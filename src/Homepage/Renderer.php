<?php
/**
 * Theme-independent homepage template renderer.
 *
 * @package Thailand_Platform
 */

namespace Thailand_Platform\Homepage;

final class Renderer {
	/**
	 * Register the reversible template selection hook.
	 *
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
		$platform_template = THAILAND_PLATFORM_DIR . 'templates/front-page.php';

		if ( ! Context::should_render() ) {
			return $template;
		}

		return $platform_template;
	}

	/**
	 * Fail closed to the original theme if any immutable asset is missing.
	 *
	 * @return bool
	 */
	public static function ready() {
		$required = array(
			THAILAND_PLATFORM_DIR . 'templates/front-page.php',
			THAILAND_PLATFORM_DIR . 'resources/homepage.html',
			THAILAND_PLATFORM_DIR . 'assets/homepage/homepage.css',
			THAILAND_PLATFORM_DIR . 'assets/homepage/homepage.js',
			THAILAND_PLATFORM_DIR . 'assets/homepage/images/homepage-hero-thailand-system-v1-640.webp',
			THAILAND_PLATFORM_DIR . 'assets/homepage/images/homepage-hero-thailand-system-v1-1024.webp',
			THAILAND_PLATFORM_DIR . 'assets/homepage/images/homepage-hero-thailand-system-v1-1713.webp',
		);

		foreach ( $required as $path ) {
			if ( ! is_readable( $path ) || 0 === filesize( $path ) ) {
				return false;
			}
		}

		$markup = self::body_markup();

		return '' !== $markup
			&& 1 === substr_count( $markup, 'id="main-content"' )
			&& 1 === substr_count( $markup, 'class="site-header"' )
			&& 1 === substr_count( $markup, 'class="site-footer"' );
	}

	/**
	 * Read the reviewed body fragment from the immutable production source.
	 *
	 * @return string
	 */
	public static function body_markup() {
		static $markup = null;

		if ( null !== $markup ) {
			return $markup;
		}

		$source = file_get_contents( THAILAND_PLATFORM_DIR . 'resources/homepage.html' );

		if ( false === $source ) {
			return '';
		}

		$markup = apply_filters( 'thailand_platform_homepage_markup', trim( $source ) );
		$markup = is_string( $markup ) ? $markup : '';

		return $markup;
	}
}
