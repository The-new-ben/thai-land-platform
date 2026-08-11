<?php
/**
 * Self-hosted Digital Islands client assets.
 *
 * @package Thailand_Platform
 */

namespace Thailand_Platform\DigitalIslands;

final class Assets {
	const MAPLIBRE_STYLE_HANDLE  = 'thailand-platform-maplibre-gl';
	const PMTILES_SCRIPT_HANDLE  = 'thailand-platform-pmtiles';
	const MAPLIBRE_SCRIPT_HANDLE = 'thailand-platform-maplibre-gl';
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

		try {
			$config = RendererAssets::runtime_config();
			$json   = wp_json_encode(
				$config,
				JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
			);
			if ( ! is_string( $json ) || '' === $json ) {
				return;
			}
		} catch ( \Throwable $exception ) {
			unset( $exception );
			return;
		}

		wp_enqueue_style(
			self::MAPLIBRE_STYLE_HANDLE,
			plugins_url( RendererAssets::MAPLIBRE_STYLE_PATH, THAILAND_PLATFORM_FILE ),
			array(),
			THAILAND_PLATFORM_VERSION
		);
		wp_enqueue_style(
			self::STYLE_HANDLE,
			plugins_url( 'assets/digital-islands/digital-islands.css', THAILAND_PLATFORM_FILE ),
			array( self::MAPLIBRE_STYLE_HANDLE ),
			THAILAND_PLATFORM_VERSION
		);
		wp_enqueue_script(
			self::PMTILES_SCRIPT_HANDLE,
			plugins_url( RendererAssets::PMTILES_SCRIPT_PATH, THAILAND_PLATFORM_FILE ),
			array(),
			THAILAND_PLATFORM_VERSION,
			true
		);
		wp_enqueue_script(
			self::MAPLIBRE_SCRIPT_HANDLE,
			plugins_url( RendererAssets::MAPLIBRE_SCRIPT_PATH, THAILAND_PLATFORM_FILE ),
			array( self::PMTILES_SCRIPT_HANDLE ),
			THAILAND_PLATFORM_VERSION,
			true
		);
		wp_enqueue_script(
			self::SCRIPT_HANDLE,
			plugins_url( 'assets/digital-islands/digital-islands.js', THAILAND_PLATFORM_FILE ),
			array( self::MAPLIBRE_SCRIPT_HANDLE ),
			THAILAND_PLATFORM_VERSION,
			true
		);
		wp_script_add_data( self::PMTILES_SCRIPT_HANDLE, 'strategy', 'defer' );
		wp_script_add_data( self::MAPLIBRE_SCRIPT_HANDLE, 'strategy', 'defer' );
		wp_script_add_data( self::SCRIPT_HANDLE, 'strategy', 'defer' );
		wp_add_inline_script(
			self::SCRIPT_HANDLE,
			'window.ThailandDigitalIslandsConfig=Object.freeze(' . $json . ');',
			'before'
		);
	}
}
