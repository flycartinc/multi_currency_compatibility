<?php

namespace WDRCS\App\Compatibility;

use WDR\Core\Helpers\Settings;
use WDR\Core\Helpers\WC;

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
        add_filter('wdr_discount_get_fixed_price', [__CLASS__, 'getConvertedPrice'], 10, 2);
        add_filter('wdr_discounted_cart_item_price', [__CLASS__, 'getCartConvertedPrice'], 10, 2);
        add_filter('wdr_discount_coupon_data', [__CLASS__, 'getCouponData'], 10, 1);
        add_filter('wdr_discounted_value_format', [__CLASS__, 'getConvertedValue'], 10, 2);
        add_filter('wdr_apply_coupon_discount_based_on_filters', '__return_false', 100);
	    if (Settings::get('suppress_other_discount_plugins')) {
		    add_filter( 'wdr_suppress_allowed_hooks', 'WDRCS\App\Controller\Base::removeSuppressedHooks', 10, 1 );
	    }
    }

    /**
     * Get the currency setting object.
     *
     * @return object|null The currency setting object or null if not found.
     */
    static function getCurrencySettingObject()
    {
        if (class_exists('\WOOMULTI_CURRENCY_F_Data')) {
            return new \WOOMULTI_CURRENCY_F_Data();
        } elseif (class_exists('\WOOMULTI_CURRENCY_Data')) {
            return new \WOOMULTI_CURRENCY_Data();
        }
        return null;
    }

    /**
     * Get the currency conversion rate.
     *
     * @return float|null The currency conversion rate or null if not found.
     */
    static function getConversionRate()
    {
        $setting = self::getCurrencySettingObject();
        if ($setting === null) {
            return null;
        }
        $selected_currencies = $setting->get_list_currencies();
        $current_currency = $setting->get_current_currency();
        if (!$current_currency || !isset($selected_currencies[$current_currency]['rate'])) {
            return null;
        }
        return $selected_currencies[$current_currency]['rate'];
    }

    /**
     * Converting price amount.
     *
     * @param int|float $price Item price.
     * @param string $discount_type
     * @return float|int
     */
    static function getConvertedPrice($price, string $discount_type)
    {
        if (empty($price)) return $price;
        $rate = self::getConversionRate();
        return (float)$price * $rate;
    }

    /**
     * Converting cart coupon data.
     *
     * @param array $coupon_data Coupon data.
     * @return array
     */
    static function getCouponData(array $coupon_data)
    {
        if (empty($coupon_data['amount'])) {
            return $coupon_data;
        }
        $rate = self::getConversionRate();
        $coupon_data['amount'] = ($rate != 0) ? $coupon_data['amount'] / $rate : $coupon_data['amount'];
        return $coupon_data;
    }

    /**
     * Converting price amount.
     *
     * @param int|float $price Item price.
     * @param array $cart_item Cart item.
     * @return float|int
     */
    static function getCartConvertedPrice($price, array $cart_item)
    {
        if (empty($price)) return $price;
        $rate = self::getConversionRate();
        return ($rate != 0) ? $price / $rate : $price;
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
        $rate = self::getConversionRate();
        if ($rate === null) {
            return $discount_value_formatted;
        }
        $discount_value_formatted = WC::formatPrice((float)$discount_value * $rate);
        if ($discount_type == 'flat') {
            $discount_value_formatted .= ' ' . __('flat', 'wdr-multi-currency-compatibility');
        } elseif ($range['discount_method'] == 'set' && $discount_type == 'fixed_set_price') {
            $discount_value_formatted = WC::formatPrice($range['discount_price']);
        }
        return $discount_value_formatted;
    }

}