<?php

namespace WDRCS\App\Compatibility;

use WDRCS\App\Currency\Providers\AeliaCurrencyProvider;

defined('ABSPATH') || exit;

/**
 * Bridge for "Aelia Currency Switcher for WooCommerce".
 */
class Aelia extends Currency
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
        return AeliaCurrencyProvider::class;
    }
}
