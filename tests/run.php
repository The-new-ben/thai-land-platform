<?php
/**
 * Dependency-free bootstrap contract tests.
 */

define( 'ABSPATH', __DIR__ . '/wordpress/' );

$GLOBALS['wp_version']             = '7.0.3';
$GLOBALS['tl_test_actions']        = array();
$GLOBALS['tl_test_routes']         = array();
$GLOBALS['tl_test_activation']     = array();
$GLOBALS['tl_test_deactivation']   = array();

function plugin_dir_path( $file ) {
	return rtrim( dirname( $file ), '/\\' ) . DIRECTORY_SEPARATOR;
}

function register_activation_hook( $file, $callback ) {
	$GLOBALS['tl_test_activation'][ $file ] = $callback;
}

function register_deactivation_hook( $file, $callback ) {
	$GLOBALS['tl_test_deactivation'][ $file ] = $callback;
}

function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	if ( ! isset( $GLOBALS['tl_test_actions'][ $hook ] ) ) {
		$GLOBALS['tl_test_actions'][ $hook ] = array();
	}

	$GLOBALS['tl_test_actions'][ $hook ][] = array(
		'callback'      => $callback,
		'priority'      => $priority,
		'accepted_args' => $accepted_args,
	);
}

function register_rest_route( $namespace, $route, $arguments ) {
	$GLOBALS['tl_test_routes'][ $namespace . $route ] = $arguments;
	return true;
}

function __return_true() {
	return true;
}

function esc_html__( $text, $domain = null ) {
	unset( $domain );
	return $text;
}

function esc_html( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function wp_die( $message ) {
	throw new RuntimeException( (string) $message );
}

final class WP_REST_Response {
	private $data;
	private $status;
	private $headers = array();

	public function __construct( $data, $status = 200 ) {
		$this->data   = $data;
		$this->status = $status;
	}

	public function header( $name, $value ) {
		$this->headers[ $name ] = $value;
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

final class WP_REST_Server {
	const READABLE = 'GET';
}

function tl_test_assert( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

function tl_test_do_action( $hook ) {
	$callbacks = isset( $GLOBALS['tl_test_actions'][ $hook ] )
		? $GLOBALS['tl_test_actions'][ $hook ]
		: array();

	usort(
		$callbacks,
		static function ( $left, $right ) {
			return $left['priority'] <=> $right['priority'];
		}
	);

	foreach ( $callbacks as $registration ) {
		call_user_func( $registration['callback'] );
	}
}

require dirname( __DIR__ ) . '/thailand-platform.php';

tl_test_assert( '0.1.0' === THAILAND_PLATFORM_VERSION, 'Version constant mismatch.' );
tl_test_assert( isset( $GLOBALS['tl_test_activation'][ THAILAND_PLATFORM_FILE ] ), 'Activation hook missing.' );
tl_test_assert( isset( $GLOBALS['tl_test_deactivation'][ THAILAND_PLATFORM_FILE ] ), 'Deactivation hook missing.' );

call_user_func( $GLOBALS['tl_test_activation'][ THAILAND_PLATFORM_FILE ] );
tl_test_do_action( 'plugins_loaded' );
tl_test_do_action( 'plugins_loaded' );

tl_test_assert( 1 === count( $GLOBALS['tl_test_actions']['rest_api_init'] ), 'Duplicate REST hook registered.' );
tl_test_assert( 1 === count( $GLOBALS['tl_test_actions']['init'] ), 'Duplicate update hook registered.' );
tl_test_assert( 5 === $GLOBALS['tl_test_actions']['init'][0]['priority'], 'Update checker priority mismatch.' );
tl_test_do_action( 'init' );
tl_test_assert( false === THAILAND_PLATFORM_ENABLE_UPDATE_CHECKER, 'Canary update checker must remain disabled.' );
tl_test_assert(
	! class_exists( '\\YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory', false ),
	'Canary unexpectedly loaded Plugin Update Checker.'
);
tl_test_do_action( 'rest_api_init' );

$route_key = 'thailand-platform/v1/health';
tl_test_assert( isset( $GLOBALS['tl_test_routes'][ $route_key ] ), 'Health route missing.' );

$route = $GLOBALS['tl_test_routes'][ $route_key ];
tl_test_assert( 'GET' === $route['methods'], 'Health route is not GET-only.' );
tl_test_assert( '__return_true' === $route['permission_callback'], 'Health permission callback mismatch.' );

$response = call_user_func( $route['callback'] );
$data     = $response->get_data();
$headers  = $response->get_headers();

tl_test_assert( 200 === $response->get_status(), 'Health response status mismatch.' );
tl_test_assert( 'thailand-platform' === $data['name'], 'Health response name mismatch.' );
tl_test_assert( THAILAND_PLATFORM_VERSION === $data['version'], 'Health response version mismatch.' );
tl_test_assert( 'ok' === $data['status'], 'Health response state mismatch.' );
tl_test_assert( 'no-store' === $headers['Cache-Control'], 'Health response cache policy mismatch.' );
tl_test_assert( 3 === count( $data ), 'Health response exposed unexpected fields.' );

$GLOBALS['wp_version'] = '6.8.7';
$activation_rejected   = false;

try {
	call_user_func( $GLOBALS['tl_test_activation'][ THAILAND_PLATFORM_FILE ] );
} catch ( RuntimeException $exception ) {
	$activation_rejected = false !== strpos( $exception->getMessage(), 'WordPress 6.9' );
}

tl_test_assert( $activation_rejected, 'Unsupported WordPress activation was not rejected.' );

fwrite( STDOUT, "PASS: Thailand Platform bootstrap contract\n" );
