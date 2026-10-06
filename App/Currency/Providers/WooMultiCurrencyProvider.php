<?php

namespace WDRCS\App\Currency\Providers;

use WDRCS\App\Currency\AbstractCurrencyProvider;
use WDR\Core\Helpers\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Provider for "Multi Currency for WooCommerce" (CURCY) by VillaTheme.
 * Plugin folder: woo-multi-currency / woocommerce-multi-currency
 */
class WooMultiCurrencyProvider extends AbstractCurrencyProvider {

	public static function getSlug() {
		return 'villatheme_currency_switcher';
	}

	public static function isActive() {
		if ( ! Plugin::isActive( 'woo-multi-currency/woo-multi-currency.php' ) && ! Plugin::isActive( 'woocommerce-multi-currency/woocommerce-multi-currency.php' ) ) {
			return false;
		}

		$instance = self::getInstance();

		return $instance && method_exists( $instance, 'get_enable' ) && (bool) $instance->get_enable();
	}

	public static function getExchangeRate( $currency_code ) {
		$rate = null;

		// 1. Try to fetch from the active instance settings list first.
		// This is the most reliable method for both frontend and background (cron/REST API)
		// contexts because it reads directly from the database options without depending on visitor session.
		$instance = self::getInstance();
		if ( ! empty( $instance ) && method_exists( $instance, 'get_list_currencies' ) ) {
			$currencies = $instance->get_list_currencies();
			if ( isset( $currencies[ $currency_code ]['rate'] ) ) {
				$rate = self::sanitizeRate( $currencies[ $currency_code ]['rate'] );
			}
		}

		// 2. If the settings lookup failed or is empty, fall back to the live rate function.
		if ( empty( $rate ) && function_exists( 'wmc_get_exchange_rate' ) ) {
			$live_rate = wmc_get_exchange_rate( $currency_code );
			if ( ! empty( $live_rate ) ) {
				$rate = self::sanitizeRate( $live_rate );
			}
		}

		return ! empty( $rate ) ? $rate : 1.0;
	}

	public static function getCurrentCurrency() {
		$instance = self::getInstance();

		return $instance && method_exists( $instance, 'get_current_currency' ) ? $instance->get_current_currency() : parent::getCurrentCurrency();
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
		$order_meta     = $order->get_meta( 'wmc_order_info', true );
		if ( ! empty( $order_meta ) && is_array( $order_meta ) && ! empty( $order_currency ) && isset( $order_meta[ $order_currency ]['rate'] ) ) {
			return self::sanitizeRate( $order_meta[ $order_currency ]['rate'] );
		}

		return ! empty( $order_currency ) ? self::getExchangeRate( $order_currency ) : null;
	}

	protected static function getInstance() {
		if ( class_exists( 'WOOMULTI_CURRENCY_Data' ) ) {
			return \WOOMULTI_CURRENCY_Data::get_ins();
		} elseif ( class_exists( 'WOOMULTI_CURRENCY_F_Data' ) ) {
			return \WOOMULTI_CURRENCY_F_Data::get_ins();
		}

		return null;
	}
}
