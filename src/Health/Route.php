<?php
/**
 * Public release healthcheck.
 *
 * @package Thailand_Platform
 */

namespace Thailand_Platform\Health;

use Thailand_Platform\Geography\Repository as Geography_Repository;
use WP_REST_Response;

final class Route {
	const REST_NAMESPACE = 'thailand-platform/v1';
	const REST_ROUTE     = '/health';

	/**
	 * Register the REST initialization hook.
	 *
	 * @return void
	 */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_route' ) );
	}

	/**
	 * Register a read-only, deliberately minimal public healthcheck.
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
	 * Return only the release identity required for independent verification.
	 *
	 * @return WP_REST_Response
	 */
	public function respond() {
		$status_code = 200;
		$status      = 'ok';

		try {
			Geography_Repository::all();
		} catch ( \Throwable $exception ) {
			unset( $exception );
			$status_code = 503;
			$status      = 'degraded';
		}

		$response = new WP_REST_Response(
			array(
				'name'    => 'thailand-platform',
				'version' => THAILAND_PLATFORM_VERSION,
				'status'  => $status,
			),
			$status_code
		);

		$response->header( 'Cache-Control', 'no-store' );

		return $response;
	}
}
