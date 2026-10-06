<?php

namespace WDRCS\App\Currency\Providers;

use WDRCS\App\Currency\AbstractCurrencyProvider;
use WDR\Core\Helpers\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Provider for "YITH Multi Currency Switcher for WooCommerce".
 * Plugin folder: yith-multi-currency-switcher-for-woocommerce
 */
class YithMultiCurrencyProvider extends AbstractCurrencyProvider {

	public static function getSlug() {
		return 'yith_currency_switcher';
	}

	public static function isActive() {
		return Plugin::isActive( 'yith-multi-currency-switcher-for-woocommerce/init.php' );
	}

	public static function getExchangeRate( $currency_code ) {
		if ( ! function_exists( 'yith_wcmcs_get_currency' ) ) {
			return 1.0;
		}

		$currency = yith_wcmcs_get_currency( $currency_code );
		if ( empty( $currency ) || ! method_exists( $currency, 'get_rate' ) ) {
			return 1.0;
		}

		return self::sanitizeRate( $currency->get_rate() );
	}

	public static function getCurrentCurrency() {
		return function_exists( 'yith_wcmcs_get_current_currency_id' ) ? yith_wcmcs_get_current_currency_id() : parent::getCurrentCurrency();
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
