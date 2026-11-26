<?php

namespace WDRCS\App\Currency\Providers;

use WDRCS\App\Currency\AbstractCurrencyProvider;

defined( 'ABSPATH' ) || exit;

/**
 * Provider for "WooCommerce Currency Switcher" (WOOCS) by realmag777 — FOX free and FOX Professional (one codebase,
 * class WOOCS, in any plugin folder).
 */
class WoocsProvider extends AbstractCurrencyProvider {

	public static function getSlug() {
		return 'realmag_currency_switcher';
	}

	/**
	 * FOX's object, built when its plugin file loads: the free folder `woocommerce-currency-switcher` or a premium build in
	 * another one. Not built on the pages FOX's own settings switch it off for.
	 *
	 * @return bool
	 */
	public static function isActive() {
		return isset( $GLOBALS['WOOCS'] ) && $GLOBALS['WOOCS'] instanceof \WOOCS;
	}

	public static function getExchangeRate( $currency_code ) {
		global $WOOCS;

		if ( empty( $WOOCS ) || ! method_exists( $WOOCS, 'get_currencies' ) ) {
			return 1.0;
		}

		$currencies = $WOOCS->get_currencies();

		return isset( $currencies[ $currency_code ]['rate'] ) ? self::sanitizeRate( $currencies[ $currency_code ]['rate'] ) : 1.0;
	}

	/**
	 * WOOCS saves the rate it applied on `woocommerce_checkout_update_order_meta` as order
	 * meta `_woocs_order_rate`. Reading it back is a plain postmeta read on the WC_Order
	 * object - it needs no live WOOCS runtime state, so it works reliably even in async
	 * webhook-delivery/cron contexts.
	 *
	 * @param \WC_Order $order Order.
	 *
	 * @return float|null
	 */
	public static function getOrderExchangeRate( $order ) {
		if ( ! $order instanceof \WC_Order ) {
			return null;
		}

		$rate = $order->get_meta( '_woocs_order_rate' );

		return ( $rate !== '' && $rate !== null ) ? self::sanitizeRate( $rate ) : null;
	}
}
