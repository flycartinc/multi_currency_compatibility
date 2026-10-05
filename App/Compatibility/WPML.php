<?php

namespace WDRCS\App\Compatibility;

use WDR\Core\Helpers\Settings;
use WDR\Core\Helpers\WC;
use WDRCS\App\Controller\Base;
use WDRCS\App\Currency\Providers\WcmlProvider;

defined('ABSPATH') || exit;

class WPML extends Currency
{

    /**
     * Initiates action.
     *
     * @return void
     */
    function run()
    {
        add_filter('wdr_discount_get_product_price', 'WDRCS\App\Controller\Base::getRawProductPrice', 10, 4);
        add_filter('wdr_discount_coupon_data', [__CLASS__, 'getCouponData'], 10, 1);
        add_filter('wdr_discounted_value_format', [__CLASS__, 'getConvertedValue'], 10, 2);
		add_filter('wdr_apply_coupon_discount_based_on_filters', '__return_false', 100);
	    if (Settings::get('suppress_other_discount_plugins')) {
		    add_filter( 'wdr_suppress_allowed_hooks', 'WDRCS\App\Controller\Base::removeSuppressedHooks', 10, 1 );
	    }
    }

	/**
	 * Converting cart coupon data. A dynamic WooCommerce coupon's `amount` is applied
	 * directly against the cart's already display-currency total by WooCommerce's own
	 * coupon math (not filtered by WCML), so it must be converted up from the
	 * base-currency discount amount WDR computed it from - `wcml_raw_price_amount` is
	 * WCML's own base-to-display conversion filter.
	 *
	 * @param array $coupon_data Coupon data.
	 *
	 * @return array
	 */
	static function getCouponData( array $coupon_data ) {
		if ( empty( $coupon_data['amount'] ) ) {
			return $coupon_data;
		}
		$coupon_data['amount'] = apply_filters( 'wcml_raw_price_amount', $coupon_data['amount'] );

		return $coupon_data;
	}

    /**
     * Get converted value.
     *
     * @param string $discount_value_formatted Discount format value.
     * @param array $range Discount range.
     * @return string
     */
    static function getConvertedValue(string $discount_value_formatted, array $range): string
    {
        $discount_type = $range['discount_type'] ?? '';
        $currency_code = WcmlProvider::getCurrentCurrency();
        if ($discount_type == 'percentage' || empty($currency_code)) {
            return $discount_value_formatted;
        }
        $discount_value = $range['discount_value'] ?? '';
        if ($discount_type == 'fixed_set_price') {
            return WC::formatPrice((float)$discount_value, array('currency' => $currency_code));
        }
        $discount_value_formatted = apply_filters('wcml_raw_price_amount', (float)$discount_value);
        $discount_value_formatted = WC::formatPrice((float)$discount_value_formatted, array('currency' => $currency_code));
        if ($discount_type == 'flat') {
            $discount_value_formatted .= ' ' . __('flat', 'wdr-multi-currency-compatibility');
        }
        return $discount_value_formatted;
    }

}
