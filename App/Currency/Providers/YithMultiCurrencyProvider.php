<?php

namespace WDRCS\App\Currency\Providers;

use WDRCS\App\Currency\AbstractCurrencyProvider;

defined( 'ABSPATH' ) || exit;

/**
 * Provider for "YITH Multi Currency Switcher for WooCommerce".
 * Plugin folder: yith-multi-currency-switcher-for-woocommerce
 */
class YithMultiCurrencyProvider extends AbstractCurrencyProvider {

	public static function getSlug() {
		return 'yith_currency_switcher';
	}

	/**
	 * Its runtime, in whichever folder the plugin is installed.
	 *
	 * @return bool
	 */
	public static function isActive() {
		return defined( 'YITH_WCMCS_INIT' ) && function_exists( 'yith_wcmcs_get_currency' );
	}

	public static function getExchangeRate( $currency_code ) {
		if ( ! function_exists( 'yith_wcmcs_get_currency' ) ) {
			return 1.0;
		}

		$currency = yith_wcmcs_get_currency( $currency_code );
		if ( empty( $currency ) || ! method_exists( $currency, 'get_rate' ) ) {
			return 1.0;
		}

		// YITH multiplies by its commission too (rate × (1 + commission %))
		$commission = method_exists( $currency, 'get_commission' ) ? (float) $currency->get_commission() : 0.0;

		return self::sanitizeRate( (float) $currency->get_rate() * ( 1 + $commission / 100 ) );
	}

	/**
	 * Get the exchange rate recorded on the order.
	 *
	 * @param \WC_Order $order Order.
	 *
	 * @return float|null
	 */
	public static function getOrderExchangeRate( $order ) {
		if ( ! $order instanceof \WC_Order ) {
			return null;
		}
		$order_currency = $order->get_currency();
		if ( empty( $order_currency ) ) {
			return null;
		}

		return self::getExchangeRate( $order_currency );
	}
}
