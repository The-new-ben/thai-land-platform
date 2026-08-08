<?php
/**
 * Homepage asset loading.
 *
 * @package Thailand_Platform
 */

namespace Thailand_Platform\Homepage;

final class Assets {
	/**
	 * Register asset hooks.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ), 100 );
		add_action( 'wp_head', array( $this, 'preload_hero' ), 2 );
	}

	/**
	 * Load only the approved dependency-free homepage bundle.
	 *
	 * @return void
	 */
	public function enqueue() {
		if ( ! Context::should_render() ) {
			return;
		}

		wp_enqueue_style(
			'thailand-platform-homepage',
			plugins_url( 'assets/homepage/homepage.css', THAILAND_PLATFORM_FILE ),
			array(),
			THAILAND_PLATFORM_VERSION
		);

		wp_enqueue_script(
			'thailand-platform-homepage',
			plugins_url( 'assets/homepage/homepage.js', THAILAND_PLATFORM_FILE ),
			array(),
			THAILAND_PLATFORM_VERSION,
			true
		);

		wp_script_add_data( 'thailand-platform-homepage', 'strategy', 'defer' );

		if ( Context::is_authorized_canary() ) {
			return;
		}

		wp_enqueue_script(
			'thailand-platform-google-analytics',
			'https://www.googletagmanager.com/gtag/js?id=G-R3THSJW0TT',
			array(),
			null,
			false
		);
		wp_script_add_data( 'thailand-platform-google-analytics', 'strategy', 'async' );
		wp_add_inline_script(
			'thailand-platform-google-analytics',
			"window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','G-R3THSJW0TT');",
			'after'
		);
	}

	/**
	 * Preload only the LCP illustration selected by the responsive CSS.
	 *
	 * @return void
	 */
	public function preload_hero() {
		if ( ! Context::should_render() ) {
			return;
		}

		$url_640  = plugins_url( 'assets/homepage/images/homepage-hero-thailand-system-v1-640.webp', THAILAND_PLATFORM_FILE );
		$url_1024 = plugins_url( 'assets/homepage/images/homepage-hero-thailand-system-v1-1024.webp', THAILAND_PLATFORM_FILE );
		$url_1713 = plugins_url( 'assets/homepage/images/homepage-hero-thailand-system-v1-1713.webp', THAILAND_PLATFORM_FILE );
		$preloads = array(
			array( $url_640, '(max-width: 640px)' ),
			array( $url_1024, '(min-width: 641px) and (max-width: 1279px)' ),
			array( $url_1713, '(min-width: 1280px)' ),
		);

		foreach ( $preloads as $preload ) {
			printf(
				'<link rel="preload" href="%1$s" as="image" type="image/webp" media="%2$s" fetchpriority="high">' . "\n",
				esc_url( $preload[0] ),
				esc_attr( $preload[1] )
			);
		}
	}
}
