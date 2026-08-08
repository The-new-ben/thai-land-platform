<?php
/**
 * Full-document content renderer.
 *
 * @package Thailand_Platform
 */

namespace Thailand_Platform\Content;

final class Renderer {
	/**
	 * @var array
	 */
	private static $bodies = array();

	/**
	 * @return void
	 */
	public function register() {
		add_filter( 'template_include', array( $this, 'template' ), 98 );
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

		return THAILAND_PLATFORM_DIR . 'templates/content-document.php';
	}

	/**
	 * @param array $route Current route.
	 * @return bool
	 */
	public static function ready( $route ) {
		$required = array(
			THAILAND_PLATFORM_DIR . 'templates/content-document.php',
			THAILAND_PLATFORM_DIR . 'templates/partials/content-header.php',
			THAILAND_PLATFORM_DIR . 'templates/partials/content-footer.php',
			THAILAND_PLATFORM_DIR . 'assets/content/content.css',
			THAILAND_PLATFORM_DIR . 'assets/content/content.js',
			THAILAND_PLATFORM_DIR . 'assets/content/images/real-estate-thailand-atlas-v1-720.webp',
			THAILAND_PLATFORM_DIR . 'assets/content/images/real-estate-thailand-atlas-v1-1200.webp',
			THAILAND_PLATFORM_DIR . 'assets/content/images/real-estate-thailand-atlas-v1-1717.webp',
		);

		foreach ( $required as $path ) {
			if ( ! is_readable( $path ) || 0 === filesize( $path ) ) {
				return false;
			}
		}

		$body = self::post_body( $route );
		return '' !== trim( $body ) && ! preg_match( '/<(?:h1|main)\b/i', $body );
	}

	/**
	 * Preserve the stored WordPress body and run normal content filters once.
	 *
	 * @param array $route Current route.
	 * @return string
	 */
	public static function post_body( $route ) {
		$route_id = $route['route_id'] ?? '';
		if ( isset( self::$bodies[ $route_id ] ) ) {
			return self::$bodies[ $route_id ];
		}

		$post_id = absint( $route['wordpress']['post_id'] ?? 0 );
		$body    = get_post_field( 'post_content', $post_id );
		if ( ! is_string( $body ) ) {
			self::$bodies[ $route_id ] = '';
			return '';
		}

		$filtered = (string) apply_filters( 'the_content', $body );
		self::$bodies[ $route_id ] = str_ireplace(
			array(
				"\xE2\x80\x93",
				"\xE2\x80\x94",
				'&ndash;',
				'&mdash;',
				'&#8211;',
				'&#8212;',
				'&#x2013;',
				'&#x2014;',
			),
			'-',
			$filtered
		);
		return self::$bodies[ $route_id ];
	}

	/**
	 * Reset only for dependency-free tests.
	 *
	 * @return void
	 */
	public static function reset_for_tests() {
		self::$bodies = array();
	}
}
