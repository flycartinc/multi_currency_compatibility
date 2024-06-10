<?php

namespace WDRCS\App\Compatibility;

use WDR\Core\Helpers\WC;

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
        add_filter('wdr_discount_get_fixed_price', [__CLASS__, 'getConvertedPrice'], 10, 2);
        add_filter('wdr_discounted_value_format', [__CLASS__, 'getConvertedValue'], 10, 2);
        add_filter('wdr_apply_coupon_discount_based_on_filters', '__return_false', 100);
    }


    /**
     * Converting price amount.
     *
     * @param int|float $price Item price.
     * @param string $discount_type Discount type.
     * @return mixed|void
     */
    static function getConvertedPrice($price, string $discount_type)
    {
        if (!is_numeric($price) || empty($price)) {
            return $price;
        }
        return apply_filters('wcml_raw_price_amount', $price);
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
        $currency_code = self::getCurrentCurrencyCode();
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
        $discount_value_formatted .= !empty($cart_discount_text) ? $cart_discount_text : '';
        return $discount_value_formatted;

    }

    /**
     * Current currency code.
     *
     * @return mixed
     */
    static function getCurrentCurrencyCode()
    {
        global $woocommerce_wpml;
        if (!empty($woocommerce_wpml)) {
            $multi_currency = $woocommerce_wpml->get_multi_currency();
            return $multi_currency->get_client_currency();
        }
        return '';
    }

}