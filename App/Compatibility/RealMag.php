<?php

namespace WDRCS\App\Compatibility;

use WDRCS\App\Currency\Providers\WoocsProvider;

defined( 'ABSPATH' ) || exit;

/**
 * Bridge for "WOOCS - WooCommerce Currency Switcher" by realmag777.
 */
class RealMag extends Currency {
	/**
	 * Initiates action.
	 *
	 * @return void
	 */
	function run() {
		$this->registerHooks();
		add_filter( 'wdr_discount_product_data', [ __CLASS__, 'getProductData' ], 10, 1 );
	}

	protected static function provider() {
		return WoocsProvider::class;
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
	 * Without one of WOOCS's "convert" flags the shop keeps showing base-currency prices, so there
	 * is nothing to convert.
	 *
	 * @return float
	 */
	protected static function getRate() {
		global $WOOCS;
		if ( ! is_object( $WOOCS ) || ! self::isConvertToCurrentCurrency( $WOOCS ) ) {
			return 1.0;
		}

		return parent::getRate();
	}

	/**
	 * WOOCS reads a dynamic coupon's amount as a plain visitor-currency value, so the amount WDR
	 * computed (already in the visitor's currency) is handed over unchanged.
	 *
	 * @return bool
	 */
	protected static function convertsCouponAmount() {
		return false;
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
