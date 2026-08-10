<?php
/**
 * Independent priority guides feature flag.
 *
 * @package Thailand_Platform
 */

namespace Thailand_Platform\Guides;

final class FeatureFlag {
	const OPTION       = 'thailand_platform_guides_mode';
	const MODE_OFF     = 'off';
	const MODE_CANARY  = 'canary';
	const MODE_LIVE    = 'live';
	const CANARY_QUERY = 'thp_guides_canary';

	/**
	 * @return string
	 */
	public static function mode() {
		if ( defined( 'THAILAND_PLATFORM_DISABLE_GUIDES' ) && THAILAND_PLATFORM_DISABLE_GUIDES ) {
			return self::MODE_OFF;
		}

		$mode = get_option( self::OPTION, self::MODE_OFF );
		return in_array( $mode, self::allowed_modes(), true ) ? $mode : self::MODE_OFF;
	}

	/**
	 * @param mixed $value Submitted setting.
	 * @return string
	 */
	public static function sanitize( $value ) {
		$value = is_string( $value ) ? sanitize_key( $value ) : self::MODE_OFF;
		return in_array( $value, self::allowed_modes(), true ) ? $value : self::MODE_OFF;
	}

	/**
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
