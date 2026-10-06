<?php

namespace WDRCS\App\Currency\Providers;

use WDRCS\App\Currency\AbstractCurrencyProvider;
use WDR\Core\Helpers\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Provider for "Currency Switcher for WooCommerce" by WPWham (Alg WC Currency Switcher).
 * Plugin folder: currency-switcher-woocommerce
 */
class WPWhamProvider extends AbstractCurrencyProvider {

	public static function getSlug() {
		return 'wpwham_currency_switcher';
	}

	public static function isActive() {
		return Plugin::isActive( 'currency-switcher-woocommerce/currency-switcher-woocommerce.php' );
	}

	public static function getExchangeRate( $currency_code ) {
		if ( empty( $currency_code ) || ! self::isPluginLoaded() ) {
			return 1.0;
		}

		return self::sanitizeRate( alg_wc_cs_get_currency_exchange_rate( $currency_code ) );
	}

	public static function getCurrentCurrency() {
		return function_exists( 'alg_get_current_currency_code' ) ? alg_get_current_currency_code() : parent::getCurrentCurrency();
	}

	// getOrderExchangeRate() is intentionally not overridden: WPWham doesn't persist a rate
	// snapshot on the order, so no reliable per-order rate is available - this falls back to
	// AbstractCurrencyProvider's default (null), the same limitation the old per-class
	// implementation already had.

	/**
	 * Whether Alg WC Currency Switcher's own runtime (class + rate function) is loaded, not
	 * just whether the plugin file is active.
	 *
	 * @return bool
	 */
	protected static function isPluginLoaded() {
		return class_exists( 'Alg_WC_Currency_Switcher' ) && function_exists( 'alg_wc_cs_get_currency_exchange_rate' );
	}
}
