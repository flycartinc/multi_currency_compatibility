<?php

namespace WDRCS\App\Compatibility;

use WDR\Core\Controllers\Store\Product;
use WDR\Core\Helpers\Settings;
use WDR\Core\Helpers\WC;

defined('ABSPATH') || exit;

/**
 * Shared bridge between WDR Core and a currency-switcher plugin.
 *
 * WDR Core runs its discount maths on the price the switcher currently exposes (i.e. already in
 * the visitor's currency), so every place where an amount crosses back into the store's base
 * currency has to be converted here. A raw base-currency price must not be fed to WDR: WDR formats
 * its price HTML without any switcher conversion, so the shop/cart would show base-currency numbers
 * under the visitor's currency symbol.
 *
 * A subclass only has to say which provider it is built on (see provider()) and may override
 * getRate() / the converters when its plugin behaves differently.
 */
abstract class Currency
{
    abstract function run();

    /**
     * Provider class (App\Currency\Providers\*) of the switcher plugin this bridge targets.
     *
     * @return string
     */
    abstract protected static function provider();

    /**
     * Register the shared conversion hooks.
     *
     * @return void
     */
    protected function registerHooks()
    {
        add_filter('wdr_custom_price_convert', [static::class, 'getCovertAmount'], 10, 4);
        // before WooCommerce's own save of the order — classic checkout and Store API
        add_action('woocommerce_checkout_create_order', [static::class, 'saveOrderRate']);
        add_action('woocommerce_store_api_checkout_update_order_meta', [static::class, 'saveOrderRate']);
        add_filter('wdr_discount_get_fixed_price', [static::class, 'getConvertedPrice'], 10, 2);
        add_filter('wdr_discounted_cart_item_price', [static::class, 'getCartConvertedPrice'], 10, 2);
        add_filter('wdr_discount_coupon_data', [static::class, 'getCouponData'], 10, 1);
        add_filter('wdr_discounted_value_format', [static::class, 'getConvertedValue'], 10, 2);
        add_filter('wdr_apply_coupon_discount_based_on_filters', '__return_false', 100);
        if (Settings::get('suppress_other_discount_plugins')) {
            add_filter('wdr_suppress_allowed_hooks', 'WDRCS\App\Controller\Base::removeSuppressedHooks', 10, 1);
        }
    }

    /**
     * A switcher's cache mode re-renders product prices in an admin-ajax request (CURCY `wmc_get_products_price`, FOX
     * `woocs_get_products_price_html`), where Discount Rules' storefront hooks are not loaded: add its price HTML filters for
     * that request.
     *
     * @return void
     */
    static function addPriceHtmlHooks()
    {
        if (!has_filter('woocommerce_get_price_html', [Product::class, 'getPriceHtml'])) {
            add_filter('woocommerce_get_price_html', [Product::class, 'getPriceHtml'], 100, 2);
            add_filter('woocommerce_variable_price_html', [Product::class, 'getVariablePriceHtml'], 100, 2);
        }
    }

    /**
     * Whether the switcher converts a cart item's price again whenever WooCommerce reads it, so the
     * discounted price has to be handed over in the store's base currency. Override with false for
     * switchers that keep a cart item's price as set.
     *
     * @return bool
     */
    protected static function convertsCartItemPrice()
    {
        return true;
    }

    /**
     * Whether the switcher converts a dynamic coupon's fixed amount into the visitor's currency,
     * so the amount has to be handed over in the store's base currency. Override with false for
     * switchers that use the amount as is.
     *
     * @return bool
     */
    protected static function convertsCouponAmount()
    {
        return true;
    }

    /**
     * Rate of the currency currently applied for the visitor, relative to the store's base currency.
     *
     * @return float
     */
    protected static function getRate()
    {
        $provider = static::provider();

        return $provider::getExchangeRate(WC::getCurrentCurrency());
    }

    /**
     * Rate of a given currency, relative to the store's base currency.
     *
     * @param string $currency_code Currency code.
     *
     * @return float
     */
    protected static function getRateOf($currency_code)
    {
        $provider = static::provider();

        return $provider::getExchangeRate($currency_code);
    }

