<?php

namespace WDRCS\App\Compatibility;

use WDRCS\App\Currency\Providers\YithMultiCurrencyProvider;

defined('ABSPATH') || exit;

/**
 * Bridge for "YITH Multi Currency Switcher for WooCommerce".
 *
 * YITH converts every price read — and puts its manual price per currency or its rounding (to whole units, down to the cent) over
 * it — and every fixed coupon amount. It lets a price and a coupon skip that: the price Discount Rules sets and our coupon's amount
 * stay in the visitor's currency, as set. Its admin-ajax requests are storefront ones only with `context=frontend`: ours are added.
 */
class YITH extends Currency
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
        add_filter('yith_wcmcs_pre_product_price', [__CLASS__, 'getOurPrice'], 10, 3);
        add_filter('yith_wcmcs_coupons_filter_coupon_amount', [__CLASS__, 'convertsCoupon'], 10, 2);
        add_filter('yith_wcmcs_is_frontend', [__CLASS__, 'isStorefrontAjax']);
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
     * YITH converts a product's price unless this returns one: the price Discount Rules set, as set_price stored it.
     *
     * @param mixed $converted Null, or a price another filter gave.
     * @param mixed $price Price being read.
     * @param \WC_Product $product Product.
     *
     * @return mixed
     */
    static function getOurPrice($converted, $price, $product)
    {
        if (null !== $converted || !is_object($product)) {
            return $converted;
        }
        $ours = self::$prices[spl_object_hash($product)] ?? null;

        return null !== $ours && $ours === (string)wc_format_decimal($price) ? $ours : null;
    }

    /**
     * YITH converts a coupon's fixed amount unless this says no: not our dynamic coupon (already in the visitor's currency).
     *
     * @param bool $convert Whether YITH converts the amount.
     * @param \WC_Coupon $coupon Coupon.
     *
     * @return bool
     */
    static function convertsCoupon($convert, $coupon)
    {
        return $convert && !static::isOurCoupon($coupon);
    }

    /**
     * Discount Rules' storefront AJAX requests are storefront ones for YITH too (prices in the visitor's currency).
     *
     * @param bool $frontend Whether YITH treats the request as a storefront one.
     *
     * @return bool
     */
    static function isStorefrontAjax($frontend)
    {
        // phpcs:ignore WordPress.Security.NonceVerification.Recommended -- the action name only selects the currency context; each action checks its own nonce
        $action = isset($_REQUEST['action']) ? sanitize_key(wp_unslash($_REQUEST['action'])) : '';

        return $frontend || (wp_doing_ajax() && in_array($action, self::STOREFRONT_AJAX_ACTIONS, true));
    }

    protected static function provider()
    {
        return YithMultiCurrencyProvider::class;
    }
}
