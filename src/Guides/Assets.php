<?php
/**
 * Route-specific priority guide assets.
 *
 * @package Thailand_Platform
 */

namespace Thailand_Platform\Guides;

final class Assets {
	const STYLE_HANDLE        = 'thailand-platform-guides';
	const SCRIPT_HANDLE       = 'thailand-platform-guides';
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
			plugins_url( 'assets/guides/guides.css', THAILAND_PLATFORM_FILE ),
			array(),
			THAILAND_PLATFORM_VERSION
		);
		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			plugins_url( 'assets/guides/guides.js', THAILAND_PLATFORM_FILE ),
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
		$route = Context::route();
		if ( ! is_array( $route ) || ! Context::should_render() ) {
			return;
		}

		$variants = array(
			array( 720, '(max-width: 720px)' ),
			array( 1200, '(min-width: 721px) and (max-width: 1279px)' ),
			array( 1717, '(min-width: 1280px)' ),
		);
		foreach ( $variants as $variant ) {
			printf(
				'<link rel="preload" href="%1$s" as="image" type="image/webp" media="%2$s" fetchpriority="high">' . "\n",
				esc_url( self::hero_url( $route, $variant[0] ) ),
				esc_attr( $variant[1] )
			);
		}
	}

	/**
	 * @param array $route Managed route.
	 * @return string
	 */
	public static function asset_key( $route ) {
		$key      = isset( $route['asset_key'] ) && is_string( $route['asset_key'] ) ? $route['asset_key'] : '';
		$contract = Repository::asset_contract();
		$allowed  = $contract['allowed_asset_keys'] ?? array();
		if ( ! preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $key ) || ! in_array( $key, $allowed, true ) ) {
			return '';
		}
		return $key;
	}

	/**
	 * @param array $route Managed route.
	 * @return string[]
	 */
	public static function hero_paths( $route ) {
		$key      = self::asset_key( $route );
		$contract = Repository::asset_contract();
		$widths   = $contract['widths'] ?? array();
		if ( '' === $key || array( 720, 1200, 1717 ) !== $widths ) {
			return array();
		}

		return array_map(
			static function ( $width ) use ( $key ) {
				return THAILAND_PLATFORM_DIR . 'assets/guides/images/' . $key . '-' . $width . '.webp';
			},
			$widths
		);
	}

	/**
	 * @param array $route Managed route.
	 * @return bool
	 */
	public static function ready( $route ) {
		$paths = self::hero_paths( $route );
		if ( 3 !== count( $paths ) ) {
			return false;
		}
		foreach ( $paths as $path ) {
			if ( ! is_readable( $path ) || 0 === filesize( $path ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * @param array $route Managed route.
	 * @param int   $width Required width.
	 * @return string
	 */
	public static function hero_url( $route, $width = 1717 ) {
		$key      = self::asset_key( $route );
		$contract = Repository::asset_contract();
		$widths   = $contract['widths'] ?? array();
		$width    = absint( $width );
		if ( '' === $key || ! in_array( $width, $widths, true ) ) {
			return '';
		}
		return plugins_url(
			'assets/guides/images/' . $key . '-' . $width . '.webp',
			THAILAND_PLATFORM_FILE
		);
	}
}
