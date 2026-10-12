<?php

namespace WDRCS\App\Compatibility;

use WDRCS\App\Currency\Providers\WcmlProvider;

defined('ABSPATH') || exit;

/**
 * Bridge for "WooCommerce Multilingual & Multicurrency (WCML)".
 */
class WPML extends Currency
{
    /**
     * Initiates action.
     *
     * @return void
     */
    function run()
    {
        $this->registerHooks();
        add_filter('wcml_multi_currency_ajax_actions', [__CLASS__, 'addAjaxActions']);
    }

    /**
     * WCML converts prices in an admin-ajax request only for the actions on its list: add Discount Rules'
     * storefront ones, so their price HTML is in the shopper's currency like the page around it (otherwise an
     * INR price came back with the USD-converted discount: "₹60.00 ₹59.82"). v2 listed its own the same way.
     *
     * @param array $actions AJAX actions.
     *
     * @return array
     */
    public static function addAjaxActions($actions)
    {
        return array_merge((array)$actions, [
            'wdr_get_price_html',
            'wdr_ajax_get_variable_product_discount_table',
            'wdr_ajax_get_variable_product_discount_bar',
            'wdr_ajax_cart_item_variants',
            'wdr_ajax_change_cart_item_variant',
            'wdr_ajax_gift_details',
            'wdr_ajax_add_gift_product',
            'wdr_ajax_cross_sells',
        ]);
    }

    /**
     * WCML keeps a cart item's price and a dynamic coupon's amount as set, so WDR's visitor-currency
     * values are handed over unchanged.
     */
    protected static function convertsCartItemPrice()
    {
        return false;
    }

    protected static function convertsCouponAmount()
    {
        return false;
    }

    protected static function provider()
    {
        return WcmlProvider::class;
    }
}
