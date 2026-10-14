<?php

namespace WDRCS\App\Compatibility;

use WDRCS\App\Currency\Providers\WPWhamProvider;

defined('ABSPATH') || exit;

/**
 * Bridge for "Currency Switcher for WooCommerce by WPWham".
 *
 * WPWham converts every price read (its per-product prices, rounding, pretty price and "save prices in array" included) and
 * every fixed coupon amount, with no way to skip one: a cart price or coupon amount handed back in the base currency would come
 * back as WPWham's own price or rounded again. Our values stay in the visitor's currency instead and are put back after WPWham's
 * conversion (the same `PHP_INT_MAX`, added later — on `init`).
 */
class WPWham extends Currency
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
        add_filter('woocommerce_product_get_price', [__CLASS__, 'getOurPrice'], PHP_INT_MAX, 2);
        add_filter('woocommerce_product_variation_get_price', [__CLASS__, 'getOurPrice'], PHP_INT_MAX, 2);
        add_filter('woocommerce_coupon_get_amount', [__CLASS__, 'getCouponAmount'], PHP_INT_MAX, 2);
        // WPWham converts every cart fee into the visitor's currency (its "cart fees" option, on by default)
        if ('yes' === get_option('alg_currency_switcher_cart_fees_enabled', 'yes')) {
            add_filter('wdr_discount_fee_amount', [__CLASS__, 'getFeeAmount']);
        }
    }

    protected static function convertsCartItemPrice()
    {
        return false;
    }

    protected static function convertsCouponAmount()
    {
        return false;
    }

    /**
     * Remember the price Discount Rules sets on the cart item (in the visitor's currency).
     *
     * @param int|float $price Discounted price in the visitor's currency.
     * @param array $cart_item Cart item.
     *
     * @return float|int
     */
    static function getCartConvertedPrice($price, $cart_item)
    {
        if (!empty($cart_item['data']) && is_object($cart_item['data'])) {
            self::$prices[spl_object_hash($cart_item['data'])] = (string)wc_format_decimal($price);
        }

        return parent::getCartConvertedPrice($price, $cart_item);
    }

    /**
     * The price Discount Rules set on a cart item, as set_price stored it — not WPWham's conversion of it.
     *
     * @param mixed $price Price after WPWham's conversion.
     * @param \WC_Product $product Product.
     *
     * @return mixed
     */
    static function getOurPrice($price, $product)
    {
        if (!is_object($product) || !isset(self::$prices[spl_object_hash($product)])) {
            return $price;
        }
        $ours = self::$prices[spl_object_hash($product)];

        return $ours === (string)wc_format_decimal($product->get_price('edit')) ? $ours : $price;
    }

    /**
     * Hand WPWham our fee-mode discount in the store's base currency: it multiplies every fee by the rate after us.
     *
     * @param int|float $amount Discount in the visitor's currency.
     *
     * @return float|int
     */
    static function getFeeAmount($amount)
    {
        $rate = static::getRate();

        return $rate > 0 ? $amount / $rate : $amount;
    }

    protected static function provider()
    {
        return WPWhamProvider::class;
    }
}
