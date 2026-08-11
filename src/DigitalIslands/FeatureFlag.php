<?php
/**
 * Digital Islands feature state and exact WordPress page binding.
 *
 * @package Thailand_Platform
 */

namespace Thailand_Platform\DigitalIslands;

final class FeatureFlag {
	const OPTION      = 'thailand_platform_digital_islands_mode';
	const PAGE_ID_OPTION = 'thailand_platform_digital_islands_page_id';
	const MODE_OFF    = 'off';
	const MODE_CANARY = 'canary';
	const MODE_LIVE   = 'live';

	/** @return string */
	public static function mode() {
		if ( defined( 'THAILAND_PLATFORM_DISABLE_DIGITAL_ISLANDS' ) && THAILAND_PLATFORM_DISABLE_DIGITAL_ISLANDS ) {
			return self::MODE_OFF;
		}

		$mode = get_option( self::OPTION, self::MODE_OFF );
		return in_array( $mode, self::allowed_modes(), true ) ? $mode : self::MODE_OFF;
	}

	/** @param mixed $value Submitted value. @return string */
	public static function sanitize( $value ) {
		$value = is_string( $value ) ? sanitize_key( $value ) : self::MODE_OFF;
		return in_array( $value, self::allowed_modes(), true ) ? $value : self::MODE_OFF;
	}

	/**
	 * Canary authorization is capability-bound and does not use a URL query.
	 *
	 * @return bool
	 */
	public static function request_is_authorized() {
		if ( self::MODE_CANARY === self::mode() ) {
			return current_user_can( 'manage_options' );
		}

		return self::MODE_LIVE === self::mode() && Context::public_api_ready();
	}

	/** @return int */
	public static function page_id() {
		return absint( get_option( self::PAGE_ID_OPTION, 0 ) );
	}

	/** @param mixed $value Submitted page ID. @return int */
	public static function sanitize_page_id( $value ) {
		return absint( $value );
	}

	/** @return string[] */
	public static function allowed_modes() {
		return array( self::MODE_OFF, self::MODE_CANARY, self::MODE_LIVE );
	}

	/** @return void */
	public static function register_setting() {
		register_setting(
			'thailand_platform_digital_islands',
			self::OPTION,
			array(
				'type'              => 'string',
				'default'           => self::MODE_OFF,
				'sanitize_callback' => array( self::class, 'sanitize' ),
			)
		);
		register_setting(
			'thailand_platform_digital_islands',
			self::PAGE_ID_OPTION,
			array(
				'type'              => 'integer',
				'default'           => 0,
				'sanitize_callback' => array( self::class, 'sanitize_page_id' ),
			)
		);
	}
}
