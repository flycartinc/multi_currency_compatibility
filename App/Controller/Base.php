<?php
namespace WDRCS\App\Controller;
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
			'handler'     => '\WDR\Core\Modules\Addons\MultiCurrency\Compatibility\VillaTheme',
		],
		'realmag_currency_switcher'    => [
			'name'        => 'Realmag currency switcher',
			'description' => '',
			'author'      => 'Realmag',
			'file'        => [ 'woocommerce-currency-switcher/index.php' ],
			'handler'     => '\WDR\Core\Modules\Addons\MultiCurrency\Compatibility\RealMag',
		],
		'wpml_currency_switcher'       => [
			'name'        => 'WPML currency switcher',
			'description' => '',
			'author'      => 'WPML',
			'file'        => [ 'sitepress-multilingual-cms/sitepress.php' ],
			'handler'     => '\WDR\Core\Modules\Addons\MultiCurrency\Compatibility\WPML',
		],
		'wpwham_currency_switcher'     => [
			'name'        => 'WPWham currency switcher',
			'description' => '',
			'author'      => 'WPWham',
			'file'        => [ 'currency-switcher-woocommerce/currency-switcher-woocommerce.php' ],
			'handler'     => '\WDR\Core\Modules\Addons\MultiCurrency\Compatibility\WPWham',
		],
	];

	/**
	 * Get list of active compatibility.
	 *
	 * @return array
	 */
	public static function getList(): array
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
}