    /**
     * Convert a past order's amount from $from_currency into the store's base currency — at the rate saved on
     * the order at checkout (saveOrderRate()); an order from before that keeps today's rate.
     *
     * @param int|float $price Amount in $from_currency.
     * @param string $from_currency Currency code the amount is in.
     * @param string $to_currency Store currency code.
     * @param float|string|null $order_rate The rate saved on the order, when known.
     *
     * @return float|int
     */
    static function getCovertAmount($price, $from_currency, $to_currency, $order_rate = null)
    {
        if (empty($price) || empty($from_currency) || $from_currency === $to_currency) {
            return $price;
        }
        $rate = (float)$order_rate > 0 ? (float)$order_rate : static::getRateOf($from_currency);

        return $rate > 0 ? (float)$price / $rate : $price;
    }

    /**
     * Set the order currency's rate on the order at checkout (classic and Store API), so the purchase-history
     * conditions convert it back at that rate later, not at the day's rate. Discount Rules reads it with the
     * order totals in one query (\WDR\Core\Models\WC\Order::EXCHANGE_RATE_META). Hooked before WooCommerce
     * saves the order, so no save here: a save_meta_data() could trigger a second full order save (HPOS).
     *
     * @param \WC_Order $order Order.
     *
     * @return void
     */
    static function saveOrderRate($order)
    {
        if (!$order instanceof \WC_Order || $order->get_currency() === WC::getStoreCurrency()) {
            return;
        }
        $order->update_meta_data('_wdr_exchange_rate', static::getRateOf($order->get_currency()));
    }

    /**
     * Convert a fixed discount amount configured in the store's base currency into the visitor's currency.
     *
     * @param int|float $price Fixed amount in base currency.
     * @param string $discount_type Discount type.
     *
     * @return float|int
     */
    static function getConvertedPrice($price, $discount_type)
    {
        if (empty($price)) {
            return $price;
        }

        return (float)$price * static::getRate();
    }

    /**
     * Hand WooCommerce the base-currency value when the switcher converts a cart item's price again
     * on read (see convertsCartItemPrice()). With the "override price" snippet WDR bypasses the switcher's price filter itself, so
     * keep the visitor-currency value as is.
     *
     * @param int|float $price Discounted price in the visitor's currency.
     * @param array $cart_item Cart item.
     *
     * @return float|int
     */
    static function getCartConvertedPrice($price, $cart_item)
    {
        if (empty($price) || !static::convertsCartItemPrice() || Settings::get('wdr_override_custom_price')) {
            return $price;
        }
        $rate = static::getRate();

        return $rate > 0 ? $price / $rate : $price;
    }

    /**
     * Hand the switcher the base-currency amount when it converts a fixed coupon amount into the
     * visitor's currency (see convertsCouponAmount()); otherwise hand WooCommerce the amount at the
     * currency's precision.
     *
     * @param array $coupon_data Coupon data.
     *
     * @return array
     */
    static function getCouponData($coupon_data)
    {
        if (empty($coupon_data['amount']) || ($coupon_data['discount_type'] ?? '') === 'percent') {
            return $coupon_data;
        }
        // WooCommerce splits a fixed_cart amount in cents and turns a leftover fraction of a cent into a
        // whole cent (10 % of $3.54 = 0.354 → −$0.36, where its percent coupon gives −$0.35): the amount at the
        // visitor currency's precision — before the divide-back, so the switcher's conversion lands on it
        $coupon_data['amount'] = round((float)$coupon_data['amount'], wc_get_price_decimals());
        if (!static::convertsCouponAmount()) {
            return $coupon_data;
        }
        $rate = static::getRate();
        if ($rate > 0) {
            $coupon_data['amount'] = $coupon_data['amount'] / $rate;
        }

        return $coupon_data;
    }

    /**
     * Get converted value for the discount table.
     *
     * @param string $discount_value_formatted Discount format value.
     * @param array $range Discount range.
     *
     * @return string
     */
    static function getConvertedValue($discount_value_formatted, $range)
    {
        $discount_type = !empty($range['discount_type']) ? $range['discount_type'] : '';
        if ($discount_type == 'percentage') {
            return $discount_value_formatted;
        }
        $discount_value = !empty($range['discount_value']) ? $range['discount_value'] : '';
        if (empty($discount_value)) {
            return $discount_value_formatted;
        }
        $discount_value_formatted = WC::formatPrice((float)$discount_value * static::getRate());
        if ($discount_type == 'flat') {
            $discount_value_formatted .= ' ' . __('flat', 'wdr-multi-currency-compatibility');
        } elseif (($range['discount_method'] ?? '') == 'set' && $discount_type == 'fixed_set_price' && isset($range['discount_price'])) {
            $discount_value_formatted = WC::formatPrice((float)$range['discount_price']);
        }

        return $discount_value_formatted;
    }
}
