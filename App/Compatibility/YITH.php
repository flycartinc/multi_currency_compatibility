<?php

namespace WDRCS\App\Compatibility;

use WDRCS\App\Currency\Providers\YithMultiCurrencyProvider;

defined('ABSPATH') || exit;

/**
 * Bridge for "YITH Multi Currency Switcher for WooCommerce".
 */
class YITH extends Currency
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
        return YithMultiCurrencyProvider::class;
    }
}
