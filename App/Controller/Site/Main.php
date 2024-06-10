<?php

namespace WDRCS\App\Controller\Site;

use WDRCS\App\Controller\Base;
use WDR\Core\Helpers\Util;

defined("ABSPATH") or die();
class Main extends Base{
	/**
	 * Executes the active compatibility.
	 *
	 * @return void
	 */
	public static function run() {
		$list = self::getList();
		if ( empty( $list ) ) {
			return;
		}
		foreach ( $list as $compatibility ) {
			if ( ! $compatibility['is_enabled'] || ! class_exists( $compatibility['handler'] ) ) {
				continue;
			}
			$class = new $compatibility['handler'];
			if ( Util::isMethodExists( $class, 'run' ) ) {
				$class->run();
			}
		}
	}
}