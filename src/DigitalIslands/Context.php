<?php
/**
 * Exact route identity and mode authorization for Digital Islands.
 *
 * @package Thailand_Platform
 */

namespace Thailand_Platform\DigitalIslands;

final class Context {
	/** @return void */
	public function register() {
		add_action( 'template_redirect', array( $this, 'protect_canary' ), 1 );
	}

	/** @return bool */
	public static function should_render() {
		try {
			if ( FeatureFlag::MODE_CANARY === FeatureFlag::mode() ) {
				return self::is_authorized_canary() && Renderer::ready_for_canary();
			}

			return self::is_live_request() && Renderer::ready_for_public();
		} catch ( \Throwable $exception ) {
			unset( $exception );
			return false;
		}
	}

	/** @return bool */
	public static function is_authorized_canary() {
		return FeatureFlag::MODE_CANARY === FeatureFlag::mode()
			&& current_user_can( 'manage_options' )
			&& self::canary_identity_matches_request();
	}

	/**
	 * A public API is available only after the exact production page identity,
	 * public-reviewed projection, and complete runtime assets are all present.
	 *
	 * @return bool
	 */
	public static function public_api_ready() {
		try {
			return FeatureFlag::MODE_LIVE === FeatureFlag::mode()
				&& self::live_page_is_provisioned()
				&& Renderer::ready_for_public();
		} catch ( \Throwable $exception ) {
			unset( $exception );
			return false;
		}
	}

	/** @return bool */
	public static function is_live_request() {
		if (
			! self::public_api_ready()
			|| ! self::live_identity_matches_request()
		) {
			return false;
		}

		return true;
	}

	/**
	 * Match the current request to the configured, published page without
	 * consulting artifact readiness. Ordinary tracking query parameters are
	 * ignored; previews, feeds and embeds are never public representations.
	 *
	 * @return bool
	 */
	public static function live_identity_matches_request() {
		if (
			! is_singular()
			|| is_preview()
			|| is_feed()
			|| is_embed()
		) {
			return false;
		}

		$post_id = absint( get_queried_object_id() );
		return self::page_identity_is_valid( $post_id, false )
			&& self::request_path() === self::normalize_path( Repository::canonical_path() );
	}

	/**
	 * Canary rendering is bound to the same stored page identity. The page may
	 * remain draft/private during administrator review, but a same-path 404 or
	 * different queried object can never be taken over by the plugin.
	 *
	 * @return bool
	 */
	public static function canary_identity_matches_request() {
		if ( ! is_singular() || is_feed() || is_embed() ) {
			return false;
		}

		$post_id = absint( get_queried_object_id() );
		return self::page_identity_is_valid( $post_id, true )
			&& self::request_path() === self::normalize_path( Repository::canonical_path() );
	}

	/**
	 * Verify the configured page independently of the current request. This is
	 * also the public REST discovery gate.
	 *
	 * @return bool
	 */
	public static function live_page_is_provisioned() {
		$post_id = FeatureFlag::page_id();
		return self::page_identity_is_valid( $post_id, false );
	}

	/**
	 * @param int  $post_id         Configured/current page ID.
	 * @param bool $allow_nonpublic Whether reviewed administrator previews may
	 *                              use a non-public status.
	 * @return bool
	 */
	private static function page_identity_is_valid( $post_id, $allow_nonpublic ) {
		$post_id = absint( $post_id );
		if ( 0 === $post_id || FeatureFlag::page_id() !== $post_id || 'page' !== get_post_type( $post_id ) ) {
			return false;
		}

		$status = get_post_status( $post_id );
		if (
			( ! $allow_nonpublic && 'publish' !== $status )
			|| ( $allow_nonpublic && ! in_array( $status, array( 'draft', 'pending', 'private', 'publish' ), true ) )
			|| ! self::stored_password_is_empty( $post_id )
			|| ( ! $allow_nonpublic && post_password_required( $post_id ) )
		) {
			return false;
		}

		$canonical = self::normalize_path( Repository::canonical_path() );
		$page_uri  = function_exists( 'get_page_uri' ) ? get_page_uri( $post_id ) : '';
		if ( ! is_string( $page_uri ) || $canonical !== self::stored_page_uri_path( $page_uri ) ) {
			return false;
		}

		if ( $allow_nonpublic ) {
			return true;
		}

		$permalink = get_permalink( $post_id );
		return is_string( $permalink )
			&& '' !== $permalink
			&& self::permalink_matches_canonical( $permalink, $canonical );
	}

	/** @param string $permalink Absolute WordPress permalink. @param string $canonical Required path. @return bool */
	private static function permalink_matches_canonical( $permalink, $canonical ) {
		$actual = wp_parse_url( $permalink );
		$home   = wp_parse_url( home_url( '/' ) );
		if ( ! is_array( $actual ) || ! is_array( $home ) ) {
			return false;
		}
		if (
			strtolower( (string) ( $actual['scheme'] ?? '' ) ) !== strtolower( (string) ( $home['scheme'] ?? '' ) )
			|| strtolower( (string) ( $actual['host'] ?? '' ) ) !== strtolower( (string) ( $home['host'] ?? '' ) )
			|| (int) ( $actual['port'] ?? 0 ) !== (int) ( $home['port'] ?? 0 )
			|| isset( $actual['user'] )
			|| isset( $actual['pass'] )
			|| isset( $actual['query'] )
			|| isset( $actual['fragment'] )
		) {
			return false;
		}
		return self::safe_url_path( $permalink ) === $canonical;
	}

