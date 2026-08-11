<?php
/**
 * Private, read-only REST API for the Digital Islands Canary.
 *
 * @package Thailand_Platform
 */

namespace Thailand_Platform\DigitalIslands;

use WP_Error;
use WP_REST_Response;

final class RestController extends \WP_REST_Controller {
	const REST_NAMESPACE = 'thailand-platform/v1';
	const ROUTE_PREFIX   = '/digital-islands';
	const ISLAND_ID      = 'geo:th:island:ko-pha-ngan';
	const ISLAND_ROUTE   = '/digital-islands/(?P<island_id>geo:th:island:[a-z0-9]+(?:-[a-z0-9]+)*)';
	const LAYERS_ROUTE   = '/digital-islands/(?P<island_id>geo:th:island:[a-z0-9]+(?:-[a-z0-9]+)*)/layers';
	const ENTITIES_ROUTE = '/digital-islands/(?P<island_id>geo:th:island:[a-z0-9]+(?:-[a-z0-9]+)*)/entities';
	const ENTITY_ROUTE   = '/digital-islands/(?P<island_id>geo:th:island:[a-z0-9]+(?:-[a-z0-9]+)*)/entities/(?P<entity_id>[a-z_]+:th:[a-z0-9:._-]+)';
	const SEARCH_ROUTE   = '/digital-islands/(?P<island_id>geo:th:island:[a-z0-9]+(?:-[a-z0-9]+)*)/search/(?P<term>[^/]+)';
	const ITEM_CONTRACT  = 'thailand-digital-island-entity-v1';

	/** @var callable|null */
	private $date_provider;

	/** @param callable|null $date_provider Optional UTC date provider for tests. */
	public function __construct( $date_provider = null ) {
		$this->namespace     = self::REST_NAMESPACE;
		$this->rest_base     = ltrim( self::ROUTE_PREFIX, '/' );
		$this->date_provider = is_callable( $date_provider ) ? $date_provider : null;
	}

