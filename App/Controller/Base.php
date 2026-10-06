<?php
namespace WDRCS\App\Controller;

use WDRCS\App\Currency\Providers\AeliaCurrencyProvider;
use WDRCS\App\Currency\Providers\WcmlProvider;
use WDRCS\App\Currency\Providers\WoocsProvider;
use WDRCS\App\Currency\Providers\WooMultiCurrencyProvider;
use WDRCS\App\Currency\Providers\WPWhamProvider;
use WDRCS\App\Currency\Providers\YithMultiCurrencyProvider;

defined("ABSPATH") or die();
class Base {

	/**
	 * Option key for save and retrieve.
	 *
	 * @var string
	 */
	public static $option_key = 'wdr_plugin_multi_currency';

	/**
	 * Multi-currency data list, keyed by the same slugs this option has always been saved
	 * under (so existing installs keep their saved enable/disable choices with no migration).
	 * "Is this provider active" is now resolved by the matching App\Currency\Providers\*
	 * class instead of a raw plugin-file list.
	 *
	 * @var \string[][]
	 */
	private static $multi_currency_compatibility = [
		'villatheme_currency_switcher' => [
			'name'        => 'VillaTheme currency switcher',
			'description' => '',
			'author'      => 'VillaTheme',
			'provider'    => WooMultiCurrencyProvider::class,
			'handler'     => '\WDRCS\App\Compatibility\VillaTheme',
		],
		'realmag_currency_switcher'    => [
			'name'        => 'Realmag currency switcher',
			'description' => '',
			'author'      => 'Realmag',
			'provider'    => WoocsProvider::class,
			'handler'     => '\WDRCS\App\Compatibility\RealMag',
		],
		'wpml_currency_switcher'       => [
			'name'        => 'WPML currency switcher',
			'description' => '',
			'author'      => 'WPML',
			'provider'    => WcmlProvider::class,
			'handler'     => '\WDRCS\App\Compatibility\WPML',
		],
		'wpwham_currency_switcher'     => [
			'name'        => 'WPWham currency switcher',
			'description' => '',
			'author'      => 'WPWham',
			'provider'    => WPWhamProvider::class,
			'handler'     => '\WDRCS\App\Compatibility\WPWham',
		],
		'aelia_currency_switcher'      => [
			'name'        => 'Aelia currency switcher',
			'description' => '',
			'author'      => 'Aelia',
			'provider'    => AeliaCurrencyProvider::class,
			'handler'     => '\WDRCS\App\Compatibility\Aelia',
		],
		'yith_currency_switcher'       => [
			'name'        => 'YITH Multi Currency Switcher',
			'description' => '',
			'author'      => 'YITH',
			'provider'    => YithMultiCurrencyProvider::class,
			'handler'     => '\WDRCS\App\Compatibility\YITH',
		],
	];

	/**
	 * Get list of active compatibility.
	 *
	 * @return array
	 */
	public static function getList()
	{
		$compatibilities = self::$multi_currency_compatibility;
		$list = [];
		foreach ($compatibilities as $key => $compatibility) {
			if (empty($compatibility['provider']) || !$compatibility['provider']::isActive()) {
				continue;
			}

			$compatibility['is_active'] = true;
			$compatibility['is_enabled'] = self::isCompatibilityEnabled($key);
			$list[$key] = $compatibility;
		}
		return $list;
	}

	/**
	 * Check compatibility enabled or not in option.
	 *
	 * @param string $key Multi-currency compatibility name.
	 * @param string $default Default multi-currency name value.
	 *
	 * @return mixed|string
	 */
	public static function isCompatibilityEnabled( string $key, string $default = '' ) {
		$options = get_option( self::$option_key, array() );

		return ( isset( $options[ $key ] ) ) ? $options[ $key ] : $default;
	}

	/**
	 * @param array $hooks
	 *
	 * @return array
	 */
	static function removeSuppressedHooks( $hooks ) {
		if ( empty( $hooks ) || ! is_array( $hooks ) ) {
			return $hooks;
		}
		if ( isset( $hooks['woocommerce_product_get_regular_price'] ) ) {
			unset( $hooks['woocommerce_product_get_regular_price'] );
		}
		if ( isset( $hooks['woocommerce_get_price_html'] ) ) {
			unset( $hooks['woocommerce_get_price_html'] );
		}

		return $hooks;
	}

}
