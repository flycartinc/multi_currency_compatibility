<?php

namespace WDRCS\App;

use WDR\Core\Helpers\Input;

defined( "ABSPATH" ) or die();

class Router {

	static function init() {

		if ( Input::get( 'page', '' ) != 'woo-discount-rules-addons' && Input::get( 'addon', '' ) != 'multi_currency' ) {
			return;
		}
		if ( is_admin() ) {
			add_action( 'wdr_addons_page', 'WDRCS\App\Controller\Admin\Main::managePages' );
			add_action( 'admin_enqueue_scripts', 'WDRCS\App\Controller\Admin\Main::enqueueAssets' );
			add_action( 'wp_ajax_wdrc_save_compatibility', 'WDRCS\App\Controller\Admin\Main::saveSettings' );
		}

	}
}