	/** @return void */
	public function register() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
		add_filter( 'rest_post_dispatch', array( $this, 'protect_dispatch' ), 10, 3 );
	}

	/** @return void */
	public function register_routes() {
		if ( true !== $this->permission_check() ) {
			return;
		}

		$this->register_read_route( self::ISLAND_ROUTE, 'respond_island', $this->island_args() );
		$this->register_read_route( self::LAYERS_ROUTE, 'respond_layers', $this->island_args() );
		$this->register_read_route( self::ENTITIES_ROUTE, 'respond_entities', $this->island_args() );
		$this->register_read_route(
			self::ENTITY_ROUTE,
			'respond_entity',
			array_merge(
				$this->island_args(),
				array(
					'entity_id' => array(
						'type'              => 'string',
						'required'          => true,
						'validate_callback' => array( $this, 'validate_entity_id_arg' ),
					),
				)
			)
		);
		$this->register_read_route(
			self::SEARCH_ROUTE,
			'respond_search',
			array_merge(
				$this->island_args(),
				array(
					'term' => array(
						'type'              => 'string',
						'required'          => true,
						'validate_callback' => array( $this, 'validate_term_arg' ),
						'sanitize_callback' => 'sanitize_text_field',
					),
				)
			)
		);
	}

	/** @return true|WP_Error */
	public function permission_check( $request = null ) {
		unset( $request );
		try {
			if ( FeatureFlag::request_is_authorized() ) {
				return true;
			}
		} catch ( \Throwable $exception ) {
			unset( $exception );
		}
		return $this->not_found();
	}

	/** @param object|null $request Request. @return WP_REST_Response|WP_Error */
	public function respond_island( $request = null ) {
		$guard = $this->guard_request( $request );
		if ( true !== $guard ) {
			return $guard;
		}
		return $this->safe_response( 'island_payload' );
	}

	/** @param object|null $request Request. @return WP_REST_Response|WP_Error */
	public function respond_layers( $request = null ) {
		$guard = $this->guard_request( $request );
		if ( true !== $guard ) {
			return $guard;
		}
		return $this->safe_response( 'layers_payload' );
	}

	/** @param object|null $request Request. @return WP_REST_Response|WP_Error */
	public function respond_entities( $request = null ) {
		$guard = $this->guard_request( $request );
		if ( true !== $guard ) {
			return $guard;
		}
		return $this->safe_response( 'entities_payload' );
	}

	/** @param object|null $request Request. @return WP_REST_Response|WP_Error */
	public function respond_entity( $request = null ) {
		$guard = $this->guard_request( $request );
		if ( true !== $guard ) {
			return $guard;
		}
		$entity_id = $this->request_param( $request, 'entity_id' );
		if ( ! $this->validate_entity_id_arg( $entity_id ) ) {
			return $this->not_found();
		}

		try {
			$representation = $this->representation_state();
			$entity = PublicView::entity( $entity_id, $this->reference_date(), $representation );
			if ( null === $entity ) {
				return $this->not_found();
			}
			return $this->success(
				array(
					'contract_id'          => self::ITEM_CONTRACT,
					'schema_version'       => 1,
					'dataset_version'      => Repository::dataset_version(),
					'dataset_checked_on'   => Repository::checked_on(),
					'as_of'                => $this->reference_date(),
					'representation_state' => $representation,
					'entity'               => $entity,
				)
			);
		} catch ( \Throwable $exception ) {
			unset( $exception );
			return $this->unavailable();
		}
	}

	/** @param object|null $request Request. @return WP_REST_Response|WP_Error */
	public function respond_search( $request = null ) {
		$guard = $this->guard_request( $request );
		if ( true !== $guard ) {
			return $guard;
		}
		$term = $this->request_param( $request, 'term' );
		$term = is_string( $term ) ? sanitize_text_field( $term ) : '';
		if ( ! $this->validate_term_arg( $term ) ) {
			return $this->invalid_request();
		}

		try {
			return $this->success( PublicView::search_payload( $term, $this->reference_date(), $this->representation_state() ) );
		} catch ( \Throwable $exception ) {
			unset( $exception );
			return $this->unavailable();
		}
	}

	/**
	 * Apply public cache headers only while the complete Live readiness gate is
	 * true. Every Canary, denied, unavailable, or readiness-lost response stays
	 * private/no-store so a hidden failure can never be cached publicly.
	 *
	 * @param mixed  $response REST response.
	 * @param mixed  $server   REST server.
	 * @param object $request  REST request.
	 * @return mixed
	 */
	public function protect_dispatch( $response, $server, $request ) {
		unset( $server );
		$route = is_object( $request ) && method_exists( $request, 'get_route' ) ? $request->get_route() : '';
		if ( ! is_string( $route ) || 0 !== strpos( $route, '/' . self::REST_NAMESPACE . self::ROUTE_PREFIX ) ) {
			return $response;
		}
		if ( is_object( $response ) && method_exists( $response, 'header' ) ) {
			$status = method_exists( $response, 'get_status' ) ? (int) $response->get_status() : 0;
			$this->set_response_headers( $response, 200 !== $status );
		}
		if (
			PublicView::REPRESENTATION_CANARY === $this->representation_state()
			&& is_object( $response )
			&& method_exists( $response, 'remove_header' )
		) {
			$response->remove_header( 'ETag' );
		}
		return $response;
	}

	/** @param mixed $value Island ID. @return bool */
	public function validate_island_id_arg( $value ) {
		return self::ISLAND_ID === $value;
	}

	/** @param mixed $value Entity ID. @return bool */
	public function validate_entity_id_arg( $value ) {
		return is_string( $value ) && 1 === preg_match( '/\A[a-z_]+:th:[a-z0-9:._-]+\z/D', $value );
	}

	/** @param mixed $value Search term. @return bool */
	public function validate_term_arg( $value ) {
		if (
			! is_string( $value )
			|| false !== strpos( $value, '/' )
			|| false !== strpos( $value, '\\' )
			|| preg_match( '/[\x00-\x1F\x7F]/u', $value )
		) {
			return false;
		}
		$length = $this->text_length( trim( $value ) );
		return 2 <= $length && 80 >= $length;
	}

	/** @param string $route Route. @param string $callback Callback. @param array $args Args. @return void */
	private function register_read_route( $route, $callback, $args ) {
		register_rest_route(
			$this->namespace,
			$route,
			array(
				'methods'             => \WP_REST_Server::READABLE,
				'callback'            => array( $this, $callback ),
				'permission_callback' => array( $this, 'permission_check' ),
				'args'                => $args,
			)
		);
	}

	/** @return array */
	private function island_args() {
		return array(
			'island_id' => array(
				'type'              => 'string',
				'required'          => true,
				'validate_callback' => array( $this, 'validate_island_id_arg' ),
			),
		);
	}

	/** @param object|null $request Request. @return true|WP_Error */
	private function guard_request( $request ) {
		$permission = $this->permission_check();
		if ( true !== $permission ) {
			return $permission;
		}

		if ( is_object( $request ) && method_exists( $request, 'get_method' ) && 'GET' !== strtoupper( (string) $request->get_method() ) ) {
			return $this->invalid_request();
		}
		if ( is_object( $request ) && method_exists( $request, 'get_body' ) && '' !== trim( (string) $request->get_body() ) ) {
			return new WP_Error( 'rest_digital_islands_get_body_forbidden', 'GET request bodies are not accepted.', array( 'status' => 400 ) );
		}
		if ( is_object( $request ) && method_exists( $request, 'get_json_params' ) ) {
			$json = $request->get_json_params();
			if ( null !== $json && array() !== $json ) {
				return new WP_Error( 'rest_digital_islands_get_json_forbidden', 'JSON parameters are not accepted on GET.', array( 'status' => 400 ) );
			}
		}
		if ( is_object( $request ) && method_exists( $request, 'get_query_params' ) && array() !== $request->get_query_params() ) {
			return new WP_Error( 'rest_digital_islands_query_state_forbidden', 'Map state belongs in the URL fragment.', array( 'status' => 400 ) );
		}

		$island_id = $this->request_param( $request, 'island_id' );
		return $this->validate_island_id_arg( $island_id ) ? true : $this->not_found();
	}

	/** @param string $method PublicView method. @return WP_REST_Response|WP_Error */
	private function safe_response( $method ) {
		try {
			return $this->success(
				call_user_func(
					array( PublicView::class, $method ),
					$this->reference_date(),
					$this->representation_state()
				)
			);
		} catch ( \Throwable $exception ) {
			unset( $exception );
			return $this->unavailable();
		}
	}

	/** @param array $data Response data. @return WP_REST_Response */
	private function success( $data ) {
		$response = new WP_REST_Response( $data, 200 );
		$this->set_response_headers( $response );
		return $response;
	}

	/**
	 * @param WP_REST_Response $response      Response.
	 * @param bool             $force_private Whether an error/readiness failure
	 *                                        must be uncacheable.
	 * @return void
	 */
	private function set_response_headers( $response, $force_private = false ) {
		if ( ! $force_private && PublicView::REPRESENTATION_PUBLIC === $this->representation_state() ) {
			$response->header( 'Cache-Control', 'public, max-age=300, stale-while-revalidate=60' );
			$response->header( 'X-Content-Type-Options', 'nosniff' );
			return;
		}

		$response->header( 'Cache-Control', 'private, no-store, max-age=0' );
		$response->header( 'Pragma', 'no-cache' );
		$response->header( 'X-Robots-Tag', 'noindex, nofollow, noarchive' );
		$response->header( 'Vary', 'Cookie, Authorization' );
	}

	/** @return string */
	private function representation_state() {
		return Context::public_api_ready()
			? PublicView::REPRESENTATION_PUBLIC
			: PublicView::REPRESENTATION_CANARY;
	}

	/** @param object|null $request Request. @param string $key Key. @return mixed */
	private function request_param( $request, $key ) {
		if ( is_object( $request ) && method_exists( $request, 'get_param' ) ) {
			return $request->get_param( $key );
		}
		if ( is_array( $request ) ) {
			return $request[ $key ] ?? null;
		}
		return null;
	}

	/** @return string */
	private function reference_date() {
		if ( null === $this->date_provider ) {
			return gmdate( 'Y-m-d' );
		}
		$date = call_user_func( $this->date_provider );
		return is_string( $date ) ? $date : '';
	}

	/** @param string $value UTF-8 text. @return int */
	private function text_length( $value ) {
		if ( function_exists( 'mb_strlen' ) ) {
			return mb_strlen( $value, 'UTF-8' );
		}
		$count = preg_match_all( '/./us', $value, $matches );
		return false === $count ? strlen( $value ) : $count;
	}

	/** @return WP_Error */
	private function not_found() {
		return new WP_Error( 'rest_no_route', 'No route was found matching the URL and request method.', array( 'status' => 404 ) );
	}

	/** @return WP_Error */
	private function invalid_request() {
		return new WP_Error( 'rest_digital_islands_invalid_request', 'The request is invalid.', array( 'status' => 400 ) );
	}

	/** @return WP_Error */
	private function unavailable() {
		return new WP_Error( 'rest_digital_islands_unavailable', 'The island dataset is unavailable.', array( 'status' => 503 ) );
	}
}
