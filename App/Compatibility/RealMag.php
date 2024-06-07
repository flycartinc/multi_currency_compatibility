<?php

namespace WDR\Core\Modules\Addons\MultiCurrency\Compatibility;

defined('ABSPATH') || exit;

class RealMag extends Currency
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
        add_filter('wdr_discounted_value_format', [__CLASS__, 'getConvertedValue'], 10, 2);
        add_filter('wdr_apply_coupon_discount_based_on_filters', '__return_false', 100);
    }

    /**
     * Checks status for convert to current currency.
     *
     * @param \WOOCS $WOOCS Woocommerce currency switcher object.
     * @param bool $convert_to_current_currency Status for convert to current currency.
     * @return bool
     */
    public static function isConvertToCurrenctCurrency(\WOOCS $WOOCS, bool $convert_to_current_currency)
    {
        if ((isset($WOOCS->is_geoip_manipulation) && $WOOCS->is_geoip_manipulation)
            || (isset($WOOCS->is_multiple_allowed) && $WOOCS->is_multiple_allowed)
            || (isset($WOOCS->woocs_is_fixed_enabled) && $WOOCS->woocs_is_fixed_enabled)) {
            $convert_to_current_currency = true;
        }
        return $convert_to_current_currency;
    }

    /**
     * Checks get_currencies method.
     *
     * @param \WOOCS $WOOCS Woocommerce currency switcher object.
     * @return bool
     */
    public static function isCurrencyMethod($WOOCS)
    {
        return !is_object($WOOCS) || !method_exists($WOOCS, 'get_currencies');
    }

    /**
     * Get currency conversion rate.
     *
     * @return float|null
     */
    static function getConversionRate()
    {
        global $WOOCS;
        if (self::isCurrencyMethod($WOOCS)) {
            return null;
        }
        $current_currency = $WOOCS->current_currency;
        $currencies = $WOOCS->get_currencies();
        return isset($currencies[$current_currency]['rate']) ? $currencies[$current_currency]['rate'] : null;
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
        global $WOOCS;
        if (empty($price) || !is_object($WOOCS) || !method_exists($WOOCS, 'wcml_raw_price_amount')) {
            return $price;
        }
        return $WOOCS->wcml_raw_price_amount($price);
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
        global $WOOCS;
        if (empty($price) || !is_object($WOOCS) || !method_exists($WOOCS, 'get_currencies')) {
            return $price;
        }
        $convert_to_current_currency = self::isConvertToCurrenctCurrency($WOOCS, false);
        if (!$convert_to_current_currency) {
            return $price;
        }
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
        global $WOOCS;
        if (self::isCurrencyMethod($WOOCS)) {
            return $discount_value_formatted;
        }
        $convert_to_current_currency = self::isConvertToCurrenctCurrency($WOOCS, false);
        if (!$convert_to_current_currency) {
            return $discount_value_formatted;
        }
        $discount_value_formatted = $WOOCS->wc_price($discount_value);
        if ($discount_type == 'flat') {
            $discount_value_formatted .= ' ' . __('flat', 'woo-discount-rules');
        } elseif ($range['discount_method'] == 'set' && $discount_type == 'fixed_set_price') {
            $discount_value_formatted = wc_price($discount_value);
        }
        return $discount_value_formatted;
    }

}