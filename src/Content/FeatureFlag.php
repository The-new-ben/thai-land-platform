<?php
/**
 * Real-estate content presentation feature flag.
 *
 * @package Thailand_Platform
 */

namespace Thailand_Platform\Content;

final class FeatureFlag {
	const OPTION    = 'thailand_platform_real_estate_mode';
	const MODE_OFF  = 'off';
	const MODE_LIVE = 'live';

	/**
	 * @return string
	 */
	public static function mode() {
		if ( defined( 'THAILAND_PLATFORM_DISABLE_REAL_ESTATE' ) && THAILAND_PLATFORM_DISABLE_REAL_ESTATE ) {
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
	 * @return string[]
	 */
	public static function allowed_modes() {
		return array( self::MODE_OFF, self::MODE_LIVE );
	}
}
