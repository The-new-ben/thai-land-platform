<?php
/**
 * Public read-only Thailand geography API.
 *
 * @package Thailand_Platform
 */

namespace Thailand_Platform\Geography;

use WP_REST_Response;

final class Route {
	const REST_NAMESPACE = 'thailand-platform/v1';
	const REST_ROUTE     = '/geography';

	/**
	 * Register the REST initialization hook.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_route' ) );
	}

	/**
	 * Register the public compiled geography endpoint.
	 *
	 * @return void
	 */
	public function register_route() {
		register_rest_route(
			self::REST_NAMESPACE,
			self::REST_ROUTE,
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, 'respond' ),
				'permission_callback' => '__return_true',
			)
		);
	}

	/**
	 * Serve the compiled public payload with conditional cache validation.
	 *
	 * @param object|null $request REST request.
	 * @return WP_REST_Response
	 */
	public function respond( $request = null ) {
		try {
			$etag = '"' . Repository::public_digest() . '"';
			$status = self::etag_matches( self::request_header( $request, 'if-none-match' ), $etag ) ? 304 : 200;
			$response = new WP_REST_Response(
				304 === $status ? null : Repository::public_payload(),
				$status
			);
			$response->header( 'Cache-Control', 'public, max-age=86400, stale-while-revalidate=604800' );
			$response->header( 'ETag', $etag );
			$response->header( 'X-Content-Type-Options', 'nosniff' );

			return $response;
		} catch ( \Throwable $exception ) {
			unset( $exception );

			$response = new WP_REST_Response(
				array(
					'status' => 'unavailable',
				),
				503
			);
			$response->header( 'Cache-Control', 'no-store' );
			$response->header( 'X-Robots-Tag', 'noindex' );

			return $response;
		}
	}

	/**
	 * @param object|null $request REST request.
	 * @param string      $name Header name.
	 * @return string
	 */
	private static function request_header( $request, $name ) {
		if ( ! is_object( $request ) || ! method_exists( $request, 'get_header' ) ) {
			return '';
		}

		return trim( (string) $request->get_header( $name ) );
	}

	/**
	 * Match strong or weak validators from an If-None-Match list.
	 *
	 * @param string $request_value Request header.
	 * @param string $etag Current ETag.
	 * @return bool
	 */
	private static function etag_matches( $request_value, $etag ) {
		if ( '' === $request_value ) {
			return false;
		}

		foreach ( explode( ',', $request_value ) as $candidate ) {
			$candidate = trim( $candidate );
			if ( '*' === $candidate || $etag === $candidate || 'W/' . $etag === $candidate ) {
				return true;
			}
		}

		return false;
	}
}
