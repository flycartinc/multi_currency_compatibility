<?php

namespace WDRCS\App\Currency\Providers;

use WDRCS\App\Currency\AbstractCurrencyProvider;
use WDR\Core\Helpers\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Provider for "Aelia Currency Switcher for WooCommerce".
 * Plugin folder: woocommerce-aelia-currencyswitcher
 */
class AeliaCurrencyProvider extends AbstractCurrencyProvider {

	/**
	 * Unique slug identifying this provider.
	 *
	 * @return string
	 */
	public static function getSlug() {
		return 'aelia_currency_switcher';
	}

	/**
	 * Whether the currency-switcher plugin this provider targets is active/loaded.
	 *
	 * @return bool
	 */
	public static function isActive() {
		return Plugin::isActive( 'woocommerce-aelia-currencyswitcher/woocommerce-aelia-currencyswitcher.php' );
	}

	/**
	 * Get the exchange rate for the given currency code relative to the shop's
	 * default/base currency, as reported by the currency-switcher plugin.
	 *
	 * @param string $currency_code Currency code (e.g. 'EUR').
	 *
	 * @return float
	 */
	public static function getExchangeRate( $currency_code ) {
		if ( empty( $currency_code ) ) {
			return 1.0;
		}

		// If currency matches the base currency, the exchange rate is always 1.0.
		$base_currency = get_option( 'woocommerce_currency' );
		if ( $currency_code === $base_currency ) {
			return 1.0;
		}

		// 1. Try to fetch via live settings object if available.
		if ( class_exists( '\Aelia\WC\CurrencySwitcher\WC_Aelia_CurrencySwitcher' ) ) {
			$settings = \Aelia\WC\CurrencySwitcher\WC_Aelia_CurrencySwitcher::settings();
			if ( $settings && method_exists( $settings, 'get_exchange_rate' ) ) {
				$rate = $settings->get_exchange_rate( $currency_code );
				if ( $rate !== false ) {
					return self::sanitizeRate( $rate );
				}
			}
		}

		// 2. Fallback: read directly from settings options in database.
		$settings = get_option( 'wc_aelia_currency_switcher' );
		if ( is_array( $settings ) && isset( $settings['exchange_rates'] ) ) {
			$exchange_rates = $settings['exchange_rates'];
			if ( is_array( $exchange_rates ) && isset( $exchange_rates[ $currency_code ] ) ) {
				$rate_settings = $exchange_rates[ $currency_code ];
				$rate          = 1.0;

				if ( is_array( $rate_settings ) && ! empty( $rate_settings['rate'] ) && is_numeric( $rate_settings['rate'] ) ) {
					$rate        = (float) $rate_settings['rate'];
					$rate_markup = isset( $rate_settings['rate_markup'] ) ? trim( $rate_settings['rate_markup'] ) : '';
					if ( ! empty( $rate_markup ) ) {
						if ( is_numeric( $rate_markup ) ) {
							$rate += (float) $rate_markup;
						} elseif ( function_exists( 'aelia_get_percentage_multiply_factor' ) ) {
							$markup_factor = aelia_get_percentage_multiply_factor( $rate_markup );
							if ( is_numeric( $markup_factor ) ) {
								$rate = $rate * $markup_factor;
							}
						} else {
							$clean_markup = str_replace( '%', '', $rate_markup );
							if ( is_numeric( $clean_markup ) ) {
								$rate = $rate * ( 1 + (float) $clean_markup / 100 );
							}
						}
					}
				} elseif ( is_numeric( $rate_settings ) ) {
					$rate = (float) $rate_settings;
				}

				return self::sanitizeRate( $rate );
			}
		}

		return 1.0;
	}

	/**
	 * Get the currency code this provider currently has applied for the visitor/session.
	 *
	 * @return string
	 */
	public static function getCurrentCurrency() {
		if ( class_exists( '\Aelia\WC\CurrencySwitcher\WC_Aelia_CurrencySwitcher' ) ) {
			$instance = \Aelia\WC\CurrencySwitcher\WC_Aelia_CurrencySwitcher::instance();
			if ( $instance && method_exists( $instance, 'get_selected_currency' ) ) {
				$currency = $instance->get_selected_currency();
				if ( ! empty( $currency ) ) {
					return $currency;
				}
			}
		}

		return parent::getCurrentCurrency();
	}

	/**
	 * Get the exchange rate this provider recorded on $order at the time it was placed.
	 *
	 * Aelia saves the exchange rate to convert order_currency -> base_currency in the order
	 * meta key '_base_currency_exchange_rate'. Since we want the rate of order_currency
	 * relative to base_currency (base_currency -> order_currency), we calculate
	 * 1 / _base_currency_exchange_rate.
	 *
	 * @param \WC_Order $order Order.
	 *
	 * @return float|null Null if none is found on this order.
	 */
	public static function getOrderExchangeRate( $order ) {
		if ( ! $order instanceof \WC_Order ) {
			return null;
		}

		$order_currency = $order->get_currency();
		if ( empty( $order_currency ) ) {
			return null;
		}

		$base_currency = get_option( 'woocommerce_currency' );
		if ( $order_currency === $base_currency ) {
			return 1.0;
		}

		// 1. Try reading _base_currency_exchange_rate from order meta.
		$base_exchange_rate = $order->get_meta( '_base_currency_exchange_rate' );
		if ( is_numeric( $base_exchange_rate ) && $base_exchange_rate > 0 ) {
			return self::sanitizeRate( 1 / (float) $base_exchange_rate );
		}

		// 2. Fallback: compute it using order totals.
		$order_total_base = $order->get_meta( '_order_total_base_currency' );
		if ( is_numeric( $order_total_base ) && $order_total_base > 0 ) {
			$order_total = (float) $order->get_total();
			if ( $order_total > 0 ) {
				return self::sanitizeRate( $order_total / (float) $order_total_base );
			}
		}

		// 3. Fallback: resolve against live settings.
		return self::getExchangeRate( $order_currency );
	}
}
