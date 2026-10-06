<?php

namespace WDRCS\App\Compatibility;

use WDRCS\App\Currency\Providers\WooMultiCurrencyProvider;

defined('ABSPATH') || exit;

/**
 * Bridge for "Multi Currency for WooCommerce (CURCY) by VillaTheme".
 */
class VillaTheme extends Currency
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
        return WooMultiCurrencyProvider::class;
    }
}
