<?php
/**
 * Full-document renderer for compiled priority guides.
 *
 * @package Thailand_Platform
 */

namespace Thailand_Platform\Guides;

final class Renderer {
	/**
	 * @return void
	 */
	public function register() {
		add_filter( 'template_include', array( $this, 'template' ), 99 );
	}

	/**
	 * @param string $template Original theme template.
	 * @return string
	 */
	public function template( $template ) {
		$route = Context::route();
		if ( ! is_array( $route ) || ! self::ready( $route ) ) {
			return $template;
		}
		return THAILAND_PLATFORM_DIR . 'templates/guides/document.php';
	}

	/**
	 * Fail closed unless the complete generic shell and route hero are readable.
	 *
	 * @param array $route Managed route.
	 * @return bool
	 */
	public static function ready( $route ) {
		$required = array(
			THAILAND_PLATFORM_DIR . 'templates/guides/document.php',
			THAILAND_PLATFORM_DIR . 'templates/guides/partials/header.php',
			THAILAND_PLATFORM_DIR . 'templates/guides/partials/footer.php',
			THAILAND_PLATFORM_DIR . 'assets/guides/guides.css',
			THAILAND_PLATFORM_DIR . 'assets/guides/guides.js',
		);
		foreach ( $required as $path ) {
			if ( ! is_readable( $path ) || 0 === filesize( $path ) ) {
				return false;
			}
		}
		return Assets::ready( $route );
	}
}
