<?php

namespace WDRCS\App\Compatibility;

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
        add_filter('wdr_custom_price_convert', [static::class, 'getCovertAmount'], 10, 3);
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

        return $provider::getExchangeRate($provider::getCurrentCurrency());
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
     * Convert an amount from $from_currency into the store's base currency.
     *
     * @param int|float $price Amount in $from_currency.
     * @param string $from_currency Currency code the amount is in.
     * @param string $to_currency Store currency code.
     *
     * @return float|int
     */
    static function getCovertAmount($price, $from_currency, $to_currency)
    {
        if (empty($price) || empty($from_currency) || $from_currency === $to_currency) {
            return $price;
        }
        $rate = static::getRateOf($from_currency);

        return $rate > 0 ? (float)$price / $rate : $price;
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
     * visitor's currency (see convertsCouponAmount()).
     *
     * @param array $coupon_data Coupon data.
     *
     * @return array
     */
    static function getCouponData($coupon_data)
    {
        if (empty($coupon_data['amount']) || !static::convertsCouponAmount() || ($coupon_data['discount_type'] ?? '') === 'percent') {
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
