<?php

namespace WDRCS\App\Currency;

use WDRCS\App\Currency\Providers\AeliaCurrencyProvider;
use WDRCS\App\Currency\Providers\WcmlProvider;
use WDRCS\App\Currency\Providers\WoocsProvider;
use WDRCS\App\Currency\Providers\WooMultiCurrencyProvider;
use WDRCS\App\Currency\Providers\WPWhamProvider;
use WDRCS\App\Currency\Providers\YithMultiCurrencyProvider;

defined( 'ABSPATH' ) || exit;

/**
 * Detects which supported currency-switcher plugin is active and resolves
 * exchange rates through it.
 *
 * Add a new plugin by creating a provider class under App/Currency/Providers
 * and appending it to $providers below.
 */
class CurrencyProviderManager {

	/**
	 * Registered currency providers, checked in order.
	 *
	 * @var string[]
	 */
	protected static $providers = [
		AeliaCurrencyProvider::class,
		WooMultiCurrencyProvider::class,
		WoocsProvider::class,
		YithMultiCurrencyProvider::class,
		WcmlProvider::class,
		WPWhamProvider::class,
	];

	/**
	 * Per-request cache of the active-provider filter in getActiveProvider(),
	 * so the isActive() check on every registered provider only runs once no
	 * matter how many times getActiveProvider() is called. Null until first
	 * computed.
	 *
	 * @var string[]|null
	 */
	protected static $active_providers_cache;

	/**
	 * Get the registered provider list.
	 *
	 * @return string[]
	 */
	public static function getProviders() {
		return apply_filters( 'wdrc_currency_providers', self::$providers );
	}

	/**
	 * Get the currency provider that should handle $currency_code.
	 *
	 * Filters the registered providers (self::$providers, via getProviders())
	 * down to the ones whose underlying plugin is active, in priority order.
	 * That filter is the expensive part - it's cached in
	 * self::$active_providers_cache for the rest of the request, since a
	 * provider's isActive() also checks the site's `active_plugins` option
	 * (see each provider's isActive()), which is available from the moment
	 * WordPress bootstraps and can't change mid-request.
	 *
	 * If several providers are active at once and $currency_code is known,
	 * the one that reports it as its currently applied currency wins -
	 * that's the plugin actually converting prices for this visitor.
	 * Otherwise falls back to the first active provider in priority order.
	 * That match/fallback scan is cheap (at most a handful of items) and
	 * isn't cached - it's redone per call since $currency_code varies.
	 *
	 * @param string|null $currency_code Currency code to match against, if known.
	 *
	 * @return string|null Provider class name, or null if none is active.
	 */
	public static function getActiveProvider( $currency_code = null ) {
		if ( self::$active_providers_cache === null ) {
			self::$active_providers_cache = array_values( array_filter( self::getProviders(), function ( $provider ) {
				return is_subclass_of( $provider, CurrencyProviderInterface::class ) && $provider::isActive();
			} ) );
		}

		$active_providers = self::$active_providers_cache;

		if ( ! empty( $currency_code ) ) {
			foreach ( $active_providers as $provider ) {
				if ( $provider::getCurrentCurrency() === $currency_code ) {
					return $provider;
				}
			}
		}

		return ! empty( $active_providers ) ? reset( $active_providers ) : null;
	}

	/**
	 * Get the exchange rate a supported currency-switcher plugin recorded on
	 * $order at checkout time, if any.
	 *
	 * @param \WC_Order $order Order.
	 *
	 * @return float|null
	 */
	public static function getOrderExchangeRate( $order ) {
		if ( ! $order instanceof \WC_Order ) {
			return null;
		}

		$provider = self::getActiveProvider( $order->get_currency() );

		return ! empty( $provider ) ? $provider::getOrderExchangeRate( $order ) : null;
	}

	/**
	 * Get the exchange rate for the given currency code from whichever
	 * supported currency-switcher plugin is currently active.
	 *
	 * @param string $currency_code Currency code.
	 *
	 * @return float
	 */
	public static function getExchangeRate( $currency_code ) {
		$default_rate = 1.0;
		if ( empty( $currency_code ) ) {
			return $default_rate;
		}

		$provider = self::getActiveProvider( $currency_code );
		if ( empty( $provider ) ) {
			return apply_filters( 'wdrc_currency_provider_exchange_rate', $default_rate, $currency_code, null );
		}

		$rate = $provider::getExchangeRate( $currency_code );

		return apply_filters( 'wdrc_currency_provider_exchange_rate', $rate, $currency_code, $provider );
	}
}
