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
	const BANGKOK_STYLE_HANDLE  = 'thailand-platform-bangkok-rental';
	const BANGKOK_SCRIPT_HANDLE = 'thailand-platform-bangkok-rental';
	const IMAGE_BASE    = 'assets/content/images/real-estate-thailand-atlas-v1-';
	const BANGKOK_RENTAL_IMAGE_BASE = 'assets/content/images/bangkok-rental-atlas-v1-';
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

		$route = Context::route();
		if ( is_array( $route ) && 'bangkok-apartment-rental' === ( $route['route_id'] ?? '' ) ) {
			wp_enqueue_style(
				self::BANGKOK_STYLE_HANDLE,
				plugins_url( 'assets/content/bangkok-rental.css', THAILAND_PLATFORM_FILE ),
				array( self::STYLE_HANDLE ),
				THAILAND_PLATFORM_VERSION
			);
			wp_enqueue_script(
				self::BANGKOK_SCRIPT_HANDLE,
				plugins_url( 'assets/content/bangkok-rental.js', THAILAND_PLATFORM_FILE ),
				array( self::SCRIPT_HANDLE ),
				THAILAND_PLATFORM_VERSION,
				true
			);
			wp_script_add_data( self::BANGKOK_SCRIPT_HANDLE, 'strategy', 'defer' );
		}
	}

	/**
	 * @return void
	 */
	public function preload_hero() {
		if ( ! Context::should_render() ) {
			return;
		}
		$route = Context::route();
		$route_id = is_array( $route ) ? ( $route['route_id'] ?? '' ) : '';

		$variants = array(
			array( '720.webp', '(max-width: 720px)' ),
			array( '1200.webp', '(min-width: 721px) and (max-width: 1279px)' ),
			array( '1717.webp', '(min-width: 1280px)' ),
		);

		foreach ( $variants as $variant ) {
			printf(
				'<link rel="preload" href="%1$s" as="image" type="image/webp" media="%2$s" fetchpriority="high">' . "\n",
				esc_url( self::hero_url( str_replace( '.webp', '', $variant[0] ), $route_id ) ),
				esc_attr( $variant[1] )
			);
		}
	}

	/**
	 * @param string $size Image suffix.
	 * @param string $route_id Managed content route identifier.
	 * @return string
	 */
	public static function hero_url( $size = '1717', $route_id = '' ) {
		return plugins_url( self::image_base( $route_id ) . $size . '.webp', THAILAND_PLATFORM_FILE );
	}

	/**
	 * @param string $route_id Managed content route identifier.
	 * @return string
	 */
	public static function image_base( $route_id = '' ) {
		if ( '' === $route_id ) {
			$route = Context::route();
			$route_id = is_array( $route ) ? ( $route['route_id'] ?? '' ) : '';
		}

		return 'bangkok-apartment-rental' === $route_id
			? self::BANGKOK_RENTAL_IMAGE_BASE
			: self::IMAGE_BASE;
	}

	/**
	 * @param string $route_id Managed content route identifier.
	 * @return string[]
	 */
	public static function hero_paths( $route_id = '' ) {
		$base = self::image_base( $route_id );
		return array_map(
			static function ( $size ) use ( $base ) {
				return THAILAND_PLATFORM_DIR . $base . $size . '.webp';
			},
			array( '720', '1200', '1717' )
		);
	}
}
