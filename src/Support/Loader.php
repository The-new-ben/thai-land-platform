<?php
/**
 * Plugin hook loader.
 *
 * @package Thailand_Platform
 */

namespace Thailand_Platform\Support;

use Thailand_Platform\Content\Module as Content_Module;
use Thailand_Platform\Geography\Route as Geography_Route;
use Thailand_Platform\Health\Route as Health_Route;
use Thailand_Platform\Homepage\Module as Homepage_Module;
use Thailand_Platform\Updates\Checker as Update_Checker;

final class Loader {
	/**
	 * Prevent duplicate hook registration.
	 *
	 * @var bool
	 */
	private static $booted = false;

	/**
	 * Register modules without running remote calls or persistent writes.
	 *
	 * @return void
	 */
	public static function boot() {
		if ( self::$booted ) {
			return;
		}

		self::$booted = true;

		$health_route = new Health_Route();
		$health_route->register();

		$geography_route = new Geography_Route();
		$geography_route->register();

		$homepage = new Homepage_Module();
		$homepage->register();

		$content = new Content_Module();
		$content->register();

		Update_Checker::register();
	}
}
