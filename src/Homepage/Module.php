<?php
/**
 * Homepage module composition root.
 *
 * @package Thailand_Platform
 */

namespace Thailand_Platform\Homepage;

final class Module {
	/**
	 * Register all reversible homepage presentation modules.
	 *
	 * @return void
	 */
	public function register() {
		$modules = array(
			new Context(),
			new Assets(),
			new Seo(),
			new Renderer(),
			new Settings(),
		);

		foreach ( $modules as $module ) {
			$module->register();
		}
	}
}
