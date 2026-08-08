<?php
/**
 * Homepage presentation feature flag.
 *
 * @package Thailand_Platform
 */

namespace Thailand_Platform\Homepage;

final class FeatureFlag {
	const OPTION       = 'thailand_platform_homepage_mode';
	const MODE_OFF     = 'off';
	const MODE_CANARY  = 'canary';
	const MODE_LIVE    = 'live';
	const CANARY_QUERY = 'thp_home_canary';

	/**
	 * Return the effective mode without creating an option.
	 *
	 * @return string
	 */
	public static function mode() {
		if ( defined( 'THAILAND_PLATFORM_DISABLE_HOMEPAGE' ) && THAILAND_PLATFORM_DISABLE_HOMEPAGE ) {
			return self::MODE_OFF;
		}

		$mode = get_option( self::OPTION, self::MODE_OFF );

		return in_array( $mode, self::allowed_modes(), true ) ? $mode : self::MODE_OFF;
	}

	/**
	 * Validate a settings value against the complete allowlist.
	 *
	 * @param mixed $value Submitted value.
	 * @return string
	 */
	public static function sanitize( $value ) {
		$value = is_string( $value ) ? sanitize_key( $value ) : self::MODE_OFF;

		return in_array( $value, self::allowed_modes(), true ) ? $value : self::MODE_OFF;
	}

	/**
	 * Determine whether the bounded canary URL was requested.
	 *
	 * @return bool
	 */
	public static function canary_requested() {
		if ( ! isset( $_GET[ self::CANARY_QUERY ] ) ) {
			return false;
		}

		$value = sanitize_text_field( wp_unslash( $_GET[ self::CANARY_QUERY ] ) );

		return '1' === $value;
	}

	/**
	 * @return string[]
	 */
	public static function allowed_modes() {
		return array( self::MODE_OFF, self::MODE_CANARY, self::MODE_LIVE );
	}
}
