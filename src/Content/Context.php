<?php
/**
 * Exact request identity for managed content pages.
 *
 * @package Thailand_Platform
 */

namespace Thailand_Platform\Content;

final class Context {
	/**
	 * @var array|false|null
	 */
	private static $route = null;

	/**
	 * Resolve only a published singular whose post ID, type, and path all match.
	 *
	 * @return array|null
	 */
	public static function route() {
		if ( false === self::$route ) {
			return null;
		}
		if ( is_array( self::$route ) ) {
			return self::$route;
		}

		self::$route = false;
		if (
			FeatureFlag::MODE_LIVE !== FeatureFlag::mode()
			|| ! Repository::ready()
			|| ! is_singular()
			|| is_preview()
			|| is_feed()
			|| is_embed()
		) {
			return null;
		}

		$post_id = absint( get_queried_object_id() );
		$route   = Repository::route_by_post_id( $post_id );
		if ( ! is_array( $route ) ) {
			return null;
		}

		$wordpress = $route['wordpress'] ?? array();
		if (
			'id_and_path_exact' !== ( $wordpress['identity_policy'] ?? '' )
			|| $post_id !== absint( $wordpress['post_id'] ?? 0 )
			|| ( $wordpress['post_type'] ?? '' ) !== get_post_type( $post_id )
			|| 'publish' !== get_post_status( $post_id )
			|| post_password_required( $post_id )
			|| self::request_path() !== self::normalize_path( $route['path'] ?? '' )
		) {
			return null;
		}

		self::$route = $route;
		return self::$route;
	}

	/**
	 * @return bool
	 */
	public static function should_render() {
		return null !== self::route();
	}

	/**
	 * @return string
	 */
	public static function request_path() {
		$request_uri = isset( $_SERVER['REQUEST_URI'] )
			? wp_unslash( $_SERVER['REQUEST_URI'] )
			: '/';
		$path = wp_parse_url( $request_uri, PHP_URL_PATH );
		if ( is_string( $path ) && preg_match( '/%(?:2f|5c)/i', $path ) ) {
			return self::normalize_path( $path );
		}
		return self::normalize_path( is_string( $path ) ? rawurldecode( $path ) : '/' );
	}

	/**
	 * @param string $path Site-relative path.
	 * @return string
	 */
	public static function normalize_path( $path ) {
		$path = (string) $path;
		if ( '' === $path || '/' === $path ) {
			return '/';
		}
		if ( class_exists( '\\Normalizer' ) ) {
			$normalized = \Normalizer::normalize( $path, \Normalizer::FORM_C );
			if ( is_string( $normalized ) ) {
				$path = $normalized;
			}
		}
		return 0 === strpos( $path, '/' ) ? $path : '/' . $path;
	}

	/**
	 * Reset only for dependency-free tests.
	 *
	 * @return void
	 */
	public static function reset_for_tests() {
		self::$route = null;
	}
}
