<?php

namespace WDRCS\App\Compatibility;

use WDR\Core\Helpers\Settings;
use WDRCS\App\Controller\Base;
use WDRCS\App\Currency\Providers\WoocsProvider;

defined( 'ABSPATH' ) || exit;


class RealMag extends Currency {
	/**
	 * Initiates action.
	 *
	 * @return void
	 */
	function run() {
		add_filter( 'wdr_discount_get_product_price', 'WDRCS\App\Controller\Base::getRawProductPrice', 10, 4 );
		add_filter( 'wdr_discount_coupon_data', [ __CLASS__, 'getCouponData' ], 10, 1 );
		add_filter( 'wdr_discounted_value_format', [ __CLASS__, 'getConvertedValue' ], 10, 2 );
		add_filter( 'wdr_discount_product_data', [ __CLASS__, 'getProductData' ], 10, 1 );
		add_filter( 'wdr_apply_coupon_discount_based_on_filters', '__return_false', 100 );
		if ( Settings::get( 'suppress_other_discount_plugins' ) ) {
			add_filter( 'wdr_suppress_allowed_hooks', 'WDRCS\App\Controller\Base::removeSuppressedHooks', 10, 1 );
		}
	}

	/**
	 * WOOCS only converts cart-facing prices when one of its own "convert to current
	 * currency" flags is enabled (geoip manipulation, multiple currencies allowed, or
	 * fixed-price mode).
	 *
	 * @param \WOOCS $WOOCS Woocommerce currency switcher object.
	 *
	 * @return bool
	 */
	public static function isConvertToCurrentCurrency( \WOOCS $WOOCS ) {
		return ( isset( $WOOCS->is_geoip_manipulation ) && $WOOCS->is_geoip_manipulation )
			|| ( isset( $WOOCS->is_multiple_allowed ) && $WOOCS->is_multiple_allowed )
			|| ( isset( $WOOCS->woocs_is_fixed_enabled ) && $WOOCS->woocs_is_fixed_enabled );
	}

	/**
	 * Converting cart coupon data. A dynamic WooCommerce coupon's `amount` is applied
	 * directly against the cart's already display-currency total by WooCommerce's own
	 * coupon math (not filtered by WOOCS), so it must be converted up from the
	 * base-currency discount amount WDR computed it from - gated behind the same
	 * "is WOOCS actually converting cart-facing prices" check this bridge already uses
	 * for every other cart-facing amount.
	 *
	 * @param array $coupon_data Coupon data.
	 *
	 * @return array
	 */
	static function getCouponData( array $coupon_data ) {
		global $WOOCS;
		if ( empty( $coupon_data['amount'] ) || ! is_object( $WOOCS ) || ! self::isConvertToCurrentCurrency( $WOOCS ) ) {
			return $coupon_data;
		}
		$coupon_data['amount'] = $coupon_data['amount'] * WoocsProvider::getExchangeRate( WoocsProvider::getCurrentCurrency() );

		return $coupon_data;
	}

	/**
	 * Get converted value.
	 *
	 * @param string $discount_value_formatted Discount format value.
	 * @param array $range Discount range.
	 *
	 * @return string
	 */
	static function getConvertedValue( string $discount_value_formatted, array $range ) {
		$discount_type = isset( $range['discount_type'] ) && ! empty( $range['discount_type'] ) ? $range['discount_type'] : '';
		if ( $discount_type == 'percentage' ) {
			return $discount_value_formatted;
		}
		$discount_value = isset( $range['discount_value'] ) && ! empty( $range['discount_value'] ) ? $range['discount_value'] : '';
		if ( empty( $discount_value ) ) {
			return $discount_value_formatted;
		}
		global $WOOCS;
		if ( empty( $WOOCS ) || ! is_object( $WOOCS ) || ! method_exists( $WOOCS, 'get_currencies' ) || ! self::isConvertToCurrentCurrency( $WOOCS ) ) {
			return $discount_value_formatted;
		}
		$discount_value_formatted = $WOOCS->wc_price( $discount_value );
		if ( $discount_type == 'flat' ) {
			$discount_value_formatted .= ' ' . __( 'flat', 'wdr-multi-currency-compatibility' );
		} elseif ( $range['discount_method'] == 'set' && $discount_type == 'fixed_set_price' ) {
			$discount_value_formatted = wc_price( $discount_value );
		}

		return $discount_value_formatted;
	}

	/**
	 * Re-fetches the product as a fresh WC_Product instance before WDR Core uses it for
	 * shop-page pricing, so WOOCS's own price filter applies cleanly on read.
	 *
	 * @param \WC_Product|int $product Product object or ID.
	 *
	 * @return \WC_Product|int
	 */
	public static function getProductData( $product ) {
		$item_id = is_object( $product ) && method_exists( $product, 'get_id' ) ? $product->get_id() : $product;

		return function_exists( 'wc_get_product' ) && ! empty( wc_get_product( $item_id ) ) ? wc_get_product( $item_id ) : $product;
	}

}
