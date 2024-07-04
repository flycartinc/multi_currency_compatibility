<?php

namespace WDRCS\App;

use WDR\Core\Helpers\Input;

defined( "ABSPATH" ) or die();

class Router {

    /**
     * Addon Hooks.
     *
     * @return void
     */
	public static function init() {
        \WDRCS\App\Controller\Site\Main::run();
        if (is_admin()) {
	        register_activation_hook(WDRCS_PLUGIN_FILE, ['WDRCS\App\Controller\Admin\Main::activate']);
	        register_deactivation_hook(WDRCS_PLUGIN_FILE, ['WDRCS\App\Controller\Admin\Main::deactivate']);
			add_action( 'wp_ajax_wdrc_save_compatibility', 'WDRCS\App\Controller\Admin\Main::saveSettings' );
		}
        if ( Input::get( 'page', '' ) != 'woo-discount-rules-addons' && Input::get( 'addon', '' ) != 'multi_currency' ) {
			return;
		}
		if ( is_admin() ) {
			add_action( 'wdr_addons_page', 'WDRCS\App\Controller\Admin\Main::managePages' );
			add_action( 'admin_enqueue_scripts', 'WDRCS\App\Controller\Admin\Main::enqueueAssets' );
		}
	}
}