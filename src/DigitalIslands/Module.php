<?php
/**
 * Isolated Digital Islands composition root.
 *
 * @package Thailand_Platform
 */

namespace Thailand_Platform\DigitalIslands;

final class Module {
	/** @return void */
	public function register() {
		$modules = array(
			new Context(),
			new Privacy(),
			new Seo(),
			new Schema(),
			new Assets(),
			new Renderer(),
			new RestController(),
			new HomepageNavigation(),
			new Settings(),
		);
		foreach ( $modules as $module ) {
			$module->register();
		}
	}
}
