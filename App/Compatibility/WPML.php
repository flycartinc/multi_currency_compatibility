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
