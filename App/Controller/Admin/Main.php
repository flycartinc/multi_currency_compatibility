<?php
namespace WDRCS\App\Controller\Admin;

use WDR\Core\Helpers\Input;
use WDR\Core\Helpers\Plugin;
use WDR\Core\Helpers\Util;
use WDR\Core\Helpers\WC;

defined("ABSPATH") or die();
class Main {

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
	 * Main menu page render display.
	 *
	 * @param string $addon Contains slug name like multi_currency.
	 * @return void
	 */
	public static function managePages($addon = '')
	{
		if ($addon != 'multi_currency') return;
		$params = array(
			'fields' => self::getList(),
			'option_key' => self::$option_key,
		);
		$path = WDRCS_PLUGIN_PATH . 'App/Views/main.php';
		Util::renderTemplate($path, $params);
	}

	/**
	 * Enqueue assets.
	 *
	 * @return void
	 */
	public static function enqueueAssets()
	{
		if ( Input::get( 'page', '' ) != 'woo-discount-rules-addons' && Input::get( 'addon', '' ) != 'multi_currency' ) {
			return;
		}
		/*$suffix = '.min';
		if (defined('SCRIPT_DEBUG')) {
			$suffix = SCRIPT_DEBUG ? '' : '.min';
		}*/
		$suffix = '';
		wp_register_style(WDR_PLUGIN_SLUG . '-alertify', WDR_PLUGIN_URL . 'assets/Admin/Css/alertify' . $suffix . '.css', array(), WDR_PLUGIN_VERSION . '&t=' . time());
		wp_enqueue_style(WDR_PLUGIN_SLUG . '-alertify');
		wp_register_script(WDR_PLUGIN_SLUG . '-alertify', WDR_PLUGIN_URL . 'assets/Admin/Js/alertify' . $suffix . '.js', array('jquery'), WDR_PLUGIN_VERSION . '&t=' . time());
		wp_enqueue_script(WDR_PLUGIN_SLUG . '-alertify');
		wp_register_style(WDRCS_PLUGIN_SLUG . '-style', WDRCS_PLUGIN_URL . 'Assets/Admin/Css/wdrcs-style.css', array(), WDRCS_PLUGIN_VERSION . '&t=' . time());
		wp_enqueue_style(WDRCS_PLUGIN_SLUG . '-style');
		wp_register_script(WDR_PLUGIN_SLUG . '-wdrcs-admin', WDRCS_PLUGIN_URL . 'Assets/Admin/Js/wdrcs-admin' . $suffix . '.js', array('jquery', WDR_PLUGIN_SLUG . '-alertify'), WDRCS_PLUGIN_VERSION . '&t=' . time());
		wp_enqueue_script(WDR_PLUGIN_SLUG . '-wdrcs-admin');

		wp_localize_script(WDR_PLUGIN_SLUG . '-wdrcs-admin', 'wdrc_localized_data', array(
			'ajax_url' => admin_url('admin-ajax.php'),
		));
	}

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
				if (Plugin::isActive($file)) {
					$is_active = true;
					break;
				}
			}

			if (!$is_active) {
				continue;
			}

			$compatibility['is_active'] = true;
			$compatibility['is_enabled'] = \WDR\Core\Modules\Addons\MultiCurrency\Main::isCompatibilityEnabled($key);
			$list[$key] = $compatibility;
		}
		return $list;
	}


	/**
	 * Save settings in option.
	 *
	 * @return void
	 */
	public static function saveSettings()
	{
		print_r($_POST);
		$response = array(
			'success' => false,
			'data' => array(
				'message' => __('Security check failed', 'woo-discount-rules'),
			),
		);
		if (!WC::hasAdminPrivilege() || !wp_verify_nonce(Input::get('wdrc_nonce', ''), 'wdrc_compatibility_ajax')) {
			wp_send_json($response);
		}
		$compatibility = Input::get('wdrc_compatibility', [], 'post');
		$option_key = Input::get('option_key', '', 'post');
		$option_key = preg_replace('/[^A-Za-z\d_\-]/', '', $option_key);
		if (empty($option_key)) {
			$response['data']['message'] = __('Compatibility not saved.', 'woo-discount-rules');
			wp_send_json($response);
		}
		$compatibility = !empty($compatibility) ? array_map('absint', $compatibility) : $compatibility;
		update_option($option_key, $compatibility);
		$response['success'] = true;
		$response['data']['message'] = __('Compatibility saved successfully.', 'woo-discount-rules');
		wp_send_json($response);
	}

	/**
	 * Check compatibility enabled or not in option.
	 *
	 * @param string $key Multi-currency compatibility name.
	 * @param string $default Default multi-currency name value.
	 * @return mixed|string
	 */
	public static function isCompatibilityEnabled(string $key, string $default = '')
	{
		$options = get_option(self::$option_key, array());
		return (isset($options[$key])) ? $options[$key] : $default;
	}
}