<?php

namespace WDRCS\App\Compatibility;

use WDRCS\App\Currency\Providers\AeliaCurrencyProvider;

defined('ABSPATH') || exit;

/**
 * Bridge for "Aelia Currency Switcher for WooCommerce".
 *
 * Aelia converts a cart item's product once, when the cart loads (`woocommerce_get_cart_item_from_session`, priority 15), and
 * hands back the stored price on every later read: the price Discount Rules sets is used as is, in the visitor's currency. Its
 * fixed coupon amounts are converted on every read (base → visitor); fees not at all.
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

    protected static function convertsCartItemPrice()
    {
        return false;
    }

    protected static function provider()
    {
        return AeliaCurrencyProvider::class;
    }
}
