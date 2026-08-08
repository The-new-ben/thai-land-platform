<?php
/**
 * Public read-only Thailand geography API.
 *
 * @package Thailand_Platform
 */

namespace Thailand_Platform\Geography;

use RuntimeException;
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
	 * Register the public source-backed geography endpoint.
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
	 * Return all 77 province entities and the non-administrative region facets.
	 *
	 * @return WP_REST_Response
	 */
	public function respond() {
		try {
			$response = new WP_REST_Response( Registry::public_payload(), 200 );
			$response->header( 'Cache-Control', 'public, max-age=86400, stale-while-revalidate=604800' );
			$response->header( 'ETag', '"' . Registry::digest() . '"' );
			$response->header( 'X-Content-Type-Options', 'nosniff' );

			return $response;
		} catch ( RuntimeException $exception ) {
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
}
