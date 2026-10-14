<?php

namespace WDRCS\App\Currency;

defined( 'ABSPATH' ) || exit;

/**
 * Contract every currency-switcher plugin provider must implement.
 *
 * To add support for a new currency-switcher plugin, create one class in
 * App/Currency/Providers implementing this interface and register it in
 * CurrencyProviderManager::$providers - no other file needs to change.
 */
interface CurrencyProviderInterface {

	/**
	 * Unique slug identifying this provider.
	 *
	 * @return string
	 */
	public static function getSlug();

	/**
	 * Whether the currency-switcher plugin this provider targets is active/loaded.
	 *
	 * @return bool
	 */
	public static function isActive();

	/**
	 * Get the currency code this provider currently has applied for the
	 * visitor/session, used to tell which plugin is actually in control
	 * when more than one currency-switcher plugin is active at once.
	 *
	 * @return string
	 */
	public static function getCurrentCurrency();

	/**
	 * Get the exchange rate for the given currency code relative to the shop's
	 * default/base currency, as reported by the currency-switcher plugin.
	 *
	 * @param string $currency_code Currency code (e.g. 'EUR').
	 *
	 * @return float
	 */
	public static function getExchangeRate( $currency_code );

	/**
	 * Get the exchange rate this provider recorded on $order at the time it
	 * was placed, if the plugin persists one as order meta. This is the most
	 * reliable source for historical orders: it doesn't depend on the plugin's
	 * live runtime state, which may not be available outside the customer's
	 * checkout session (e.g. admin order screens, REST API, async contexts).
	 *
	 * @param \WC_Order $order Order.
	 *
	 * @return float|null Null if this provider doesn't store a per-order rate,
	 *                     or none is found on this order.
	 */
	public static function getOrderExchangeRate( $order );
}
