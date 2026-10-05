<?php

namespace WDRCS\App\Currency;

defined( 'ABSPATH' ) || exit;

/**
 * Shared helpers for currency provider implementations.
 */
abstract class AbstractCurrencyProvider implements CurrencyProviderInterface {

	/**
	 * Sanitize a raw rate value coming from a third-party plugin.
	 *
	 * @param mixed $rate Raw rate value.
	 *
	 * @return float
	 */
	protected static function sanitizeRate( $rate ) {
		$rate = floatval( $rate );

		return $rate > 0 ? $rate : 1.0;
	}

	/**
	 * Default current-currency detection, used by providers that don't expose
	 * a more specific API of their own. WooCommerce's active currency is
	 * itself filtered by whichever currency-switcher plugin is in control,
	 * so it's a reliable fallback signal.
	 *
	 * @return string
	 */
	public static function getCurrentCurrency() {
		return function_exists( 'get_woocommerce_currency' ) ? get_woocommerce_currency() : '';
	}

	/**
	 * Default: this provider doesn't know how to read a per-order rate.
	 * Override in providers whose plugin persists one as order meta.
	 *
	 * @param \WC_Order $order Order.
	 *
	 * @return float|null
	 */
	public static function getOrderExchangeRate( $order ) {
		return null;
	}
}
