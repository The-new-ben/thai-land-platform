<?php
/**
 * Managed content module composition root.
 *
 * @package Thailand_Platform
 */

namespace Thailand_Platform\Content;

final class Module {
	/**
	 * @return void
	 */
	public function register() {
		$modules = array(
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
