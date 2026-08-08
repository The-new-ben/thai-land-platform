<?php
/**
 * Managed content assets.
 *
 * @package Thailand_Platform
 */

namespace Thailand_Platform\Content;

final class Assets {
	const STYLE_HANDLE  = 'thailand-platform-content';
	const SCRIPT_HANDLE = 'thailand-platform-content';
	const IMAGE_BASE    = 'assets/content/images/real-estate-thailand-atlas-v1-';
	const SOCIAL_IMAGE_WIDTH  = '1717';
	const SOCIAL_IMAGE_HEIGHT = '916';

	/**
	 * @return void
	 */
	public function register() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ) );
		add_action( 'wp_head', array( $this, 'preload_hero' ), 2 );
	}

	/**
	 * @return void
	 */
	public function enqueue() {
		if ( ! Context::should_render() ) {
			return;
		}

		wp_enqueue_style(
			self::STYLE_HANDLE,
			plugins_url( 'assets/content/content.css', THAILAND_PLATFORM_FILE ),
			array(),
			THAILAND_PLATFORM_VERSION
		);
		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			plugins_url( 'assets/content/content.js', THAILAND_PLATFORM_FILE ),
			array(),
			THAILAND_PLATFORM_VERSION,
			true
		);
		wp_script_add_data( self::SCRIPT_HANDLE, 'strategy', 'defer' );
	}

	/**
	 * @return void
	 */
	public function preload_hero() {
		if ( ! Context::should_render() ) {
			return;
		}

		$variants = array(
			array( '720.webp', '(max-width: 720px)' ),
			array( '1200.webp', '(min-width: 721px) and (max-width: 1279px)' ),
			array( '1717.webp', '(min-width: 1280px)' ),
		);

		foreach ( $variants as $variant ) {
			printf(
				'<link rel="preload" href="%1$s" as="image" type="image/webp" media="%2$s" fetchpriority="high">' . "\n",
				esc_url( plugins_url( self::IMAGE_BASE . $variant[0], THAILAND_PLATFORM_FILE ) ),
				esc_attr( $variant[1] )
			);
		}
	}

	/**
	 * @param string $size Image suffix.
	 * @return string
	 */
	public static function hero_url( $size = '1717' ) {
		return plugins_url( self::IMAGE_BASE . $size . '.webp', THAILAND_PLATFORM_FILE );
	}
}
