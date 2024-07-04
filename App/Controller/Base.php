<?php
namespace WDRCS\App\Controller;

defined("ABSPATH") or die();
class Base {

	/**
	 * Option key for save and retrieve.
	 *
	 * @var string
	 */
	public static $option_key = 'wdr_plugin_multi_currency';

	/**
	 * Multi-currency data list.
	 *
	 * @var \string[][]
	 */
	private static $multi_currency_compatibility = [
		'villatheme_currency_switcher' => [
			'name'        => 'VillaTheme currency switcher',
			'description' => '',
			'author'      => 'VillaTheme',
			'file'        => [
				'woo-multi-currency/woo-multi-currency.php',
				'woocommerce-multi-currency/woocommerce-multi-currency.php',
			],
			'handler'     => '\WDRCS\App\Compatibility\VillaTheme',
		],
		'realmag_currency_switcher'    => [
			'name'        => 'Realmag currency switcher',
			'description' => '',
			'author'      => 'Realmag',
			'file'        => [ 'woocommerce-currency-switcher/index.php' ],
			'handler'     => '\WDRCS\App\Compatibility\RealMag',
		],
		'wpml_currency_switcher'       => [
			'name'        => 'WPML currency switcher',
			'description' => '',
			'author'      => 'WPML',
			'file'        => [ 'sitepress-multilingual-cms/sitepress.php' ],
			'handler'     => '\WDRCS\App\Compatibility\WPML',
		],
		'wpwham_currency_switcher'     => [
			'name'        => 'WPWham currency switcher',
			'description' => '',
			'author'      => 'WPWham',
			'file'        => [ 'currency-switcher-woocommerce/currency-switcher-woocommerce.php' ],
			'handler'     => '\WDRCS\App\Compatibility\WPWham',
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
			if (empty($compatibility['file'])) {
				continue;
			}
			$is_active = false;
			foreach ($compatibility['file'] as $file) {
				if (\WDR\Core\Helpers\Plugin::isActive($file)) {
					$is_active = true;
					break;
				}
			}

			if (!$is_active) {
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
		if ( isset( $hooks['woocommerce_before_calculate_totals'] ) ) {
			unset( $hooks['woocommerce_before_calculate_totals'] );
		}

		return $hooks;
	}

}