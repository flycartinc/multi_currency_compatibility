<?php

namespace WDRCS\App\Compatibility;

use WDR\Core\Helpers\WC;
use WDR\Core\Helpers\Settings;

defined('ABSPATH') || exit;

class WPWham extends Currency
{

    /**
     * Initiates action.
     *
     * @return void
     */
    function run()
    {
	    add_filter('wdr_custom_price_convert', [__CLASS__, 'getCovertAmount'], 10, 3);
	    add_filter('wdr_discount_get_fixed_price', [__CLASS__, 'getConvertedPrice'], 10, 2);
        add_filter('wdr_discounted_cart_item_price', [__CLASS__, 'getCartConvertedPrice'], 10, 2);
        add_filter('wdr_discounted_value_format', [__CLASS__, 'getConvertedValue'], 10, 2);
        add_filter('wdr_discount_coupon_data', [__CLASS__, 'getCouponData'], 10, 1);
        add_filter('wdr_apply_coupon_discount_based_on_filters', '__return_false', 100);
	    if ( Settings::get( 'suppress_other_discount_plugins' ) ) {
		    add_filter( 'wdr_suppress_allowed_hooks', 'WDRCS\App\Controller\Base::removeSuppressedHooks', 10, 1 );
	    }
    }

	/**
	 * Converting price amount.
	 *
	 * @param float|int $price Item price.
	 * @param string $from_currency
	 * @param string $to_currency
	 *
	 * @return float|int
	 */
	static function getCovertAmount($price, $from_currency, $to_currency)
	{
		if (empty($price) || !function_exists('alg_get_current_currency_code')) {
			return $price;
		}
		$rate = self::getCurrencyExchangeRate($from_currency);
		return (float) $price / $rate;
	}

    /**
     * Converting cart coupon data.
     *
     * @param array $coupon_data Coupon data.
     * @return array
     */
    static function getCouponData(array $coupon_data)
    {
        if (empty($coupon_data['amount']) || !function_exists('alg_get_current_currency_code')) {
            return $coupon_data;
        }
        $currency_code = alg_get_current_currency_code();
        $rate = self::getCurrencyExchangeRate($currency_code);
        $coupon_data['amount'] = ($rate != 0) ? $coupon_data['amount'] / $rate : $coupon_data['amount'];
        return $coupon_data;
    }

    /**
     * Converting price amount.
     *
     * @param float|int $price Item price.
     * @param string $discount_type
     * @return float|int
     */
    static function getConvertedPrice($price, string $discount_type)
    {
        if (empty($price) || !function_exists('alg_get_current_currency_code')) {
            return $price;
        }
        $currency_code = alg_get_current_currency_code();
        $rate = self::getCurrencyExchangeRate($currency_code);
        return (float)$price * $rate;
    }

    /**
     * Converting price amount.
     *
     * @param float|int $price Item price.
     * @param array $cart_item Cart item.
     * @return float|int
     */
    static function getCartConvertedPrice($price, array $cart_item)
    {
        if (empty($price) || !function_exists('alg_get_current_currency_code')) {
            return $price;
        }
        $currency_code = alg_get_current_currency_code();
        $rate = self::getCurrencyExchangeRate($currency_code);
        return ($rate != 0) ? $price / $rate : $price;
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
        if ($discount_type == 'percentage') {
            return $discount_value_formatted;
        }
        $discount_value = $range['discount_value'] ?? '';
        if (empty($discount_value) || !function_exists('alg_get_current_currency_code')) {
            return $discount_value_formatted;
        }
        $currency_code = alg_get_current_currency_code();
        $rate = self::getCurrencyExchangeRate($currency_code);
        $discount_value_formatted = WC::formatPrice((float)$discount_value * $rate);
        if ($discount_type == 'flat') {
            $discount_value_formatted .= ' ' . __('flat', 'wdr-multi-currency-compatibility');
        } elseif ($range['discount_method'] == 'set' && $discount_type == 'fixed_set_price') {
            $discount_value_formatted = wc_price($discount_value);
        }
        $discount_value_formatted .= !empty($cart_discount_text) ? $cart_discount_text : '';
        return $discount_value_formatted;
    }

    /**
     * Get currency exchange rate.
     *
     * @param string $currency_code Currency code.
     * @return float
     */
    static function getCurrencyExchangeRate(string $currency_code)
    {
        if (!class_exists('Alg_WC_Currency_Switcher') ||
            !function_exists('alg_wc_cs_get_currency_exchange_rate')) {
            return 1;
        }
        return alg_wc_cs_get_currency_exchange_rate($currency_code);
    }

}