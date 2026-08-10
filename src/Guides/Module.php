<?php
/**
 * Priority guides module composition root.
 *
 * @package Thailand_Platform
 */

namespace Thailand_Platform\Guides;

final class Module {
	/**
	 * @return void
	 */
	public function register() {
		$modules = array(
			new Context(),
			new Assets(),
			new HomepageNavigation(),
			new Seo(),
			new Schema(),
			new Renderer(),
			new Settings(),
		);
		foreach ( $modules as $module ) {
			$module->register();
		}
	}
}
