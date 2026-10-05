<?php
namespace WDRCS\App\Controller\Admin;

use WDR\Core\Helpers\Input;
use WDR\Core\Helpers\Plugin;
use WDR\Core\Helpers\Util;
use WDR\Core\Helpers\WC;
use WDRCS\App\Controller\Base;

defined("ABSPATH") or die();
class Main extends Base{



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
	 * Run plugin activation scripts.
	 */
	public static function activate(){

		$slug ="multi_currency";
		$active_addons = (array) get_option( 'wdr_active_addons', [] );
		if ( ! in_array( $slug, $active_addons ) ) {
			$active_addons[] = $slug;
		}
		update_option( 'wdr_active_addons', $active_addons );
		return true;
	}


	/**
	 * Run plugin activation scripts.
	 */
	public static function deactivate()
	{
		$slug ="multi_currency";
		$active_addons = (array) get_option( 'wdr_active_addons', [] );
		if ( in_array( $slug, $active_addons ) ) {
			if ( ( $key = array_search( $slug, $active_addons ) ) !== false ) {
				unset( $active_addons[ $key ] );
			}
		}
		update_option( 'wdr_active_addons', $active_addons );
		return true;
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
		$suffix = '';
		wp_register_style(WDRCS_PLUGIN_SLUG . '-style', WDRCS_PLUGIN_URL . 'Assets/Admin/Css/wdrcs-style.css', array(), WDRCS_PLUGIN_VERSION . '&t=' . time());
		wp_enqueue_style(WDRCS_PLUGIN_SLUG . '-style');
		wp_register_script(WDRCS_PLUGIN_SLUG . '-wdrcs-admin', WDRCS_PLUGIN_URL . 'Assets/Admin/Js/wdrcs-admin' . $suffix . '.js', array('jquery'), WDRCS_PLUGIN_VERSION . '&t=' . time(), true);
		wp_enqueue_script(WDRCS_PLUGIN_SLUG . '-wdrcs-admin');

		wp_localize_script(WDRCS_PLUGIN_SLUG . '-wdrcs-admin', 'wdrc_localized_data', array(
			'ajax_url' => admin_url('admin-ajax.php'),
			'nonce'    => wp_create_nonce('wdrc_compatibility_ajax'),
			'i18n'     => array(
				'saved_error'   => __('Compatibility not saved.', 'wdr-multi-currency-compatibility'),
				'success_title' => __('Success', 'wdr-multi-currency-compatibility'),
				'error_title'   => __('Error', 'wdr-multi-currency-compatibility'),
			),
		));
	}




	/**
	 * Save settings in option.
	 *
	 * @return void
	 */
	public static function saveSettings()
	{
		$response = array(
			'success' => false,
			'data' => array(
				'message' => __('Security check failed', 'wdr-multi-currency-compatibility'),
			),
		);
		if (!WC::hasAdminPrivilege() || !wp_verify_nonce(Input::get('wdrc_nonce', ''), 'wdrc_compatibility_ajax')) {
			wp_send_json($response);
		}
		$compatibility = Input::get('wdrc_compatibility', [], 'post');
		$option_key = Input::get('option_key', '', 'post');
		$option_key = preg_replace('/[^A-Za-z\d_\-]/', '', $option_key);
		if (empty($option_key)) {
			$response['data']['message'] = __('Compatibility not saved.', 'wdr-multi-currency-compatibility');
			wp_send_json($response);
		}
		$compatibility = !empty($compatibility) ? array_map('absint', $compatibility) : $compatibility;
		update_option($option_key, $compatibility);
		$response['success'] = true;
		$response['data']['message'] = __('Compatibility saved successfully.', 'wdr-multi-currency-compatibility');
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