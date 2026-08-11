<?php
/**
 * Reversible template renderer for the private island Canary.
 *
 * @package Thailand_Platform
 */

namespace Thailand_Platform\DigitalIslands;

final class Renderer {
	/** @return void */
	public function register() {
		add_filter( 'template_include', array( $this, 'template' ), 99 );
	}

	/** @param string $template Original theme template. @return string */
	public function template( $template ) {
		return Context::should_render()
			? THAILAND_PLATFORM_DIR . 'templates/digital-islands/koh-phangan.php'
			: $template;
	}

	/** @return bool */
	public static function ready() {
		return FeatureFlag::MODE_LIVE === FeatureFlag::mode()
			? self::ready_for_public()
			: self::ready_for_canary();
	}

	/** @return bool */
	public static function ready_for_canary() {
		return self::assets_ready() && Repository::canary_ready();
	}

	/** @return bool */
	public static function ready_for_public() {
		return self::assets_ready() && Repository::public_ready();
	}

	/** @return bool */
	private static function assets_ready() {
		if ( ! RendererAssets::ready() ) {
			return false;
		}

		$required = array(
			THAILAND_PLATFORM_DIR . 'templates/digital-islands/koh-phangan.php',
			THAILAND_PLATFORM_DIR . 'assets/digital-islands/digital-islands.css',
			THAILAND_PLATFORM_DIR . 'assets/digital-islands/digital-islands.js',
		);
		foreach ( $required as $path ) {
			if ( ! is_readable( $path ) || 0 === filesize( $path ) ) {
				return false;
			}
		}
		return true;
	}
}
