<?php

namespace WDRCS\App\Compatibility;

use WDR\Core\Controllers\Store\Product;
use WDR\Core\Modules\Store\Calculator;
use WDRCS\App\Currency\Providers\WooMultiCurrencyProvider;

defined('ABSPATH') || exit;

/**
 * Bridge for "Multi Currency for WooCommerce (CURCY) by VillaTheme".
 *
 * CURCY pro passes every converted amount through its beauty-price rounding (`wmc_get_price`), so a cart price or coupon
 * amount handed back in the base currency would be rounded a second time ($1.81 → $1.99, a coupon −$0.36 → −$0.99). Pro keeps
 * our values in the visitor's currency instead: its price condition filters leave the price we set as is, and our coupon's
 * amount is put back after its conversion. Free has neither the rounding nor the condition filters: base-currency hand-back.
 */
class VillaTheme extends Currency
{
    /**
     * Cart item prices set by Discount Rules, by product object.
     *
     * @var array<string, string>
     */
    private static $prices = [];

    /**
     * Initiates action.
     *
     * @return void
     */
    function run()
    {
        $this->registerHooks();
        add_action('wmc_get_products_price_ajax_handle_before', [__CLASS__, 'addPriceHtmlHooks']);
        if (self::isPro()) {
            add_filter('wmc_product_get_price_condition', [__CLASS__, 'convertsPrice'], 10, 3);
            add_filter('wmc_product_variation_get_price_condition', [__CLASS__, 'convertsPrice'], 10, 3);
            // after CURCY's own conversion (priority 10)
            add_filter('woocommerce_coupon_get_amount', [__CLASS__, 'getCouponAmount'], 11, 2);
            // after Discount Rules' price HTML (priority 100)
            add_filter('woocommerce_get_price_html', [__CLASS__, 'addApproximatePrice'], 101, 2);
        }
    }

    /**
     * CURCY's cache-compatible mode re-renders product prices in an admin-ajax request (`wmc_get_products_price`), where
     * Discount Rules' storefront hooks are not loaded: add its price HTML filters for that request.
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
     * CURCY pro's approximate price ("Approximately: $1.20", added at priority 20) is dropped when Discount Rules rebuilds a
     * discounted product's price HTML: add it again, from the discounted price, formatted as CURCY does (price.php
     * add_approximately_price).
     * ponytail: simple products only — a variable product keeps CURCY's undiscounted range (its span comes after our HTML).
     *
     * @param string $html Price HTML.
     * @param \WC_Product $product Product.
     *
     * @return string
     */
    static function addApproximatePrice($html, $product)
    {
        if (!$product instanceof \WC_Product || $product->is_type('variable') || strpos((string)$html, 'wmc-approximately') !== false) {
            return $html;
        }
        $curcy = self::approximatePriceSource();
        $discount = $curcy ? Calculator::getShopDiscount($product) : [];
        if (empty($discount['discount_price']) || !isset($discount['discounted_price'])) {
            return $html;
        }
        $params = $curcy->prepare_param;
        $rate = static::getRate();
        $base_price = $rate > 0 ? (float)$discount['discounted_price'] / $rate : (float)$discount['discounted_price'];
        $price = number_format((float)apply_filters('wmc_get_price', $base_price * (float)$params['rate'], $params['currency_code']),
            (int)$params['decimals'], $params['decimal_separator'], $params['thousand_separator']);
        $formatted = strpos($params['symbol'], '#PRICE#') ? str_replace('#PRICE#', $price, $params['symbol']) : sprintf($params['format'], $params['symbol'], $price);
        $label = (string)\WOOMULTI_CURRENCY_Data::get_ins()->get_param('approximately_label');
        $approximate = '<span class="wmc-approximately">' . (strpos($label, '{price}') ? str_replace('{price}', $formatted, $label) : $label . $formatted) . '</span>';

        return $params['priority'] ? $approximate . $html : $html . $approximate;
    }

    /**
     * CURCY's frontend price object, when its approximate price is on for this visitor (it adds add_approximately_price at
     * priority 20 only then, with prepare_param set); CURCY keeps no reference to the object.
     *
     * @return object|null
     */
    private static function approximatePriceSource()
    {
        global $wp_filter;
        if (empty($wp_filter['woocommerce_get_price_html']->callbacks[20])) {
            return null;
        }
        foreach ($wp_filter['woocommerce_get_price_html']->callbacks[20] as $callback) {
            $function = $callback['function'];
            if (is_array($function) && is_object($function[0]) && $function[1] === 'add_approximately_price' && !empty($function[0]->prepare_param)) {
                return $function[0];
            }
        }

        return null;
    }

    protected static function provider()
    {
        return WooMultiCurrencyProvider::class;
    }

    /**
     * CURCY pro (class `WOOMULTI_CURRENCY_Data`; free is `WOOMULTI_CURRENCY_F_Data`).
     *
     * @return bool
     */
    private static function isPro()
    {
        return class_exists('WOOMULTI_CURRENCY_Data');
    }

    protected static function convertsCartItemPrice()
    {
        return !self::isPro();
    }

    protected static function convertsCouponAmount()
    {
        return !self::isPro();
    }

    /**
     * Remember the price Discount Rules sets on the cart item (pro: in the visitor's currency).
     *
     * @param int|float $price Discounted price in the visitor's currency.
     * @param array $cart_item Cart item.
     *
     * @return float|int
     */
    static function getCartConvertedPrice($price, $cart_item)
    {
        if (self::isPro() && !empty($cart_item['data']) && is_object($cart_item['data'])) {
            self::$prices[spl_object_hash($cart_item['data'])] = (string)wc_format_decimal($price);
        }

        return parent::getCartConvertedPrice($price, $cart_item);
    }

    /**
     * CURCY pro converts a product's price unless this says no: not the price Discount Rules set (as set_price stored it).
     *
     * @param bool $convert Whether CURCY converts the price.
     * @param mixed $price Price being read.
     * @param \WC_Product $product Product.
     *
     * @return bool
     */
    static function convertsPrice($convert, $price, $product)
    {
        if (!$convert || !is_object($product)) {
            return $convert;
        }

        return (self::$prices[spl_object_hash($product)] ?? null) !== (string)$price;
    }

    /**
     * Our dynamic coupon's fixed amount as Discount Rules set it (pro: in the visitor's currency), not CURCY's rounded
     * conversion of it.
     *
     * @param mixed $amount Amount after CURCY's conversion.
     * @param \WC_Coupon $coupon Coupon.
     *
     * @return mixed
     */
    static function getCouponAmount($amount, $coupon)
    {
        if (!$coupon instanceof \WC_Coupon || $coupon->get_id() || $coupon->is_type('percent')
            || empty(Calculator::getCartCouponDiscount((string)$coupon->get_code()))) {
            return $amount;
        }

        return $coupon->get_amount('edit');
    }
}
