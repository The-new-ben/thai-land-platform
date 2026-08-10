<?php
/**
 * Exact request identity and mode authorization for priority guides.
 *
 * @package Thailand_Platform
 */

namespace Thailand_Platform\Guides;

final class Context {
	/**
	 * @var array|false|null
	 */
	private static $candidate = null;

	/**
	 * @var array|false|null
	 */
	private static $route = null;

	/**
	 * @return void
	 */
	public function register() {
		add_action( 'template_redirect', array( $this, 'protect_canary' ), 1 );
	}

	/**
	 * Resolve only an exact managed identity with an allowed WordPress state.
	 *
	 * @return array|null
	 */
	public static function candidate_route() {
		if ( false === self::$candidate ) {
			return null;
		}
		if ( is_array( self::$candidate ) ) {
			return self::$candidate;
		}

		self::$candidate = false;
		if ( ! Repository::ready() || ! is_singular() || is_feed() || is_embed() ) {
			return null;
		}

		$post_id = absint( get_queried_object_id() );
		$route   = Repository::route_by_post_id( $post_id );
		if ( ! is_array( $route ) ) {
			return null;
		}

		$wordpress = $route['wordpress'] ?? array();
		$status    = get_post_status( $post_id );
		if (
			'id_and_path_exact' !== ( $wordpress['identity_policy'] ?? '' )
			|| $post_id !== absint( $wordpress['post_id'] ?? 0 )
			|| ( $wordpress['post_type'] ?? '' ) !== get_post_type( $post_id )
			|| post_password_required( $post_id )
		) {
			return null;
		}

		if ( 'publish' === $status && ! is_preview() ) {
			if ( self::request_path() !== self::normalize_path( $route['path'] ?? '' ) ) {
				return null;
			}
		} elseif (
			'draft_canary_or_published_live' === ( $wordpress['state_policy'] ?? '' )
			&& 'draft' === $status
			&& is_preview()
		) {
			// Draft previews are bound by exact protected post ID and type.
		} else {
			return null;
		}

		$route['_runtime_post_status'] = $status;
		self::$candidate               = $route;
		return self::$candidate;
	}

	/**
	 * Resolve the effective route after mode authorization.
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
		$candidate   = self::candidate_route();
		if ( ! is_array( $candidate ) ) {
			return null;
		}

		$mode = FeatureFlag::mode();
		if (
			FeatureFlag::MODE_LIVE === $mode
			&& 'publish' === ( $candidate['_runtime_post_status'] ?? '' )
			&& ! FeatureFlag::canary_requested()
		) {
			self::$route = $candidate;
			return self::$route;
		}

		if (
			FeatureFlag::MODE_CANARY === $mode
			&& FeatureFlag::canary_requested()
			&& current_user_can( 'manage_options' )
		) {
			self::$route = $candidate;
			return self::$route;
		}

		return null;
	}

	/**
	 * @return bool
	 */
	public static function should_render() {
		$route = self::route();
		return is_array( $route ) && Renderer::ready( $route );
	}

	/**
	 * @return bool
	 */
	public static function is_authorized_canary() {
		return FeatureFlag::MODE_CANARY === FeatureFlag::mode()
			&& FeatureFlag::canary_requested()
			&& current_user_can( 'manage_options' )
			&& is_array( self::candidate_route() );
	}

	/**
	 * Return unauthorized attempts against a managed identity as a normal 404.
	 *
	 * @return void
	 */
	public function protect_canary() {
		if ( ! FeatureFlag::canary_requested() || ! is_singular() ) {
			return;
		}

		$managed = Repository::route_by_post_id( absint( get_queried_object_id() ) );
		if ( ! is_array( $managed ) ) {
			return;
		}

		if ( self::is_authorized_canary() ) {
			nocache_headers();
			return;
		}

		global $wp_query;
		if ( is_object( $wp_query ) && method_exists( $wp_query, 'set_404' ) ) {
			$wp_query->set_404();
		}
		status_header( 404 );
		nocache_headers();
	}

	/**
	 * Build a bounded canary URL for a managed route.
	 *
	 * @param array $route Managed route.
	 * @return string
	 */
	public static function canary_url( $route ) {
		$post_id = absint( $route['wordpress']['post_id'] ?? 0 );
		$status  = $post_id ? get_post_status( $post_id ) : '';
		if ( 'publish' === $status ) {
			$url = home_url( $route['path'] ?? '/' );
		} elseif ( function_exists( 'get_preview_post_link' ) ) {
			$url = get_preview_post_link( $post_id );
		} else {
			$url = add_query_arg(
				array(
					'page_id' => $post_id,
					'preview' => 'true',
				),
				home_url( '/' )
			);
		}

		return add_query_arg( FeatureFlag::CANARY_QUERY, '1', $url );
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
		self::$candidate = null;
		self::$route     = null;
	}
}
