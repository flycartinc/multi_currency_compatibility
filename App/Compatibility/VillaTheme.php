<?php

namespace WDRCS\App\Compatibility;

use WDR\Core\Helpers\Settings;
use WDR\Core\Helpers\WC;
use WDRCS\App\Controller\Base;
use WDRCS\App\Currency\Providers\WooMultiCurrencyProvider;

defined('ABSPATH') || exit;


class VillaTheme extends Currency
{

    /**
     * Initiates action.
     *
     * @return void
     */
    function run()
    {
        add_filter('wdr_discount_get_product_price', 'WDRCS\App\Controller\Base::getRawProductPrice', 10, 4);
        add_filter('wdr_discounted_value_format', [__CLASS__, 'getConvertedValue'], 10, 2);
        add_filter('wdr_apply_coupon_discount_based_on_filters', '__return_false', 100);
	    if (Settings::get('suppress_other_discount_plugins')) {
		    add_filter( 'wdr_suppress_allowed_hooks', 'WDRCS\App\Controller\Base::removeSuppressedHooks', 10, 1 );
	    }
    }

	/**
	 * Current exchange rate for whichever currency VillaTheme/CURCY has active.
	 *
	 * @return float
	 */
	protected static function getRate()
	{
		return WooMultiCurrencyProvider::getExchangeRate(WooMultiCurrencyProvider::getCurrentCurrency());
	}

    /**
     * Get converted value.
     *
     * @param string $discount_value_formatted Discount format value.
     * @param array $range Discount range.
     * @return string
     */
    static function getConvertedValue(string $discount_value_formatted, array $range)
    {
        $discount_type = isset($range['discount_type']) && !empty($range['discount_type']) ? $range['discount_type'] : '';
        if ($discount_type == 'percentage') {
            return $discount_value_formatted;
        }
        $discount_value = isset($range['discount_value']) && !empty($range['discount_value']) ? $range['discount_value'] : '';
        if (empty($discount_value)) {
            return $discount_value_formatted;
        }
        $rate = self::getRate();
        $discount_value_formatted = WC::formatPrice((float)$discount_value * $rate);
        if ($discount_type == 'flat') {
            $discount_value_formatted .= ' ' . __('flat', 'wdr-multi-currency-compatibility');
        } elseif ($range['discount_method'] == 'set' && $discount_type == 'fixed_set_price') {
            $discount_value_formatted = WC::formatPrice($range['discount_price']);
        }
        return $discount_value_formatted;
    }

}
