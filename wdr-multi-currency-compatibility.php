<?php
/**
 * Plugin Name:         Discount rules : Multi-currency compatibility
 * Plugin URI:          https://www.flycart.org
 * Description:         Helpful to provide compatibility for Multi-currency plugins.
 * Version:             2.0.0
 * Requires at least:   6.0
 * Requires PHP:        7.4
 * Author:              Flycart
 * Author URI:          https://www.flycart.org
 * Slug:                wdr-multi-currency-compatibility
 * Text Domain:         wdr-multi-currency-compatibility
 * Domain path:         /i18n/languages/
 * License:             GPL v3 or later
 * License URI:         https://www.gnu.org/licenses/gpl-3.0.html
 * Contributors:        Ilaiyaraja
 * WC requires at least: 7.0
 * WC tested up to:     10.2
 */

defined( 'ABSPATH' ) or die();

/**
 * Check woocommerce and Discount rules active or not.
 */
if ( ! function_exists( 'isWooAndWDRActive' ) ) {
	function isWooAndWDRActive() {
		$active_plugins = apply_filters( 'active_plugins', get_option( 'active_plugins', array() ) );
		if ( is_multisite() ) {
			$active_plugins = array_merge( $active_plugins, get_site_option( 'active_sitewide_plugins', array() ) );
		}

		return (in_array( 'woocommerce/woocommerce.php', $active_plugins, false ) || array_key_exists( 'woocommerce/woocommerce.php', $active_plugins )
		&& (in_array( 'woo-discount-rules-pro/woo-discount-rules-pro.php', $active_plugins, false ) || in_array( 'woo-discount-rules/woo-discount-rules.php', $active_plugins, false )));
	}
}
if (! isWooAndWDRActive()) {
	return;
}

if ( ! class_exists( '\WDR\Core\Helpers\Plugin' ) && file_exists( WP_PLUGIN_DIR . '/woo-discount-rules/vendor/autoload.php' ) ) {
	require_once WP_PLUGIN_DIR . '/woo-discount-rules/vendor/autoload.php';
} elseif ( file_exists( WP_PLUGIN_DIR . '/woo-discount-rules-pro/vendor/autoload.php' ) ) {
	require_once WP_PLUGIN_DIR . '/woo-discount-rules-pro/vendor/autoload.php';
}

if ( ! class_exists( '\WDR\Core\Helpers\Plugin' ) ) {
	return;
}

/**
 * This addon only supports Discount Rules running in v3 (Core) mode. WDR's v2 engine has its own,
 * separate, built-in currency-switcher compatibility (see
 * v2/core/v2/App/Compatibility/*CurrencySwitcher*.php and MultiCurrencyByWPML.php/
 * MultiCurrencyByTivNet.php in woo-discount-rules) and never fires any of the
 * wdr_discount_get_product_price / wdr_discount_coupon_data / wdr_discounted_value_format /
 * wdr_apply_coupon_discount_based_on_filters / wdr_suppress_allowed_hooks filters this addon
 * hooks - so there is nothing for this addon to do under v2, and it must stay inactive there.
 *
 * Checked on the 'init' hook rather than here at top level: WordPress loads each active plugin's
 * main file in alphabetical order of its folder name, and "wdr-multi-currency-compatibility"
 * sorts before "woo-discount-rules" - so WDR_PLUGIN_VERSION / is_wdr_load_v2() are not guaranteed
 * to exist yet if checked immediately here.
 *
 * @return bool
 */
if ( ! function_exists( 'wdrcsIsWdrV3Active' ) ) {
	function wdrcsIsWdrV3Active() {
		if ( function_exists( 'is_wdr_load_v2' ) && is_wdr_load_v2() ) {
			return false;
		}
		if ( ! defined( 'WDR_PLUGIN_VERSION' ) ) {
			return false;
		}
		// Strip any pre-release/build suffix (e.g. "3.0.0-RC2", "3.0.0-beta1") before comparing -
		// version_compare() otherwise ranks a pre-release below its plain release.
		$version = preg_replace( '/[-+].*$/', '', WDR_PLUGIN_VERSION );

		return version_compare( $version, '3.0.0', '>=' );
	}
}

/**
 * Plugin constants.
 */
defined( 'WDRCS_PLUGIN_NAME' ) or define( 'WDRCS_PLUGIN_NAME', 'Multi-currency' );
defined( 'WDRCS_PLUGIN_VERSION' ) or define( 'WDRCS_PLUGIN_VERSION', '2.0.0' );
defined( 'WDRCS_PLUGIN_SLUG' ) or define( 'WDRCS_PLUGIN_SLUG', 'wdr-multi-currency-compatibility' );
defined('WDRCS_PLUGIN_FILE') || define('WDRCS_PLUGIN_FILE', __FILE__);
defined('WDRCS_PLUGIN_PATH') || define('WDRCS_PLUGIN_PATH', plugin_dir_path(__FILE__));
defined('WDRCS_PLUGIN_URL') || define('WDRCS_PLUGIN_URL', plugin_dir_url(__FILE__));
if ( ! file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	return;
}
require __DIR__ . '/vendor/autoload.php';

if(! class_exists(\WDRCS\App\Router::class)) return;

$myUpdateChecker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
    'https://github.com/flycartinc/multi_currency_compatibility',
    __FILE__,
    'wdr-multi-currency-compatibility'
);
$myUpdateChecker->getVcsApi()->enableReleaseAssets();

if (! method_exists(\WDRCS\App\Router::class, 'init')) return;

register_activation_hook(WDRCS_PLUGIN_FILE, 'WDRCS\App\Controller\Admin\Main::activate');
register_deactivation_hook(WDRCS_PLUGIN_FILE, 'WDRCS\App\Controller\Admin\Main::deactivate');

add_action( 'init', function () {
	if ( ! wdrcsIsWdrV3Active() ) {
		return;
	}
	
	\WDRCS\App\Router::init();
} );
