<?php
/**
 * Plugin Name:         Multi-currency
 * Plugin URI:          https://www.flycart.org
 * Description:         Helpful to provide compatibility for Multi-currency plugins.
 * Version:             1.0.0
 * Requires at least:   5.3
 * Requires PHP:        5.6
 * Author:              Flycart
 * Author URI:          https://www.flycart.org
 * Slug:                wdr-multi-currency-compatibility
 * Text Domain:         wdr-multi-currency-compatibility
 * Domain path:         /i18n/languages/
 * License:             GPL v3 or later
 * License URI:         https://www.gnu.org/licenses/gpl-3.0.html
 * Contributors:        Ilaiyaraja
 * WC requires at least: 4.3
 * WC tested up to:     8.0
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
 * Check discount rules plugin is latest.
 */
if ( ! function_exists( 'isWDRLatestVersion' ) ) {
	function isWDRLatestVersion() {
		$db_version = get_option( 'wdr_version', '' );
		if ( ! empty( $db_version ) ) {
			return ( version_compare( $db_version, '3.0.0', '>=' ) );
		}

		return false;
	}
}

if ( !isWDRLatestVersion() ) {
	return;
}

/**
 * Plugin constants.
 */
defined( 'WDRCS_PLUGIN_NAME' ) or define( 'WDRCS_PLUGIN_NAME', 'Multi currency compatibility' );
defined( 'WDRCS_PLUGIN_VERSION' ) or define( 'WDRCS_PLUGIN_VERSION', '1.0.0' );
defined( 'WDRCS_PLUGIN_SLUG' ) or define( 'WDRCS_PLUGIN_SLUG', 'wdr-multi-currency-compatibility' );
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
\WDRCS\App\Router::init();