<?php

namespace WDRCS\App\Compatibility;

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
        if (self::isPro()) {
            add_filter('wmc_product_get_price_condition', [__CLASS__, 'convertsPrice'], 10, 3);
            add_filter('wmc_product_variation_get_price_condition', [__CLASS__, 'convertsPrice'], 10, 3);
            // after CURCY's own conversion (priority 10)
            add_filter('woocommerce_coupon_get_amount', [__CLASS__, 'getCouponAmount'], 11, 2);
        }
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
