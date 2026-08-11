<?php
/**
 * Dependency-free contracts for Canary and fail-closed public Digital Islands.
 */

declare(strict_types=1);

define( 'THAILAND_PLATFORM_DIR', dirname( __DIR__ ) . DIRECTORY_SEPARATOR );
define( 'THAILAND_PLATFORM_FILE', THAILAND_PLATFORM_DIR . 'thailand-platform.php' );
define( 'THAILAND_PLATFORM_VERSION', 'test' );

$GLOBALS['di_options']       = array();
$GLOBALS['di_admin']         = false;
$GLOBALS['di_routes']        = array();
$GLOBALS['di_actions']       = array();
$GLOBALS['di_filters']       = array();
$GLOBALS['di_settings']      = array();
$GLOBALS['di_status']        = array();
$GLOBALS['di_nocache_count'] = 0;
$GLOBALS['di_queried_id']    = 731;
$GLOBALS['di_singular']      = true;
$GLOBALS['di_preview']       = false;
$GLOBALS['di_feed']          = false;
$GLOBALS['di_embed']         = false;
$GLOBALS['di_page']          = array(
	'ID'            => 731,
	'post_type'     => 'page',
	'post_status'   => 'draft',
	'post_password' => '',
	'page_uri'      => '',
);

function get_option( $key, $default = false ) {
	return array_key_exists( $key, $GLOBALS['di_options'] ) ? $GLOBALS['di_options'][ $key ] : $default;
}

function current_user_can( $capability ) {
	return 'manage_options' === $capability && true === $GLOBALS['di_admin'];
}

function absint( $value ) {
	return abs( (int) $value );
}

function is_singular() {
	return true === $GLOBALS['di_singular'];
}

function is_preview() {
	return true === $GLOBALS['di_preview'];
}

function is_feed() {
	return true === $GLOBALS['di_feed'];
}

function is_embed() {
	return true === $GLOBALS['di_embed'];
}

function get_queried_object_id() {
	return $GLOBALS['di_queried_id'];
}

function get_post_type( $post_id ) {
	return absint( $post_id ) === absint( $GLOBALS['di_page']['ID'] ) ? $GLOBALS['di_page']['post_type'] : false;
}

function get_post_status( $post_id ) {
	return absint( $post_id ) === absint( $GLOBALS['di_page']['ID'] ) ? $GLOBALS['di_page']['post_status'] : false;
}

function get_post_field( $field, $post_id ) {
	return absint( $post_id ) === absint( $GLOBALS['di_page']['ID'] ) ? ( $GLOBALS['di_page'][ $field ] ?? null ) : null;
}

function get_page_uri( $post_id ) {
	return absint( $post_id ) === absint( $GLOBALS['di_page']['ID'] ) ? $GLOBALS['di_page']['page_uri'] : false;
}

function post_password_required( $post_id = 0 ) {
	return '' !== (string) get_post_field( 'post_password', $post_id );
}

function get_permalink( $post_id ) {
	return absint( $post_id ) === absint( $GLOBALS['di_page']['ID'] )
		? 'https://example.test/' . trim( $GLOBALS['di_page']['page_uri'], '/' ) . '/'
		: false;
}

function home_url( $path = '/' ) {
	return 'https://example.test' . ( '/' === $path ? '/' : $path );
}

function sanitize_key( $value ) {
	return strtolower( preg_replace( '/[^a-z0-9_-]/', '', (string) $value ) );
}

function sanitize_text_field( $value ) {
	$value = strip_tags( (string) $value );
	return trim( preg_replace( '/[\x00-\x1F\x7F]/u', '', $value ) );
}

function wp_unslash( $value ) {
	return $value;
}

function wp_parse_url( $value, $component = -1 ) {
	return parse_url( $value, $component );
}

function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['di_actions'][ $hook ][] = compact( 'callback', 'priority', 'accepted_args' );
}

function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	$GLOBALS['di_filters'][ $hook ][] = compact( 'callback', 'priority', 'accepted_args' );
}

function remove_action( $hook, $callback ) {
	$GLOBALS['di_removed_actions'][] = compact( 'hook', 'callback' );
	return true;
}

function esc_attr( $value ) {
	return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
}

function esc_url( $value ) {
	return (string) $value;
}