	/** @param int $post_id WordPress page ID. @return bool */
	public static function stored_password_is_empty( $post_id ) {
		$password = get_post_field( 'post_password', $post_id );
		return is_string( $password ) && '' === $password;
	}

	/**
	 * Match only the registry-owned canonical path. Query parameters do not own
	 * map state; client state remains fragment-only.
	 *
	 * @return bool
	 */
	public static function is_route_request() {
		if ( FeatureFlag::MODE_OFF === FeatureFlag::mode() ) {
			return false;
		}

		try {
			return self::request_path() === self::normalize_path( Repository::canonical_path() );
		} catch ( \Throwable $exception ) {
			unset( $exception );
			return false;
		}
	}

	/**
	 * Return unauthorized Canary and not-ready Live route requests as ordinary
	 * uncacheable 404s. This prevents the stored WordPress body from becoming a
	 * fallback public representation when any reviewed gate disappears.
	 *
	 * @return void
	 */
	public function protect_canary() {
		$mode = FeatureFlag::mode();
		if ( ! self::is_route_request() ) {
			return;
		}

		if ( FeatureFlag::MODE_CANARY === $mode && self::is_authorized_canary() ) {
			nocache_headers();
			return;
		}
		if ( FeatureFlag::MODE_LIVE === $mode && self::is_live_request() ) {
			return;
		}
		if ( ! in_array( $mode, array( FeatureFlag::MODE_CANARY, FeatureFlag::MODE_LIVE ), true ) ) {
			return;
		}

		global $wp_query;
		if ( is_object( $wp_query ) && method_exists( $wp_query, 'set_404' ) ) {
			$wp_query->set_404();
		}
		status_header( 404 );
		nocache_headers();
	}

	/** @return string */
	public static function representation_state() {
		return self::is_live_request() ? PublicView::REPRESENTATION_PUBLIC : PublicView::REPRESENTATION_CANARY;
	}

	/** @return string */
	public static function request_path() {
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? wp_unslash( $_SERVER['REQUEST_URI'] ) : '/';
		return self::safe_url_path( $request_uri );
	}

	/**
	 * Decode a URL path once and reject ambiguous or unsafe encodings.
	 *
	 * @param string $url Absolute or site-relative URL.
	 * @return string
	 */
	public static function safe_url_path( $url ) {
		$path = wp_parse_url( (string) $url, PHP_URL_PATH );
		if ( ! is_string( $path ) ) {
			return '';
		}
		return self::decode_path( $path );
	}

	/**
	 * WordPress stores non-ASCII post_name values URI-encoded. Decode that one
	 * relative page URI without accepting encoded separators or traversal.
	 *
	 * @param string $page_uri Raw get_page_uri() value.
	 * @return string
	 */
	public static function stored_page_uri_path( $page_uri ) {
		if (
			! is_string( $page_uri )
			|| '' === $page_uri
			|| '/' === substr( $page_uri, 0, 1 )
			|| '/' === substr( $page_uri, -1 )
			|| false !== strpos( $page_uri, '?' )
			|| false !== strpos( $page_uri, '#' )
		) {
			return '';
		}
		return self::decode_path( '/' . $page_uri . '/' );
	}

	/** @param string $path Raw URL path. @return string */
	private static function decode_path( $path ) {
		if (
			'' === $path
			|| preg_match( '/%(?![0-9a-f]{2})/i', $path )
			|| preg_match( '/%(?:2f|5c)/i', $path )
		) {
			return '';
		}

		$path = rawurldecode( $path );
		if (
			1 !== preg_match( '//u', $path )
			|| preg_match( '/[\x00-\x1f\x7f]/', $path )
			|| false !== strpos( $path, '\\' )
			|| false !== strpos( $path, '//' )
			|| false !== strpos( $path, '%' )
		) {
			return '';
		}

		$trimmed = trim( $path, '/' );
		if ( '' !== $trimmed ) {
			foreach ( explode( '/', $trimmed ) as $segment ) {
				if ( '' === $segment || '.' === $segment || '..' === $segment ) {
					return '';
				}
			}
		}

		return self::normalize_path( $path );
	}

	/** @param string $path Site-relative path. @return string */
	public static function normalize_path( $path ) {
		$path = (string) $path;
		if ( class_exists( '\\Normalizer' ) ) {
			$normalized = \Normalizer::normalize( $path, \Normalizer::FORM_C );
			if ( is_string( $normalized ) ) {
				$path = $normalized;
			}
		}
		if ( '' === $path || '/' === $path ) {
			return '/';
		}
		return 0 === strpos( $path, '/' ) ? $path : '/' . $path;
	}
}
