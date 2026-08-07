<?php
/**
 * Non-fatal WordPress update UI integration.
 *
 * @package Thailand_Platform
 */

namespace Thailand_Platform\Updates;

final class Checker {
	/**
	 * Register the required early init hook.
	 *
	 * @return void
	 */
	public static function register() {
		add_action( 'init', array( __CLASS__, 'boot' ), 5 );
	}

	/**
	 * Attach Plugin Update Checker as a human fallback only.
	 *
	 * Deliberate releases use the authenticated, independently verified
	 * deployment loop. A missing library, unavailable manifest, or PUC failure
	 * must never make the public site fail.
	 *
	 * @return void
	 */
	public static function boot() {
		if (
			! defined( 'THAILAND_PLATFORM_ENABLE_UPDATE_CHECKER' ) ||
			true !== THAILAND_PLATFORM_ENABLE_UPDATE_CHECKER
		) {
			return;
		}

		$library = THAILAND_PLATFORM_DIR . 'lib/plugin-update-checker/plugin-update-checker.php';

		if ( ! is_readable( $library ) ) {
			return;
		}

		require_once $library;

		$factory = '\\YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory';

		if ( ! class_exists( $factory ) ) {
			return;
		}

		try {
			$factory::buildUpdateChecker(
				THAILAND_PLATFORM_MANIFEST_URL,
				THAILAND_PLATFORM_FILE,
				'thailand-platform'
			);
		} catch ( \Throwable $exception ) {
			// Intentionally non-fatal and redacted. Release health is checked elsewhere.
			unset( $exception );
		}
	}
}