function esc_html( $value ) {
	return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
}

function wp_json_encode( $value, $flags = 0 ) {
	return json_encode( $value, $flags );
}

function register_setting( $group, $name, $arguments = array() ) {
	$GLOBALS['di_settings'][ $name ] = compact( 'group', 'arguments' );
}

function register_rest_route( $namespace, $route, $arguments ) {
	$GLOBALS['di_routes'][] = compact( 'namespace', 'route', 'arguments' );
}

function plugins_url( $path, $file ) {
	unset( $file );
	return '/wp-content/plugins/thailand-platform/' . ltrim( $path, '/' );
}

function wp_enqueue_style() {}
function wp_enqueue_script() {}
function wp_script_add_data() {}

function nocache_headers() {
	++$GLOBALS['di_nocache_count'];
}

function status_header( $status ) {
	$GLOBALS['di_status'][] = $status;
}

class WP_REST_Controller {
	public $namespace;
	public $rest_base;
}

class WP_REST_Server {
	const READABLE = 'GET';
}

class WP_Error {
	private $code;
	private $message;
	private $data;

	public function __construct( $code, $message, $data = array() ) {
		$this->code    = $code;
		$this->message = $message;
		$this->data    = $data;
	}

	public function get_error_code() {
		return $this->code;
	}

	public function get_error_message() {
		return $this->message;
	}

	public function get_error_data() {
		return $this->data;
	}
}

class WP_REST_Response {
	private $data;
	private $status;
	private $headers = array();

	public function __construct( $data = null, $status = 200 ) {
		$this->data   = $data;
		$this->status = $status;
	}

	public function header( $name, $value ) {
		$this->headers[ $name ] = $value;
	}

	public function remove_header( $name ) {
		unset( $this->headers[ $name ] );
	}

	public function get_data() {
		return $this->data;
	}

	public function get_status() {
		return $this->status;
	}

	public function get_headers() {
		return $this->headers;
	}
}

final class DI_Test_Request {
	private $params;
	private $query;
	private $body;
	private $json;
	private $method;
	private $route;

	public function __construct( $params, $query = array(), $body = '', $json = null, $method = 'GET', $route = '' ) {
		$this->params = $params;
		$this->query  = $query;
		$this->body   = $body;
		$this->json   = $json;
		$this->method = $method;
		$this->route  = $route;
	}

	public function get_param( $key ) {
		return $this->params[ $key ] ?? null;
	}

	public function get_query_params() {
		return $this->query;
	}

	public function get_body() {
		return $this->body;
	}

	public function get_json_params() {
		return $this->json;
	}

	public function get_method() {
		return $this->method;
	}

	public function get_route() {
		return $this->route;
	}
}

