<?php
/**
 * Self-hosted Digital Islands client assets.
 *
 * @package Thailand_Platform
 */

namespace Thailand_Platform\DigitalIslands;

final class Assets {
	const STYLE_HANDLE  = 'thailand-platform-digital-islands';
	const SCRIPT_HANDLE = 'thailand-platform-digital-islands';

	/** @return void */
	public function register() {
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue' ), 100 );
	}

	/** @return void */
	public function enqueue() {
		if ( ! Context::should_render() ) {
			return;
		}

		wp_enqueue_style(
			self::STYLE_HANDLE,
			plugins_url( 'assets/digital-islands/digital-islands.css', THAILAND_PLATFORM_FILE ),
			array(),
			THAILAND_PLATFORM_VERSION
		);
		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			plugins_url( 'assets/digital-islands/digital-islands.js', THAILAND_PLATFORM_FILE ),
			array(),
			THAILAND_PLATFORM_VERSION,
			true
		);
		wp_script_add_data( self::SCRIPT_HANDLE, 'strategy', 'defer' );
	}
}
