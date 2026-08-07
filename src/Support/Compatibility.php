<?php
/**
 * Runtime compatibility checks.
 *
 * @package Thailand_Platform
 */

namespace Thailand_Platform\Support;

final class Compatibility {
	const MINIMUM_PHP = '7.4';
	const MINIMUM_WP  = '6.9';

	/**
	 * Stop activation when the declared runtime contract is not met.
	 *
	 * @return void
	 */
	public static function assert_activation_requirements() {
		global $wp_version;

		$php_supported = version_compare( PHP_VERSION, self::MINIMUM_PHP, '>=' );
		$wp_supported  = isset( $wp_version ) && version_compare( $wp_version, self::MINIMUM_WP, '>=' );

		if ( $php_supported && $wp_supported ) {
			return;
		}

		$message = sprintf(
			/* translators: 1: required PHP version, 2: required WordPress version. */
			esc_html__(
				'Thailand Platform requires PHP %1$s or later and WordPress %2$s or later.',
				'thailand-platform'
			),
			esc_html( self::MINIMUM_PHP ),
			esc_html( self::MINIMUM_WP )
		);

		wp_die( $message ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Each value is escaped above.
	}
}