function di_assert( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

$class_files = array(
	'StrictJson.php',
	'ArtifactVerifier.php',
	'Repository.php',
	'PublicView.php',
	'FeatureFlag.php',
	'Context.php',
	'Privacy.php',
	'Seo.php',
	'Schema.php',
	'Assets.php',
	'Renderer.php',
	'View.php',
	'RestController.php',
	'HomepageNavigation.php',
	'Settings.php',
	'Module.php',
);
foreach ( $class_files as $class_file ) {
	require THAILAND_PLATFORM_DIR . 'src/DigitalIslands/' . $class_file;
}

use Thailand_Platform\DigitalIslands\ArtifactVerifier;
use Thailand_Platform\DigitalIslands\Context;
use Thailand_Platform\DigitalIslands\FeatureFlag;
use Thailand_Platform\DigitalIslands\Privacy;
use Thailand_Platform\DigitalIslands\PublicView;
use Thailand_Platform\DigitalIslands\Renderer;
use Thailand_Platform\DigitalIslands\Repository;
use Thailand_Platform\DigitalIslands\RestController;
use Thailand_Platform\DigitalIslands\Schema;
use Thailand_Platform\DigitalIslands\Seo;
use Thailand_Platform\DigitalIslands\HomepageNavigation;
use Thailand_Platform\DigitalIslands\StrictJson;

/* Strict manifest parsing and exact artifact receipt. */
$duplicate_rejected = false;
try {
	StrictJson::decode_object( '{"outer":{"same":1,"same":2}}' );
} catch ( RuntimeException $exception ) {
	$duplicate_rejected = false !== strpos( $exception->getMessage(), 'duplicate' );
}
di_assert( $duplicate_rejected, 'Duplicate JSON keys were accepted.' );

$manifest      = ArtifactVerifier::verify();
$artifact      = $manifest['artifacts'][ ArtifactVerifier::REGISTRY_PATH ];
$registry_path = THAILAND_PLATFORM_DIR . ArtifactVerifier::REGISTRY_PATH;
di_assert( ArtifactVerifier::CONTRACT_ID === $manifest['contract_id'], 'Manifest contract mismatch.' );
di_assert( ArtifactVerifier::PUBLICATION_STATE_PUBLIC === $manifest['publication_state'], 'Manifest is not reviewed Live.' );
di_assert( 49 === $manifest['counts']['public_map_entities'], 'Manifest public projection count changed.' );
di_assert( filesize( $registry_path ) === $artifact['bytes'], 'Registry byte count mismatch.' );
di_assert( hash_file( 'sha256', $registry_path ) === $artifact['sha256'], 'Registry SHA-256 mismatch.' );

$registry = Repository::all();
di_assert( Repository::is_loaded(), 'Registry did not load.' );
di_assert( 49 === count( $registry['public_map_entities']), 'Public projection is not exact.' );
di_assert( $registry['public_map_entities'] === $registry['canary_map_entities'], 'Live and reviewed Canary projections differ.' );
di_assert( 49 === count( $registry['entities_by_id'] ), 'Public source entity count changed unexpectedly.' );

/* The second allowlist contains only map-safe Canary entities. */
$payload       = PublicView::entities_payload( '2026-08-11' );
$payload_json  = json_encode( $payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
$entity_types  = array_count_values( array_column( $payload['entities'], 'entity_type' ) );
di_assert( 49 === $payload['entity_count'], 'Canary entity count mismatch.' );
di_assert( 14 === ( $entity_types['settlement'] ?? 0 ), 'The 14 official mubans are not present.' );
di_assert( 7 === ( $entity_types['landmark'] ?? 0 ), 'The seven orientation landmarks are not present.' );
di_assert( 4 === ( $entity_types['road'] ?? 0 ), 'The four road corridors are not present.' );
di_assert( 1 === ( $entity_types['education'] ?? 0 ), 'The public-school record is not present.' );
di_assert( 1 === ( $entity_types['telecom'] ?? 0 ), 'The held-pin telecom card is not present.' );
di_assert( 3 === ( $entity_types['property_project'] ?? 0 ), 'The three map-only project candidates are not present.' );
di_assert( ! isset( $entity_types['property_offer'] ), 'Review-held offers entered the map-safe projection.' );
di_assert( ! isset( $entity_types['professional_service'] ), 'Review-held professionals entered the map-safe projection.' );
foreach ( array( 'holds', 'conflicts', 'source_ids', 'property_offer:th:', 'legal_overlay:th:', 'professional_service' ) as $private_marker ) {
	di_assert( false === strpos( $payload_json, $private_marker ), 'Private marker leaked: ' . $private_marker );
}

$null_settlement_points = 0;
foreach ( $payload['entities'] as $entity ) {
	if ( 'settlement' === $entity['entity_type'] && null === $entity['coordinates'] ) {
		++$null_settlement_points;
	}
}
di_assert( 4 === $null_settlement_points, 'Missing settlement coordinates were invented or lost.' );

$after_price_review = PublicView::entities_payload( '2026-08-26' );
di_assert( 49 === $after_price_review['entity_count'], 'Review dates silently changed the verified projection count.' );

/* Off, exact-object Canary, and published Live identities all fail closed. */
di_assert( array( 'off', 'canary', 'live' ) === FeatureFlag::allowed_modes(), 'Feature modes changed.' );
di_assert( FeatureFlag::MODE_LIVE === FeatureFlag::sanitize( 'live' ), 'Live state was rejected.' );
di_assert( FeatureFlag::MODE_OFF === FeatureFlag::mode(), 'Default mode is not Off.' );

$canonical_path = Repository::canonical_path();
$encoded_page_uri = strtolower( rawurlencode( trim( $canonical_path, '/' ) ) );
$GLOBALS['di_page']['page_uri'] = $encoded_page_uri;
$GLOBALS['di_options'][ FeatureFlag::PAGE_ID_OPTION ] = $GLOBALS['di_page']['ID'];
$GLOBALS['di_options'][ FeatureFlag::OPTION ] = FeatureFlag::MODE_CANARY;
di_assert( false !== strpos( $encoded_page_uri, '%d7' ), 'The page URI fixture does not emulate WordPress UTF-8 slug storage.' );
di_assert( $canonical_path === Context::stored_page_uri_path( $encoded_page_uri ), 'A WordPress-encoded Hebrew page URI did not decode to the canonical path.' );
foreach (
	array(
		$encoded_page_uri . '%zz',
		$encoded_page_uri . '%2fchild',
		'%2e%2e/' . $encoded_page_uri,
		$encoded_page_uri . '%00',
		str_replace( '%', '%25', $encoded_page_uri ),
		$encoded_page_uri . chr( 1 ),
	) as $unsafe_page_uri
) {
	di_assert( '' === Context::stored_page_uri_path( $unsafe_page_uri ), 'An unsafe stored page URI was accepted: ' . bin2hex( $unsafe_page_uri ) );
}
$_SERVER['REQUEST_URI'] = $canonical_path . '?preview=true';
$GLOBALS['di_admin'] = false;
di_assert( ! FeatureFlag::request_is_authorized(), 'A non-administrator entered Canary.' );
$GLOBALS['di_admin'] = true;
di_assert( FeatureFlag::request_is_authorized(), 'An administrator could not enter Canary.' );

$_SERVER['REQUEST_URI'] = $canonical_path;
di_assert( Context::is_route_request(), 'The exact canonical path did not match.' );
di_assert( Context::is_authorized_canary(), 'The exact administrator route was not authorized.' );
$_SERVER['REQUEST_URI'] = $canonical_path . '?utm_source=test';
di_assert( Context::is_route_request(), 'An ordinary query parameter changed page identity.' );
$GLOBALS['di_queried_id'] = 999;
di_assert( ! Context::is_authorized_canary(), 'Canary took over a same-path wrong object.' );
$GLOBALS['di_queried_id'] = $GLOBALS['di_page']['ID'];
$_SERVER['REQUEST_URI'] = $canonical_path . '%2F';
di_assert( ! Context::is_route_request(), 'An encoded slash route variant was accepted.' );
$_SERVER['REQUEST_URI'] = $canonical_path;

$privacy = new Privacy();
$headers = $privacy->headers( array( 'X-Test' => 'preserved' ) );
di_assert( 'private, no-store, max-age=0' === $headers['Cache-Control'], 'Canary page cache policy mismatch.' );
di_assert( 'noindex, nofollow, noarchive' === $headers['X-Robots-Tag'], 'Canary page robots header mismatch.' );
di_assert( 'preserved' === $headers['X-Test'], 'Canary headers removed an unrelated value.' );
di_assert( Renderer::ready(), 'The exact runtime artifact set is not ready.' );

/* REST routes are capability-bound, read-only and bodyless. */
$controller = new RestController(
	static function () {
		return '2026-08-11';
	}
);
$GLOBALS['di_routes'] = array();
$controller->register_routes();
di_assert( 5 === count( $GLOBALS['di_routes'] ), 'Authorized Canary REST route count mismatch.' );
foreach ( $GLOBALS['di_routes'] as $route ) {
	di_assert( RestController::REST_NAMESPACE === $route['namespace'], 'REST namespace mismatch.' );
	di_assert( WP_REST_Server::READABLE === $route['arguments']['methods'], 'A Digital Islands route is not GET-only.' );
	di_assert( is_callable( $route['arguments']['permission_callback'] ), 'A route lacks a permission callback.' );
}

$base_params = array( 'island_id' => RestController::ISLAND_ID );
$base_route  = '/' . RestController::REST_NAMESPACE . '/digital-islands/' . RestController::ISLAND_ID;
$request     = new DI_Test_Request( $base_params, array(), '', null, 'GET', $base_route );
$response    = $controller->respond_island( $request );
di_assert( $response instanceof WP_REST_Response && 200 === $response->get_status(), 'Island endpoint failed.' );
di_assert( 'private, no-store, max-age=0' === $response->get_headers()['Cache-Control'], 'REST response is cacheable.' );
di_assert( ! isset( $response->get_headers()['ETag'] ), 'Private Canary emitted an ETag.' );
di_assert( 'private_canary' === $response->get_data()['representation_state'], 'REST representation state mismatch.' );
$island_data = $response->get_data();
di_assert( 3 === count( $island_data['official_tools'] ), 'Official land tools are missing.' );
di_assert( 'dependency_pending' === $island_data['renderers'][0]['state'], 'CesiumJS is presented as ready without pinned local dependencies.' );
di_assert( 'dependency_pending' === $island_data['renderers'][1]['state'], 'MapLibre is presented as ready without pinned local dependencies.' );
di_assert( false === $island_data['decision_policy']['automatic_buildability_verdict'], 'The Canary emits an automatic buildability verdict.' );
di_assert( in_array( 'parcel_reference_match', $island_data['decision_policy']['required_dimensions'], true ), 'The parcel verification dimension is missing.' );
foreach ( $island_data['official_tools'] as $official_tool ) {
	di_assert( 0 === strpos( $official_tool['url'], 'https://' ), 'An official tool URL is not HTTPS.' );
	di_assert( array() !== $official_tool['limitations_he'], 'An official tool lacks limitations.' );
}

$entities_response = $controller->respond_entities( $request );
di_assert( 49 === $entities_response->get_data()['entity_count'], 'Entities endpoint count mismatch.' );

$safe_item_request = new DI_Test_Request(
	array_merge( $base_params, array( 'entity_id' => 'property_project:th:84:840501:nava-koh-phangan' ) )
);
$safe_item = $controller->respond_entity( $safe_item_request );
di_assert( $safe_item instanceof WP_REST_Response, 'A map-safe entity was not returned.' );

$body_error = $controller->respond_island( new DI_Test_Request( $base_params, array(), '{"x":1}', null ) );
di_assert( $body_error instanceof WP_Error && 'rest_digital_islands_get_body_forbidden' === $body_error->get_error_code(), 'A GET body was accepted.' );
$json_error = $controller->respond_island( new DI_Test_Request( $base_params, array(), '', array( 'x' => 1 ) ) );
di_assert( $json_error instanceof WP_Error && 'rest_digital_islands_get_json_forbidden' === $json_error->get_error_code(), 'GET JSON parameters were accepted.' );
$query_error = $controller->respond_island( new DI_Test_Request( $base_params, array( 'view' => '3d' ) ) );
di_assert( $query_error instanceof WP_Error && 'rest_digital_islands_query_state_forbidden' === $query_error->get_error_code(), 'Query-string map state was accepted.' );

$search_request = new DI_Test_Request( array_merge( $base_params, array( 'term' => 'NAVA' ) ) );
$search_response = $controller->respond_search( $search_request );
di_assert( $search_response instanceof WP_REST_Response, 'Search endpoint failed.' );
di_assert( 1 === $search_response->get_data()['result_count'], 'Stable English-name search mismatch.' );

$missing_item_request = new DI_Test_Request(
	array_merge( $base_params, array( 'entity_id' => 'service:th:84:8405:not-reviewed' ) )
);
$missing_item = $controller->respond_entity( $missing_item_request );
di_assert( $missing_item instanceof WP_Error && 'rest_no_route' === $missing_item->get_error_code(), 'An absent record was distinguishable.' );

/* Live stays hidden while page identity is draft/missing and never gets public cache headers. */
$GLOBALS['di_options'][ FeatureFlag::OPTION ] = FeatureFlag::MODE_LIVE;
$GLOBALS['di_admin'] = false;
di_assert( ! Context::public_api_ready(), 'A draft page opened the public API.' );
di_assert( 'rest_no_route' === $controller->permission_check()->get_error_code(), 'Draft Live registered a public route.' );
( new Context() )->protect_canary();
di_assert( 404 === end( $GLOBALS['di_status'] ), 'A not-ready Live page did not fail closed as 404.' );
$hidden = new WP_REST_Response( array( 'code' => 'rest_no_route' ), 404 );
$controller->protect_dispatch( $hidden, null, new DI_Test_Request( array(), array(), '', null, 'GET', $base_route ) );
di_assert( 'private, no-store, max-age=0' === $hidden->get_headers()['Cache-Control'], 'A hidden Live error became publicly cacheable.' );

$GLOBALS['di_page']['post_status'] = 'publish';
$_SERVER['REQUEST_URI'] = $canonical_path . '?utm_source=test';
di_assert( Context::public_api_ready(), 'The exact published page did not open the public API.' );
di_assert( Context::is_live_request(), 'Tracking parameters broke exact Live identity.' );
di_assert( FeatureFlag::request_is_authorized(), 'Anonymous public REST authorization failed.' );
$GLOBALS['di_page']['post_password'] = 'secret';
di_assert( ! Context::public_api_ready(), 'A stored page password did not fail closed.' );
$GLOBALS['di_page']['post_password'] = '';

$GLOBALS['di_routes'] = array();
$controller->register_routes();
di_assert( 5 === count( $GLOBALS['di_routes'] ), 'Live REST routes were not registered.' );
$live_response = $controller->respond_island( $request );
di_assert( 'public, max-age=300, stale-while-revalidate=60' === $live_response->get_headers()['Cache-Control'], 'Live REST cache policy mismatch.' );
di_assert( 'public_live' === $live_response->get_data()['representation_state'], 'Live representation state mismatch.' );
di_assert( 'https://www.openstreetmap.org/copyright' === $live_response->get_data()['attribution']['url'], 'REST attribution URL is missing.' );
$live_entities = $controller->respond_entities( $request )->get_data();
di_assert( 49 === $live_entities['entity_count'], 'Live REST projection is not exactly 49.' );
foreach ( $live_entities['entities'] as $entity ) {
	di_assert( array() !== $entity['evidence'], 'A Live entity has no accessible evidence.' );
	if ( is_array( $entity['coordinates'] ) ) {
		di_assert( array() !== $entity['coordinates']['evidence'], 'A Live pin has no geometry evidence.' );
	}
	foreach ( $entity['facts'] as $fact ) {
		di_assert( array() !== $fact['evidence'], 'A Live fact has no evidence.' );
	}
}

/* The Live owner has one canonical/meta/schema graph and a direct Home link. */
$seo = new Seo();
di_assert( Seo::TITLE === $seo->title( 'old' ), 'Live title owner did not replace the title.' );
di_assert( Seo::DESCRIPTION === $seo->description( 'old' ), 'Live meta description owner did not replace the value.' );
di_assert( home_url( $canonical_path ) === $seo->canonical( 'old' ), 'Live self-canonical mismatch.' );
di_assert( array() === $seo->yoast_schema_graph( array( array( '@type' => 'WebPage' ) ) ), 'Yoast graph was not suppressed on the owned Live page.' );
di_assert( 0 === $seo->saswp_schema_gate( 1 ), 'SASWP graph was not suppressed on the owned Live page.' );
$sitemap_object = (object) array( 'ID' => $GLOBALS['di_page']['ID'] );
$sitemap_entry  = $seo->sitemap_entry( array( 'loc' => 'old', 'mod' => 'old' ), 'post', $sitemap_object );
di_assert( home_url( $canonical_path ) === $sitemap_entry['loc'], 'Live sitemap child URL mismatch.' );
di_assert( Repository::checked_on() . 'T00:00:00+00:00' === $sitemap_entry['mod'], 'Live sitemap child modified date mismatch.' );
di_assert( false === $seo->sitemap_entry( false, 'post', $sitemap_object ), 'Live sitemap filter resurrected a prior exclusion.' );
$unrelated_sitemap_object = (object) array( 'ID' => 999 );
di_assert(
	array( 'loc' => 'unrelated' ) === $seo->sitemap_entry( array( 'loc' => 'unrelated' ), 'post', $unrelated_sitemap_object ),
	'Digital Islands changed an unrelated sitemap entry.'
);

$GLOBALS['di_options'][ FeatureFlag::OPTION ] = FeatureFlag::MODE_CANARY;
di_assert( false === $seo->sitemap_entry( array( 'loc' => 'hidden' ), 'post', $sitemap_object ), 'Canary page remained in the public sitemap.' );
$GLOBALS['di_options'][ FeatureFlag::OPTION ] = FeatureFlag::MODE_OFF;
di_assert( false === $seo->sitemap_entry( array( 'loc' => 'hidden' ), 'post', $sitemap_object ), 'Off page remained in the public sitemap.' );
$GLOBALS['di_options'][ FeatureFlag::OPTION ] = FeatureFlag::MODE_LIVE;
$GLOBALS['di_page']['post_status'] = 'draft';
di_assert( false === $seo->sitemap_entry( array( 'loc' => 'hidden' ), 'post', $sitemap_object ), 'Not-ready Live page remained in the public sitemap.' );
di_assert(
	array( 'loc' => 'unrelated' ) === $seo->sitemap_entry( array( 'loc' => 'unrelated' ), 'post', $unrelated_sitemap_object ),
	'Not-ready Digital Islands state changed an unrelated sitemap entry.'
);
$GLOBALS['di_page']['post_status'] = 'publish';
ob_start();
$seo->fallback_meta();
$fallback_meta = ob_get_clean();
di_assert( 1 === substr_count( $fallback_meta, '<meta name="description"' ), 'Yoast-absent description was not emitted exactly once.' );
di_assert( 1 === substr_count( $fallback_meta, '<link rel="canonical"' ), 'Yoast-absent canonical was not emitted exactly once.' );
ob_start();
$seo->fallback_meta();
di_assert( '' === ob_get_clean(), 'Fallback metadata was emitted twice.' );

$schema = Schema::graph();
$schema_json = json_encode( $schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES );
di_assert( 1 === substr_count( $schema_json, 'BreadcrumbList' ), 'The Digital Islands graph does not own exactly one BreadcrumbList.' );
$breadcrumb = $schema['@graph'][2]['itemListElement'];
di_assert( isset( $breadcrumb[0]['item'] ), 'Home breadcrumb is not linked.' );
di_assert( ! isset( $breadcrumb[1]['item'] ), 'Planned Thailand Map breadcrumb linked to a nonexistent page.' );
di_assert( home_url( $canonical_path ) === $breadcrumb[2]['item'], 'Current breadcrumb URL mismatch.' );

$home_markup = file_get_contents( THAILAND_PLATFORM_DIR . 'resources/homepage.html' );
$home_navigation = new HomepageNavigation();
$live_home = $home_navigation->inject( $home_markup );
di_assert( false !== strpos( $live_home, 'data-thp-digital-island-home-link="koh-phangan-map"' ), 'Homepage lacks the direct Live child link.' );
di_assert( false !== strpos( $live_home, 'href="' . home_url( $canonical_path ) . '"' ), 'Homepage child URL mismatch.' );

$GLOBALS['di_queried_id'] = 999;
di_assert( 'old' === $seo->title( 'old' ), 'SEO title changed on another page.' );
di_assert( array( 'keep' ) === $seo->yoast_schema_graph( array( 'keep' ) ), 'Yoast graph changed on another page.' );
di_assert( 7 === $seo->saswp_schema_gate( 7 ), 'SASWP graph changed on another page.' );
$GLOBALS['di_queried_id'] = $GLOBALS['di_page']['ID'];

$GLOBALS['di_options'][ FeatureFlag::OPTION ] = FeatureFlag::MODE_OFF;
$GLOBALS['di_routes'] = array();
$controller->register_routes();
di_assert( array() === $GLOBALS['di_routes'], 'Off mode registered private REST routes.' );
di_assert( 'rest_no_route' === $controller->permission_check()->get_error_code(), 'Off mode did not return an indistinguishable route miss.' );

/* Static fallback, adapter seams, privacy, and write-free source gates. */
$template_path = THAILAND_PLATFORM_DIR . 'templates/digital-islands/koh-phangan.php';
$css_path      = THAILAND_PLATFORM_DIR . 'assets/digital-islands/digital-islands.css';
$js_path       = THAILAND_PLATFORM_DIR . 'assets/digital-islands/digital-islands.js';
$template      = file_get_contents( $template_path );
$css           = file_get_contents( $css_path );
$js            = file_get_contents( $js_path );

di_assert( false !== strpos( $template, '<html lang="he" dir="rtl">' ), 'Template is not Hebrew-first RTL.' );
di_assert( false !== strpos( $template, 'data-entity-card' ) && false !== strpos( $template, '<ul class="thp-di-cards"' ), 'Static accessible entity list is missing.' );
di_assert( false !== strpos( $template, 'data-renderer-status role="status" aria-live="polite"' ), 'Renderer status is not announced.' );
di_assert( false !== strpos( $template, 'data-decision-dimension' ), 'The land decision checklist is missing.' );
di_assert( false !== strpos( $template, 'data-official-tool' ) && false !== strpos( $template, 'rel="noopener noreferrer external"' ), 'Official land tools are not rendered safely.' );
di_assert( 1 === substr_count( $template, 'https://www.openstreetmap.org/copyright' ), 'Persistent OSM attribution link is missing or duplicated.' );
di_assert( false !== strpos( $template, '© OpenStreetMap contributors' ), 'Visible OSM attribution text is missing.' );
di_assert( false !== strpos( $template, 'thp-di-evidence-links' ), 'Accessible evidence links are not rendered.' );
di_assert( false !== strpos( $js, 'class CesiumAdapter' ) && false !== strpos( $js, 'window.Cesium' ), 'CesiumJS adapter seam is missing.' );
di_assert( false !== strpos( $js, 'class MapLibreAdapter' ) && false !== strpos( $js, 'window.maplibregl' ), 'MapLibre adapter seam is missing.' );
di_assert( false !== strpos( $js, 'navigator.connection.saveData' ), 'Data-saver fallback is missing.' );
di_assert( false !== strpos( $js, 'prefers-reduced-motion: reduce' ), 'Reduced-motion fallback is missing.' );
di_assert( false !== strpos( $js, "getContext('webgl2')" ), 'WebGL capability fallback is missing.' );
di_assert( false !== strpos( $js, 'window.location.hash' ) && false === strpos( $js, 'window.location.search' ), 'Client state is not fragment-only.' );
di_assert( false !== strpos( $js, "method: 'GET'" ) && false === strpos( $js, 'body:' ), 'Client GET contract contains a body.' );
di_assert( false !== strpos( $js, "if (nonce)" ) && false !== strpos( $js, "credentials: nonce ? 'same-origin' : 'omit'" ), 'Live nonce/cookie omission is missing.' );
di_assert( false !== strpos( $css, '@media (prefers-reduced-motion: reduce)' ), 'CSS reduced-motion support is missing.' );

foreach ( array( $template, $css, $js ) as $asset_source ) {
	di_assert( 0 === preg_match( '/[\x{2013}\x{2014}]/u', $asset_source ), 'A public asset contains a forbidden long dash.' );
}
di_assert( 0 === preg_match( '#https?://#i', $css . $js ), 'A bundled runtime asset contains an external URL.' );

$runtime_paths = glob( THAILAND_PLATFORM_DIR . 'src/DigitalIslands/*.php' );
foreach ( array_merge( $runtime_paths, array( $template_path, $css_path, $js_path, __FILE__ ) ) as $path ) {
	$source = file_get_contents( $path );
	di_assert( is_string( $source ) && '' !== $source, 'A Digital Islands runtime file is unreadable.' );
	di_assert( 0 === preg_match( '/[\x{2013}\x{2014}]/u', $source ), 'A runtime file contains a forbidden long dash.' );
}
$read_only_runtime_paths = array(
	THAILAND_PLATFORM_DIR . 'src/DigitalIslands/ArtifactVerifier.php',
	THAILAND_PLATFORM_DIR . 'src/DigitalIslands/Repository.php',
	THAILAND_PLATFORM_DIR . 'src/DigitalIslands/PublicView.php',
	THAILAND_PLATFORM_DIR . 'src/DigitalIslands/RestController.php',
);
foreach ( $read_only_runtime_paths as $path ) {
	$source = file_get_contents( $path );
	foreach ( array( '$wpdb', 'WP_Query', 'get_posts(', 'post_content', 'wp_insert_post(', 'update_option(', 'file_put_contents(' ) as $forbidden ) {
		di_assert( false === strpos( $source, $forbidden ), 'Read-only runtime contains forbidden storage or draft dependency: ' . basename( $path ) );
	}
}

fwrite( STDOUT, "PASS: Digital Islands runtime (Canary and Live)\n" );
