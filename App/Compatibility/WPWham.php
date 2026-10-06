<?php

namespace WDRCS\App\Compatibility;

use WDRCS\App\Currency\Providers\WPWhamProvider;

defined('ABSPATH') || exit;

/**
 * Bridge for "Currency Switcher for WooCommerce by WPWham".
 */
class WPWham extends Currency
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

    protected static function provider()
    {
        return WPWhamProvider::class;
    }
}
