<?php

namespace WDRCS\App\Currency\Providers;

use WDRCS\App\Currency\AbstractCurrencyProvider;
use WDR\Core\Helpers\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Provider for WPML's WooCommerce Multilingual & Multicurrency (WCML)
 * currency options, read directly from the stored plugin settings.
 */
class WcmlProvider extends AbstractCurrencyProvider {

	public static function getSlug() {
		return 'wpml_currency_switcher';
	}

	/**
	 * WCML can be installed/active as a plugin while its own "Multi-currency support"
	 * setting is still switched off, in which case currency_options below is meaningless.
	 * So being active isn't just about the plugin file - it also needs
	 * enable_multi_currency set to WCML_MULTI_CURRENCIES_INDEPENDENT (2) in
	 * _wcml_settings, the mode where a rate table is actually in use.
	 *
	 * @return bool
	 */
	public static function isActive() {
		if ( ! Plugin::isActive( 'woocommerce-multilingual/wpml-woocommerce.php' ) ) {
			return false;
		}

		$wcml_settings = get_option( '_wcml_settings' );

		return is_array( $wcml_settings )
			&& isset( $wcml_settings['enable_multi_currency'] )
			&& (int) $wcml_settings['enable_multi_currency'] === 2;
	}

	public static function getExchangeRate( $currency_code ) {
		$wcml_settings = get_option( '_wcml_settings' );
		if ( ! is_array( $wcml_settings ) || ! isset( $wcml_settings['currency_options'][ $currency_code ]['rate'] ) ) {
			return 1.0;
		}

		return self::sanitizeRate( $wcml_settings['currency_options'][ $currency_code ]['rate'] );
	}

	public static function getCurrentCurrency() {
		// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound
		$currency = apply_filters( 'wcml_price_currency', null );

		return ! empty( $currency ) ? $currency : parent::getCurrentCurrency();
	}

	/**
	 * WCML doesn't persist a rate snapshot on the order itself - it only stores the
	 * order's currency (WooCommerce's own `_order_currency` meta, exposed via
	 * WC_Order::get_currency()). So the best available signal is to resolve that
	 * currency against the plugin's current rate table, the same lookup
	 * getExchangeRate() does. This isn't a historical snapshot (the configured rate
	 * may have changed since the order was placed), but it's the only rate data WCML
	 * makes available for an order.
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
