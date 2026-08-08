<?php
/**
 * Dependency-free Thailand Platform bootstrap and homepage contract tests.
 */

define( 'ABSPATH', __DIR__ . '/wordpress/' );

$GLOBALS['wp_version']                 = '7.0.3';
$GLOBALS['tl_test_actions']            = array();
$GLOBALS['tl_test_filters']            = array();
$GLOBALS['tl_test_routes']             = array();
$GLOBALS['tl_test_activation']         = array();
$GLOBALS['tl_test_deactivation']       = array();
$GLOBALS['tl_test_options']            = array();
$GLOBALS['tl_test_registered_settings'] = array();
$GLOBALS['tl_test_options_pages']      = array();
$GLOBALS['tl_test_styles']             = array();
$GLOBALS['tl_test_scripts']            = array();
$GLOBALS['tl_test_script_data']        = array();
$GLOBALS['tl_test_inline_scripts']      = array();
$GLOBALS['tl_test_is_front_page']      = true;
$GLOBALS['tl_test_capabilities']       = array();
$GLOBALS['tl_test_status_headers']     = array();
$GLOBALS['tl_test_nocache_calls']      = 0;
$GLOBALS['tl_test_cache_flush_calls']  = 0;

function plugin_dir_path( $file ) {
	return rtrim( dirname( $file ), '/\\' ) . DIRECTORY_SEPARATOR;
}

function plugins_url( $path, $plugin_file = '' ) {
	unset( $plugin_file );
	return 'https://example.test/wp-content/plugins/thailand-platform/' . ltrim( $path, '/' );
}

function register_activation_hook( $file, $callback ) {
	$GLOBALS['tl_test_activation'][ $file ] = $callback;
}

function register_deactivation_hook( $file, $callback ) {
	$GLOBALS['tl_test_deactivation'][ $file ] = $callback;
}

function tl_test_register_hook( $registry, $hook, $callback, $priority, $accepted_args ) {
	if ( ! isset( $GLOBALS[ $registry ][ $hook ] ) ) {
		$GLOBALS[ $registry ][ $hook ] = array();
	}

	$GLOBALS[ $registry ][ $hook ][] = array(
		'callback'      => $callback,
		'priority'      => $priority,
		'accepted_args' => $accepted_args,
	);
}

function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	tl_test_register_hook( 'tl_test_actions', $hook, $callback, $priority, $accepted_args );
}

function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	tl_test_register_hook( 'tl_test_filters', $hook, $callback, $priority, $accepted_args );
}

function register_rest_route( $namespace, $route, $arguments ) {
	$GLOBALS['tl_test_routes'][ $namespace . $route ] = $arguments;
	return true;
}

function register_setting( $group, $option, $arguments = array() ) {
	$GLOBALS['tl_test_registered_settings'][ $option ] = array(
		'group'     => $group,
		'arguments' => $arguments,
	);
}

function add_options_page( $page_title, $menu_title, $capability, $slug, $callback ) {
	$GLOBALS['tl_test_options_pages'][ $slug ] = array(
		'page_title' => $page_title,
		'menu_title' => $menu_title,
		'capability' => $capability,
		'callback'   => $callback,
	);
	return $slug;
}

function get_option( $name, $default = false ) {
	return array_key_exists( $name, $GLOBALS['tl_test_options'] )
		? $GLOBALS['tl_test_options'][ $name ]
		: $default;
}

function sanitize_key( $value ) {
	$value = strtolower( (string) $value );
	return preg_replace( '/[^a-z0-9_\-]/', '', $value );
}

function sanitize_text_field( $value ) {
	return trim( strip_tags( (string) $value ) );
}

function wp_unslash( $value ) {
	return is_string( $value ) ? stripslashes( $value ) : $value;
}

function is_front_page() {
	return (bool) $GLOBALS['tl_test_is_front_page'];
}

function current_user_can( $capability ) {
	return ! empty( $GLOBALS['tl_test_capabilities'][ $capability ] );
}

function status_header( $status ) {
	$GLOBALS['tl_test_status_headers'][] = (int) $status;
}

function nocache_headers() {
	++$GLOBALS['tl_test_nocache_calls'];
}

function wp_enqueue_style( $handle, $source, $dependencies = array(), $version = false, $media = 'all' ) {
	$GLOBALS['tl_test_styles'][ $handle ] = array(
		'source'       => $source,
		'dependencies' => $dependencies,
		'version'      => $version,
		'media'        => $media,
	);
}

function wp_enqueue_script( $handle, $source, $dependencies = array(), $version = false, $in_footer = false ) {
	$GLOBALS['tl_test_scripts'][ $handle ] = array(
		'source'       => $source,
		'dependencies' => $dependencies,
		'version'      => $version,
		'in_footer'    => $in_footer,
	);
}

function wp_script_add_data( $handle, $key, $value ) {
	if ( ! isset( $GLOBALS['tl_test_script_data'][ $handle ] ) ) {
		$GLOBALS['tl_test_script_data'][ $handle ] = array();
	}
	$GLOBALS['tl_test_script_data'][ $handle ][ $key ] = $value;
	return true;
}

function wp_add_inline_script( $handle, $data, $position = 'after' ) {
	if ( ! isset( $GLOBALS['tl_test_inline_scripts'][ $handle ] ) ) {
		$GLOBALS['tl_test_inline_scripts'][ $handle ] = array();
	}

	$GLOBALS['tl_test_inline_scripts'][ $handle ][] = array(
		'data'     => $data,
		'position' => $position,
	);
	return true;
}

function wp_cache_flush() {
	++$GLOBALS['tl_test_cache_flush_calls'];
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

function esc_attr( $text ) {
	return htmlspecialchars( (string) $text, ENT_QUOTES, 'UTF-8' );
}

function esc_url( $url ) {
	return htmlspecialchars( (string) $url, ENT_QUOTES, 'UTF-8' );
}

function wp_json_encode( $value, $flags = 0, $depth = 512 ) {
	return json_encode( $value, $flags, $depth );
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

final class TL_Test_REST_Request {
	private $headers;

	public function __construct( $headers = array() ) {
		$this->headers = array();
		foreach ( $headers as $name => $value ) {
			$this->headers[ strtolower( (string) $name ) ] = (string) $value;
		}
	}

	public function get_header( $name ) {
		$key = strtolower( (string) $name );
		return isset( $this->headers[ $key ] ) ? $this->headers[ $key ] : '';
	}
}

final class TL_Test_Query {
	public $is_404 = false;

	public function set_404() {
		$this->is_404 = true;
	}
}

function tl_test_assert( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
}

function tl_test_sorted_hooks( $registry, $hook ) {
	$callbacks = isset( $GLOBALS[ $registry ][ $hook ] )
		? $GLOBALS[ $registry ][ $hook ]
		: array();

	usort(
		$callbacks,
		static function ( $left, $right ) {
			return $left['priority'] <=> $right['priority'];
		}
	);

	return $callbacks;
}

function tl_test_do_action( $hook ) {
	$arguments = array_slice( func_get_args(), 1 );

	foreach ( tl_test_sorted_hooks( 'tl_test_actions', $hook ) as $registration ) {
		call_user_func_array(
			$registration['callback'],
			array_slice( $arguments, 0, $registration['accepted_args'] )
		);
	}
}

function tl_test_apply_filters( $hook, $value ) {
	$arguments = array_slice( func_get_args(), 2 );

	foreach ( tl_test_sorted_hooks( 'tl_test_filters', $hook ) as $registration ) {
		$callback_arguments = array_merge( array( $value ), $arguments );
		$value              = call_user_func_array(
			$registration['callback'],
			array_slice( $callback_arguments, 0, $registration['accepted_args'] )
		);
	}

	return $value;
}

function tl_test_hook_count( $registry, $hook ) {
	return isset( $GLOBALS[ $registry ][ $hook ] ) ? count( $GLOBALS[ $registry ][ $hook ] ) : 0;
}

function tl_test_media_scope_violations( $css ) {
	$without_comments = preg_replace( '!/\*.*?\*/!s', '', $css );
	$depth            = 0;
	$media_depth      = null;
	$violations       = array();

	foreach ( preg_split( '/\R/', $without_comments ) as $line_number => $line ) {
		$trimmed = trim( $line );
		if ( '' === $trimmed ) {
			continue;
		}

		$is_media = 0 === strpos( $trimmed, '@media ' );
		if ( $is_media ) {
			$media_depth = $depth + 1;
		} elseif ( null !== $media_depth && $depth >= $media_depth && false !== strpos( $trimmed, '{' ) && 0 !== strpos( $trimmed, '@' ) ) {
			$selector_list = trim( substr( $trimmed, 0, strpos( $trimmed, '{' ) ) );
			foreach ( explode( ',', $selector_list ) as $selector ) {
				$selector = trim( $selector );
				if (
					'' !== $selector
					&& 1 !== preg_match(
						'/^(?:\.thp-home|body\.thailand-platform-home|html\.thailand-platform-document)(?:$|[\s.:#\[>+~])/',
						$selector
					)
				) {
					$violations[] = ( $line_number + 1 ) . ': ' . $selector;
				}
			}
		}

		$depth += substr_count( $trimmed, '{' );
		$depth -= substr_count( $trimmed, '}' );
		if ( null !== $media_depth && $depth < $media_depth ) {
			$media_depth = null;
		}
	}

	return $violations;
}

function tl_test_set_request( $mode, $front_page, $administrator, $canary_requested ) {
	$GLOBALS['tl_test_options']['thailand_platform_homepage_mode'] = $mode;
	$GLOBALS['tl_test_is_front_page']                              = $front_page;
	$GLOBALS['tl_test_capabilities']['manage_options']             = $administrator;
	$_GET                                                          = array();

	if ( $canary_requested ) {
		$_GET['thp_home_canary'] = '1';
	}
}

require dirname( __DIR__ ) . '/thailand-platform.php';

use Thailand_Platform\Homepage\Context;
use Thailand_Platform\Homepage\FeatureFlag;
use Thailand_Platform\Homepage\Renderer;
use Thailand_Platform\Homepage\Seo;
use Thailand_Platform\Geography\Repository as Geography_Repository;

tl_test_assert( '0.2.7' === THAILAND_PLATFORM_VERSION, 'Version constant mismatch.' );
tl_test_assert( isset( $GLOBALS['tl_test_activation'][ THAILAND_PLATFORM_FILE ] ), 'Activation hook missing.' );
tl_test_assert( isset( $GLOBALS['tl_test_deactivation'][ THAILAND_PLATFORM_FILE ] ), 'Deactivation hook missing.' );

call_user_func( $GLOBALS['tl_test_activation'][ THAILAND_PLATFORM_FILE ] );
tl_test_assert( array() === $GLOBALS['tl_test_options'], 'Activation unexpectedly wrote an option.' );
tl_test_assert( 0 === $GLOBALS['tl_test_cache_flush_calls'], 'Activation unexpectedly purged caches.' );
call_user_func( $GLOBALS['tl_test_deactivation'][ THAILAND_PLATFORM_FILE ] );
tl_test_assert( 1 === $GLOBALS['tl_test_cache_flush_calls'], 'Deactivation did not purge caches.' );
$GLOBALS['tl_test_cache_flush_calls'] = 0;

tl_test_do_action( 'plugins_loaded' );
tl_test_do_action( 'plugins_loaded' );

tl_test_assert( 2 === tl_test_hook_count( 'tl_test_actions', 'rest_api_init' ), 'REST hook registration count mismatch.' );
tl_test_assert( 1 === tl_test_hook_count( 'tl_test_actions', 'init' ), 'Duplicate update hook registered.' );
tl_test_assert( 1 === tl_test_hook_count( 'tl_test_actions', 'template_redirect' ), 'Canary protection hook mismatch.' );
tl_test_assert( 1 === tl_test_hook_count( 'tl_test_actions', 'wp_enqueue_scripts' ), 'Homepage enqueue hook mismatch.' );
tl_test_assert( 1 === tl_test_hook_count( 'tl_test_actions', 'wp_head' ), 'Homepage preload hook mismatch.' );
tl_test_assert( 1 === tl_test_hook_count( 'tl_test_actions', 'admin_init' ), 'Homepage setting hook mismatch.' );
tl_test_assert( 1 === tl_test_hook_count( 'tl_test_actions', 'admin_menu' ), 'Homepage settings page hook mismatch.' );
tl_test_assert( 1 === tl_test_hook_count( 'tl_test_actions', 'add_option_thailand_platform_homepage_mode' ), 'Homepage add-option cache hook mismatch.' );
tl_test_assert( 1 === tl_test_hook_count( 'tl_test_actions', 'update_option_thailand_platform_homepage_mode' ), 'Homepage update-option cache hook mismatch.' );
tl_test_assert( 1 === tl_test_hook_count( 'tl_test_filters', 'template_include' ), 'Homepage template filter mismatch.' );
tl_test_assert( 1 === tl_test_hook_count( 'tl_test_filters', 'body_class' ), 'Homepage body-class filter mismatch.' );
tl_test_assert( 1 === tl_test_hook_count( 'tl_test_filters', 'wp_robots' ), 'Homepage robots filter mismatch.' );
tl_test_assert( 1 === tl_test_hook_count( 'tl_test_filters', 'wpseo_robots' ), 'Yoast robots filter mismatch.' );
tl_test_assert( 1 === tl_test_hook_count( 'tl_test_filters', 'pre_get_document_title' ), 'Core homepage title filter mismatch.' );
tl_test_assert( 1 === tl_test_hook_count( 'tl_test_filters', 'wpseo_title' ), 'Yoast homepage title filter mismatch.' );
tl_test_assert( 1 === tl_test_hook_count( 'tl_test_filters', 'wpseo_metadesc' ), 'Yoast description filter mismatch.' );
tl_test_assert( 1 === tl_test_hook_count( 'tl_test_filters', 'wpseo_opengraph_title' ), 'Open Graph title filter mismatch.' );
tl_test_assert( 1 === tl_test_hook_count( 'tl_test_filters', 'wpseo_opengraph_desc' ), 'Open Graph description filter mismatch.' );
tl_test_assert( 1 === tl_test_hook_count( 'tl_test_filters', 'wpseo_opengraph_image' ), 'Open Graph image filter mismatch.' );
tl_test_assert( 1 === tl_test_hook_count( 'tl_test_filters', 'wpseo_opengraph_image_width' ), 'Open Graph image width filter mismatch.' );
tl_test_assert( 1 === tl_test_hook_count( 'tl_test_filters', 'wpseo_opengraph_image_height' ), 'Open Graph image height filter mismatch.' );
tl_test_assert( 1 === tl_test_hook_count( 'tl_test_filters', 'wpseo_twitter_title' ), 'Twitter title filter mismatch.' );
tl_test_assert( 1 === tl_test_hook_count( 'tl_test_filters', 'wpseo_twitter_description' ), 'Twitter description filter mismatch.' );
tl_test_assert( 1 === tl_test_hook_count( 'tl_test_filters', 'wpseo_twitter_image' ), 'Twitter image filter mismatch.' );
tl_test_assert( 1 === tl_test_hook_count( 'tl_test_filters', 'wp_headers' ), 'Homepage header filter mismatch.' );
tl_test_assert( 5 === $GLOBALS['tl_test_actions']['init'][0]['priority'], 'Update checker priority mismatch.' );
tl_test_assert( 99 === $GLOBALS['tl_test_filters']['template_include'][0]['priority'], 'Template filter priority mismatch.' );
tl_test_assert( 2 === $GLOBALS['tl_test_actions']['add_option_thailand_platform_homepage_mode'][0]['accepted_args'], 'Add-option cache hook argument count mismatch.' );
tl_test_assert( 3 === $GLOBALS['tl_test_actions']['update_option_thailand_platform_homepage_mode'][0]['accepted_args'], 'Update-option cache hook argument count mismatch.' );

tl_test_do_action( 'init' );
tl_test_assert( false === THAILAND_PLATFORM_ENABLE_UPDATE_CHECKER, 'Canary update checker must remain disabled.' );
tl_test_assert(
	! class_exists( '\\YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory', false ),
	'Canary unexpectedly loaded Plugin Update Checker.'
);

tl_test_do_action( 'rest_api_init' );
tl_test_assert( ! Geography_Repository::is_loaded(), 'Geography registry loaded before a geography request.' );
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

/* The public geography API must expose only the compiled 77-province spine. */
$geography_route_key = 'thailand-platform/v1/geography';
tl_test_assert( isset( $GLOBALS['tl_test_routes'][ $geography_route_key ] ), 'Geography route missing.' );
$geography_route = $GLOBALS['tl_test_routes'][ $geography_route_key ];
tl_test_assert( 'GET' === $geography_route['methods'], 'Geography route is not GET-only.' );
tl_test_assert( '__return_true' === $geography_route['permission_callback'], 'Geography permission callback mismatch.' );

$geography_response      = call_user_func( $geography_route['callback'] );
$geography_data          = $geography_response->get_data();
$geography_headers       = $geography_response->get_headers();
$geography_actual_keys   = array_keys( $geography_data );
$geography_expected_keys = array( 'schema_version', 'dataset_version', 'country', 'classification_schemes', 'regions', 'provinces' );
sort( $geography_actual_keys );
sort( $geography_expected_keys );
tl_test_assert( 200 === $geography_response->get_status(), 'Geography response status mismatch.' );
tl_test_assert( Geography_Repository::is_loaded(), 'Geography registry did not load for the geography request.' );
tl_test_assert(
	$geography_expected_keys === $geography_actual_keys,
	'Geography public response shape mismatch.'
);
tl_test_assert( 'geo:th:country' === $geography_data['country']['id'], 'Geography country identity mismatch.' );
tl_test_assert( 77 === count( $geography_data['provinces'] ), 'Geography payload does not contain 77 provinces.' );
tl_test_assert( 7 === count( $geography_data['regions'] ), 'Geography payload does not contain seven statistical regions.' );
tl_test_assert( 1 === preg_match( '/^"[0-9a-f]{64}"$/', $geography_headers['ETag'] ), 'Geography ETag is invalid.' );
tl_test_assert( false !== strpos( $geography_headers['Cache-Control'], 'max-age=86400' ), 'Geography cache policy mismatch.' );
tl_test_assert( 'nosniff' === $geography_headers['X-Content-Type-Options'], 'Geography content type protection missing.' );

$public_geography_json = wp_json_encode( $geography_data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
tl_test_assert( false !== $public_geography_json, 'Geography public response could not be encoded.' );
foreach ( array( 'aliases', 'indexes', 'relations_by_subject', 'source_ids', 'sources' ) as $internal_field ) {
	tl_test_assert( false === strpos( $public_geography_json, '"' . $internal_field . '"' ), 'Geography public response exposed an internal field: ' . $internal_field );
}

$conditional_request = new TL_Test_REST_Request( array( 'If-None-Match' => 'W/' . $geography_headers['ETag'] ) );
$conditional_response = call_user_func( $geography_route['callback'], $conditional_request );
tl_test_assert( 304 === $conditional_response->get_status(), 'Matching geography ETag did not return 304.' );
tl_test_assert( null === $conditional_response->get_data(), 'Geography 304 response returned a body.' );
tl_test_assert( $geography_headers['ETag'] === $conditional_response->get_headers()['ETag'], 'Geography 304 ETag changed.' );

$stale_request = new TL_Test_REST_Request( array( 'If-None-Match' => '"' . str_repeat( '0', 64 ) . '"' ) );
$stale_response = call_user_func( $geography_route['callback'], $stale_request );
tl_test_assert( 200 === $stale_response->get_status(), 'Stale geography ETag did not return the current payload.' );

$province_codes = array();
$province_slugs = array();
foreach ( $geography_data['provinces'] as $province ) {
	$province_codes[] = $province['external_ids']['moi_province_code'];
	$province_slugs[] = $province['slug'];
	tl_test_assert( 'geo:th:province:' . $province['external_ids']['moi_province_code'] === $province['id'], 'Province stable identity mismatch.' );
	tl_test_assert( is_bool( $province['priority'] ), 'Province priority is not boolean.' );
	tl_test_assert( 'geo:th:country' === $province['admin_parent_id'], 'Province administrative parent mismatch.' );
	tl_test_assert( 1 === count( $province['memberships'] ), 'Province classification membership count mismatch.' );
}
tl_test_assert( 77 === count( array_unique( $province_codes ) ), 'Province codes are not unique.' );
tl_test_assert( 77 === count( array_unique( $province_slugs ) ), 'Province slugs are not unique.' );

/* The administrator setting must be allowlisted and default to Off. */
tl_test_do_action( 'admin_init' );
tl_test_do_action( 'admin_menu' );
tl_test_assert( isset( $GLOBALS['tl_test_registered_settings'][ FeatureFlag::OPTION ] ), 'Homepage mode setting missing.' );
$setting = $GLOBALS['tl_test_registered_settings'][ FeatureFlag::OPTION ];
tl_test_assert( 'thailand_platform_homepage' === $setting['group'], 'Homepage setting group mismatch.' );
tl_test_assert( 'string' === $setting['arguments']['type'], 'Homepage setting type mismatch.' );
tl_test_assert( FeatureFlag::MODE_OFF === $setting['arguments']['default'], 'Homepage setting default is not Off.' );
tl_test_assert( is_callable( $setting['arguments']['sanitize_callback'] ), 'Homepage mode sanitizer missing.' );
tl_test_assert( isset( $GLOBALS['tl_test_options_pages']['thailand-platform'] ), 'Homepage settings page missing.' );
tl_test_assert(
	'manage_options' === $GLOBALS['tl_test_options_pages']['thailand-platform']['capability'],
	'Homepage settings page capability mismatch.'
);

$GLOBALS['tl_test_cache_flush_calls'] = 0;
tl_test_do_action(
	'add_option_' . FeatureFlag::OPTION,
	FeatureFlag::OPTION,
	FeatureFlag::MODE_CANARY
);
tl_test_assert( 1 === $GLOBALS['tl_test_cache_flush_calls'], 'First stored homepage mode did not purge caches.' );
tl_test_do_action(
	'update_option_' . FeatureFlag::OPTION,
	FeatureFlag::MODE_CANARY,
	FeatureFlag::MODE_CANARY,
	FeatureFlag::OPTION
);
tl_test_assert( 1 === $GLOBALS['tl_test_cache_flush_calls'], 'No-op homepage mode save purged caches.' );
tl_test_do_action(
	'update_option_' . FeatureFlag::OPTION,
	FeatureFlag::MODE_CANARY,
	FeatureFlag::MODE_LIVE,
	FeatureFlag::OPTION
);
tl_test_assert( 2 === $GLOBALS['tl_test_cache_flush_calls'], 'Homepage mode transition did not purge caches.' );

tl_test_assert(
	array( 'off', 'canary', 'live' ) === FeatureFlag::allowed_modes(),
	'Homepage mode allowlist mismatch.'
);
tl_test_assert( FeatureFlag::MODE_CANARY === FeatureFlag::sanitize( 'CANARY' ), 'Valid canary mode rejected.' );
tl_test_assert( FeatureFlag::MODE_LIVE === FeatureFlag::sanitize( 'live' ), 'Valid live mode rejected.' );
tl_test_assert( FeatureFlag::MODE_OFF === FeatureFlag::sanitize( 'publish-now' ), 'Invalid mode did not fail closed.' );
tl_test_assert( FeatureFlag::MODE_OFF === FeatureFlag::sanitize( array() ), 'Non-string mode did not fail closed.' );

/* Immutable homepage source and its release inventory. */
$root           = dirname( __DIR__ );
$node_binary    = getenv( 'THAILAND_PLATFORM_NODE_BINARY' );
if ( false === $node_binary || '' === trim( $node_binary ) ) {
	$node_binary = 'node';
}
$node_process = proc_open(
	array( $node_binary, $root . '/tests/tawk-state.test.js' ),
	array(
		0 => array( 'pipe', 'r' ),
		1 => array( 'pipe', 'w' ),
		2 => array( 'pipe', 'w' ),
	),
	$node_pipes,
	$root
);
tl_test_assert( is_resource( $node_process ), 'Could not start the Tawk behavior test.' );
fclose( $node_pipes[0] );
$node_stdout = stream_get_contents( $node_pipes[1] );
$node_stderr = stream_get_contents( $node_pipes[2] );
fclose( $node_pipes[1] );
fclose( $node_pipes[2] );
$node_status = proc_close( $node_process );
tl_test_assert( 0 === $node_status, 'Tawk behavior test failed: ' . trim( $node_stderr ) );
tl_test_assert( 'PASS: Tawk chat behavior' === trim( $node_stdout ), 'Unexpected Tawk behavior test output.' );

$geography_process = proc_open(
	array( PHP_BINARY, $root . '/tests/geography-resolver.test.php' ),
	array(
		0 => array( 'pipe', 'r' ),
		1 => array( 'pipe', 'w' ),
		2 => array( 'pipe', 'w' ),
	),
	$geography_pipes,
	$root
);
tl_test_assert( is_resource( $geography_process ), 'Could not start the geography resolver test.' );
fclose( $geography_pipes[0] );
$geography_stdout = stream_get_contents( $geography_pipes[1] );
$geography_stderr = stream_get_contents( $geography_pipes[2] );
fclose( $geography_pipes[1] );
fclose( $geography_pipes[2] );
$geography_status = proc_close( $geography_process );
tl_test_assert( 0 === $geography_status, 'Geography resolver test failed: ' . trim( $geography_stderr ) );
tl_test_assert( 'PASS: geography resolver contract' === trim( $geography_stdout ), 'Unexpected geography resolver test output.' );

$package_lines  = file( $root . '/package-files.txt', FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES );
$package_entries = array();
foreach ( $package_lines as $line ) {
	$line = trim( $line );
	if ( '' !== $line && 0 !== strpos( $line, '#' ) ) {
		$package_entries[] = $line;
	}
}
$sorted_entries = $package_entries;
sort( $sorted_entries, SORT_STRING );
tl_test_assert( $sorted_entries === $package_entries, 'Package inventory is not sorted.' );
tl_test_assert( count( $package_entries ) === count( array_unique( $package_entries ) ), 'Package inventory has duplicates.' );

$homepage_runtime_files = array(
	'assets/homepage/homepage.css',
	'assets/homepage/homepage.js',
	'assets/homepage/images/homepage-hero-thailand-system-v1-1024.webp',
	'assets/homepage/images/homepage-hero-thailand-system-v1-1713.webp',
	'assets/homepage/images/homepage-hero-thailand-system-v1-640.webp',
	'resources/homepage.html',
	'src/Homepage/Assets.php',
	'src/Homepage/Context.php',
	'src/Homepage/FeatureFlag.php',
	'src/Homepage/Module.php',
	'src/Homepage/Renderer.php',
	'src/Homepage/Seo.php',
	'src/Homepage/Settings.php',
	'templates/front-page.php',
);

foreach ( $homepage_runtime_files as $runtime_file ) {
	tl_test_assert( in_array( $runtime_file, $package_entries, true ), 'Homepage runtime file is not packaged: ' . $runtime_file );
	tl_test_assert( is_file( $root . '/' . $runtime_file ), 'Homepage runtime file is missing: ' . $runtime_file );
}

$geography_runtime_files = array(
	'assets/geography/core.json',
	'resources/geography/manifest.json',
	'resources/geography/registry.php',
	'src/Geography/Repository.php',
	'src/Geography/Resolver.php',
	'src/Geography/Route.php',
);
foreach ( $geography_runtime_files as $runtime_file ) {
	tl_test_assert( in_array( $runtime_file, $package_entries, true ), 'Geography runtime file is not packaged: ' . $runtime_file );
	tl_test_assert( is_file( $root . '/' . $runtime_file ), 'Geography runtime file is missing: ' . $runtime_file );
}

foreach ( $package_entries as $entry ) {
	foreach ( array( 'data/', 'output/', 'prototype/', 'scripts/', 'tests/' ) as $excluded_prefix ) {
		tl_test_assert( 0 !== strpos( $entry, $excluded_prefix ), 'Non-runtime tree entered package: ' . $entry );
	}
}
foreach ( array( 'README.md', 'package-files.txt', 'release.json' ) as $excluded_file ) {
	tl_test_assert( ! in_array( $excluded_file, $package_entries, true ), 'Build-only file entered package: ' . $excluded_file );
}

$context_source   = file_get_contents( $root . '/src/Homepage/Context.php' );
$renderer_source  = file_get_contents( $root . '/src/Homepage/Renderer.php' );
$uninstall_source = file_get_contents( $root . '/uninstall.php' );
tl_test_assert( false !== $context_source, 'Homepage context source is unreadable.' );
tl_test_assert( false !== $renderer_source, 'Homepage renderer source is unreadable.' );
tl_test_assert( false !== $uninstall_source, 'Uninstall source is unreadable.' );
tl_test_assert(
	1 === preg_match( '/return\s+\$authorized\s*&&\s*Renderer::ready\(\)\s*;/', $context_source ),
	'Homepage context no longer fails closed through renderer readiness.'
);
tl_test_assert(
	1 === preg_match( '/if\s*\(\s*!\s*Context::should_render\(\)\s*\)\s*\{\s*return\s+\$template\s*;/s', $renderer_source ),
	'Homepage renderer no longer returns the legacy template when context rejects rendering.'
);
tl_test_assert( false !== strpos( $renderer_source, "THAILAND_PLATFORM_DIR . 'templates/front-page.php'" ), 'Renderer readiness omits the production template.' );
tl_test_assert( false !== strpos( $renderer_source, '! is_readable( $path ) || 0 === filesize( $path )' ), 'Renderer readiness no longer rejects missing or empty runtime files.' );
foreach ( array( 'id="main-content"', 'class="site-header"', 'class="site-footer"' ) as $required_marker ) {
	tl_test_assert( false !== strpos( $renderer_source, $required_marker ), 'Renderer readiness marker missing: ' . $required_marker );
}

tl_test_assert(
	1 === preg_match( "/delete_option\(\s*'" . preg_quote( FeatureFlag::OPTION, '/' ) . "'\s*\)\s*;/", $uninstall_source ),
	'Uninstall does not delete the bounded homepage mode option.'
);
tl_test_assert( 1 === substr_count( $uninstall_source, 'delete_option(' ), 'Uninstall deletes an unexpected number of options.' );
tl_test_assert( false !== strpos( $uninstall_source, 'wp_cache_flush();' ), 'Uninstall no longer purges the WordPress cache.' );
tl_test_assert( false !== strpos( $uninstall_source, 'clear_cache( false )' ), 'Uninstall no longer purges the Upress page cache.' );

$markup = Renderer::body_markup();
tl_test_assert( Renderer::ready(), 'Homepage renderer is not ready with complete assets.' );
tl_test_assert( '' !== $markup, 'Homepage markup is empty.' );
tl_test_assert( 1 === substr_count( strtolower( $markup ), '<h1' ), 'Homepage must contain exactly one H1.' );
tl_test_assert( 1 === substr_count( strtolower( $markup ), '<main' ), 'Homepage must contain exactly one main landmark.' );
tl_test_assert( 1 === substr_count( strtolower( $markup ), '<footer' ), 'Homepage must contain exactly one footer.' );
tl_test_assert( false === stripos( $markup, '<html' ), 'Homepage fragment unexpectedly contains an HTML document root.' );
tl_test_assert( false === stripos( $markup, '<body' ), 'Homepage fragment unexpectedly contains a body element.' );
tl_test_assert( false === stripos( $markup, '<script' ), 'Homepage fragment unexpectedly contains inline script.' );
tl_test_assert( false === stripos( $markup, '<style' ), 'Homepage fragment unexpectedly contains inline style.' );

preg_match_all( '/\sid="([^"]+)"/', $markup, $id_matches );
$markup_ids = $id_matches[1];
tl_test_assert( count( $markup_ids ) === count( array_unique( $markup_ids ) ), 'Homepage fragment contains duplicate IDs.' );
preg_match_all( '/href="#([A-Za-z][A-Za-z0-9_-]+)"/', $markup, $fragment_matches );
foreach ( array_unique( $fragment_matches[1] ) as $fragment_target ) {
	tl_test_assert( in_array( $fragment_target, $markup_ids, true ), 'Homepage has a missing fragment target: ' . $fragment_target );
}

$css = file_get_contents( $root . '/assets/homepage/homepage.css' );
$js  = file_get_contents( $root . '/assets/homepage/homepage.js' );
tl_test_assert( false !== $css && strlen( $css ) <= 70000, 'Homepage CSS is missing or exceeds the raw release budget.' );
tl_test_assert( false !== $js && strlen( $js ) <= 25000, 'Homepage JavaScript is missing or exceeds the raw release budget.' );
tl_test_assert( false !== strpos( $css, '.thp-home' ), 'Homepage CSS is not scoped to the plugin root.' );
tl_test_assert( 0 === preg_match( '/(?:fetch\s*\(|XMLHttpRequest|https?:\\/\\/)/i', $js ), 'Homepage JavaScript contains a network runtime.' );
$media_scope_violations = tl_test_media_scope_violations( $css );
tl_test_assert(
	array() === $media_scope_violations,
	'Homepage media query contains an unscoped selector: ' . implode( ', ', $media_scope_violations )
);
tl_test_assert( false !== strpos( $css, '@keyframes thp-home-fade-in' ), 'Scoped homepage fade keyframe missing.' );
tl_test_assert( false !== strpos( $css, '@keyframes thp-home-slide-in-rtl' ), 'Scoped homepage drawer keyframe missing.' );
tl_test_assert( 0 === preg_match( '/@keyframes\s+(?:fade-in|slide-in-rtl)\b/', $css ), 'Generic homepage keyframe name leaked globally.' );
tl_test_assert(
	false !== strpos( $css, 'body.thailand-platform-home.drawer-open #pojo-a11y-toolbar' )
	&& false !== strpos( $css, 'body.thailand-platform-home.drawer-open #pojo-a11y-skip-content' )
	&& false !== strpos( $css, 'visibility: hidden;' )
	&& false !== strpos( $css, 'pointer-events: none;' ),
	'Open mobile drawer no longer suppresses the external accessibility control.'
);
tl_test_assert( false !== strpos( $js, "doc.querySelectorAll('#pojo-a11y-toolbar, #pojo-a11y-skip-content')" ), 'Mobile drawer no longer includes all external accessibility controls in its inert background.' );
tl_test_assert( false !== strpos( $js, 'new Set([...drawerBackground, ...externalDrawerControls])' ), 'Mobile drawer background restoration can contain duplicate elements.' );
tl_test_assert( false !== strpos( $js, 'inert: element.inert' ), 'Mobile drawer no longer records prior inert state.' );
tl_test_assert( false !== strpos( $js, 'element.inert = inert' ), 'Mobile drawer no longer restores prior inert state.' );
tl_test_assert( false !== strpos( $js, "window.matchMedia('(min-width: 1231px)')" ), 'Mobile drawer no longer watches the desktop navigation breakpoint.' );
tl_test_assert( false !== strpos( $js, "desktopNavigation.addEventListener('change', closeDrawerAtDesktop)" ), 'Mobile drawer no longer closes when the desktop breakpoint is crossed.' );
tl_test_assert( false !== strpos( $js, 'closeDrawer({ restoreFocus: false })' ), 'Desktop breakpoint close can move focus to the hidden menu button.' );
tl_test_assert( false !== strpos( $js, "doc.querySelector('.site-header .brand')" ), 'Desktop breakpoint close has no visible focus destination.' );
tl_test_assert( false !== strpos( $js, "doc.activeElement?.closest('#mobile-drawer')" ), 'Desktop breakpoint close no longer detects focus left in the hidden drawer.' );
tl_test_assert( false !== strpos( $js, 'desktopFocusTarget?.focus({ preventScroll: true })' ), 'Desktop breakpoint close no longer moves focus to the visible header.' );
foreach ( array( 'em dash' => "\xE2\x80\x94", 'en dash' => "\xE2\x80\x93" ) as $dash_name => $dash_bytes ) {
	tl_test_assert( false === strpos( $markup, $dash_bytes ), 'Homepage public markup contains an ' . $dash_name . '.' );
	tl_test_assert( false === strpos( $js, $dash_bytes ), 'Homepage public JavaScript contains an ' . $dash_name . '.' );
}

$presentation_phrases = array(
	'מדריכים וידע שימושי',
	'איך המידע עובד',
	'איך משתמשים באתר',
	'איך מגיעים למידע',
	'להיכרות ראשונית',
	'יעדים נבחרים',
	'לסקירת',
	'סקירה עסקית',
	'ממשיכים מכאן',
	'ממשיכים לפי הנושא',
	'לכרטיס',
	'מפה סכמטית',
	'מדריך מומלץ',
	'מתחילים כאן',
	'בדיקות ראשונות',
	'נושאים קשורים',
	'נושאים למעבר',
	'למציאת מקום ראשון',
	'נקודות בדיקה',
	'הפלטפורמה המקיפה ביותר',
	'כל מה שצריך לדעת במקום אחד',
	'המסע שלכם לתאילנד מתחיל כאן',
	'גלו את תאילנד כמו שלא הכרתם',
	'אנחנו משנים את הדרך',
	'החזון שלנו',
	'המשימה שלנו',
	'מערכת תוכן מתקדמת',
	'חוויה דיגיטלית פורצת דרך',
	'פתרון מקיף לכל צורך',
	'מידע אמין ומאומת',
	'הצוות שלנו בדק עבורכם',
	'המידע המעודכן ביותר',
	'אלפי ישראלים כבר',
	'האתר המוביל',
	'המומחים שלנו',
	'שנים של ניסיון',
	'קהילה ותיקה',
	'הצטרפו לקהילה הצומחת',
	'אנו גאים להציג',
	'ללא גבולות',
	'הנכס המושלם',
	'שאסור לפספס',
	'השקעה בטוחה',
	'בלב גן עדן',
	'ללא פשרות',
	'מיקום מנצח',
	'אטרקטיבי במיוחד',
	'תשואה גבוהה במיוחד',
	'החלום התאילנדי',
	'ללא תחרות',
);
tl_test_assert( 50 === count( $presentation_phrases ), 'Homepage language boundary must keep all 50 rejected phrases.' );
foreach ( array( 'homepage markup' => $markup, 'homepage JavaScript' => $js ) as $public_source_name => $public_source ) {
	foreach ( $presentation_phrases as $presentation_phrase ) {
		tl_test_assert(
			false === strpos( $public_source, $presentation_phrase ),
			$public_source_name . ' contains presentation language: ' . $presentation_phrase
		);
	}
}
foreach ( array( 'תאילנד לישראלים', 'ישראלים בתאילנד', 'בעלות, עלויות וחוזים', 'חברות, רישוי ומסים', 'קוסמוי וקופנגן', 'חפשו יעד, מדריך או נושא בתאילנד', 'בתי חב״ד', 'מסעדות כשרות' ) as $reader_phrase ) {
	tl_test_assert( false !== strpos( $markup, $reader_phrase ), 'Homepage public markup is missing reader-facing language: ' . $reader_phrase );
}

foreach ( array( 'עסקים והאתר', 'כל המידע על בנגקוק', 'קהילה ושירותים', 'שירותים לפי אזור', 'מימון, שכירות, תשואה וניהול', 'MARKET<br>OPERATIONS', 'THAILAND<br>BUSINESS', 'חפשו יעד, שכונה, פרויקט, שירות או אטרקציה', 'חיפושים נפוצים', 'חיפוש בכל תאילנד' ) as $unsupported_phrase ) {
	tl_test_assert( false === strpos( $markup, $unsupported_phrase ), 'Homepage public markup contains an unsupported promise or presentation label: ' . $unsupported_phrase );
}

$gated_route_substrings = array(
	'ויזת-תיירים',
	'אישור-עבודה',
	'permanent-residence',
	'ביטוח-נסיעות',
	'מעבר-לתאילנד',
	'קניית-נכס',
	'אפשרויות-משכנתא',
	'מדריך-להשכרת',
	'property-management',
	'עסקים-בתאילנד-סקירה',
	'כלכלת-תאילנד',
	'מחשבון-תכנון',
);
foreach ( array( 'homepage markup' => $markup, 'homepage JavaScript' => $js ) as $public_source_name => $public_source ) {
	foreach ( $gated_route_substrings as $gated_route_substring ) {
		tl_test_assert(
			false === strpos( $public_source, $gated_route_substring ),
			$public_source_name . ' promotes gated route substring: ' . $gated_route_substring
		);
	}
}

tl_test_assert( 1 === substr_count( $markup, 'id="saved"' ), 'Homepage saved-list section target is missing or duplicated.' );
tl_test_assert( 1 === substr_count( $markup, 'data-saved-list' ), 'Homepage saved-list render target is missing or duplicated.' );
foreach ( array( 'chiangmai', 'phuket', 'samui' ) as $atlas_place ) {
	tl_test_assert(
		false !== strpos( $markup, 'data-atlas-place="' . $atlas_place . '"' ),
		'Homepage atlas control is missing for: ' . $atlas_place
	);
}
tl_test_assert( false !== strpos( $js, "querySelector('[data-saved-list]')" ), 'Homepage JavaScript does not select the saved-list target.' );
tl_test_assert( false !== strpos( $js, 'savedList.replaceChildren()' ), 'Homepage JavaScript does not render the saved-list state.' );
tl_test_assert( false !== strpos( $js, "closest('[data-atlas-place]')" ), 'Homepage JavaScript does not handle atlas-place controls.' );
tl_test_assert( false !== strpos( $js, 'placeControl.dataset.atlasPlace' ), 'Homepage JavaScript does not resolve atlas-place keys.' );
tl_test_assert( false !== strpos( $js, 'api.minimize()' ), 'Homepage JavaScript does not minimize the Tawk widget on load.' );
tl_test_assert( false !== strpos( $js, 'const previousTawkOnLoad = tawkApi.onLoad' ), 'Homepage JavaScript does not preserve the current Tawk onLoad callback.' );
tl_test_assert( false !== strpos( $js, 'previousTawkOnLoad.apply(this, args)' ), 'Homepage JavaScript does not run the preserved Tawk onLoad callback.' );
tl_test_assert( false !== strpos( $js, 'tawkApi.onLoad = function (...args)' ), 'Homepage JavaScript does not register a Tawk readiness callback.' );
tl_test_assert( false !== strpos( $js, 'api.isChatMinimized()' ), 'Homepage JavaScript does not verify that Tawk is compact.' );
tl_test_assert( false !== strpos( $js, 'tawkRetryDelay = Math.min(tawkRetryDelay * 2, 4000)' ), 'Homepage JavaScript does not retry late Tawk readiness with bounded backoff.' );
tl_test_assert( false !== strpos( $js, "window.addEventListener('pagehide'" ), 'Homepage JavaScript does not clear the Tawk readiness timer on page exit.' );
tl_test_assert( false !== strpos( $js, "window.addEventListener('pageshow', (event) =>" ), 'Homepage JavaScript does not handle history restoration.' );
tl_test_assert( false !== strpos( $js, '!event.persisted || tawkResumeAfterPageShow' ), 'Homepage JavaScript minimizes chat after every history restoration.' );
tl_test_assert( false !== strpos( $js, 'tawkResumeAfterPageShow = Boolean(tawkRetryTimer || tawkGreetingTimer)' ), 'Homepage JavaScript does not remember interrupted Tawk settling.' );
tl_test_assert( false !== strpos( $js, 'if (tawkGreetingTimer) window.clearTimeout(tawkGreetingTimer)' ), 'Homepage JavaScript leaves a Tawk greeting timer active on page exit.' );
tl_test_assert( false !== strpos( $js, "wrapTawkCallback('onChatMessageVisitor'," ), 'Homepage JavaScript does not remember visitor chat interaction.' );
tl_test_assert( false !== strpos( $js, "wrapTawkCallback('onChatMessageAgent', queueTawkGreetingSettle)" ), 'Homepage JavaScript does not handle unsolicited agent greetings.' );
tl_test_assert( false !== strpos( $js, "wrapTawkCallback('onChatMessageSystem', queueTawkGreetingSettle)" ), 'Homepage JavaScript does not handle unsolicited system greetings.' );
tl_test_assert( false === strpos( $js, "wrapTawkCallback('onChatMaximized'" ), 'Homepage JavaScript intercepts visitor chat maximization.' );
tl_test_assert( false === strpos( $js, 'tawkPageTitle' ), 'Homepage JavaScript classifies visitor intent from title timing.' );
tl_test_assert( false !== strpos( $js, 'api.isVisitorEngaged()' ), 'Homepage JavaScript does not preserve an engaged visitor chat.' );
tl_test_assert( false !== strpos( $js, 'api.isChatOngoing()' ), 'Homepage JavaScript does not preserve an ongoing visitor chat.' );
tl_test_assert( false !== strpos( $js, "if (tawkVisitorIsActive(api)) return 'preserve'" ), 'Homepage JavaScript does not guard every Tawk minimize attempt.' );
tl_test_assert( false !== strpos( $js, "if (state !== 'retry')" ), 'Homepage JavaScript does not stop Tawk retries when visitor chat must be preserved.' );
tl_test_assert( false !== strpos( $js, 'tawkVisitorInteracted = true' ), 'Homepage JavaScript does not preserve a visitor who has sent a chat message.' );
tl_test_assert( false !== strpos( $js, 'previousCallback.apply(this, args)' ), 'Homepage JavaScript does not preserve existing Tawk message callbacks.' );
tl_test_assert( false === strpos( $js, 'tawkAttempts' ), 'Homepage JavaScript contains a fixed Tawk readiness attempt limit.' );
tl_test_assert( false === strpos( $js, 'hideWidget()' ), 'Homepage JavaScript removes chat access on mobile.' );
tl_test_assert( false !== strpos( $css, 'left: 0; right: 88px' ), 'Homepage mobile action bar does not reserve room for the right-side chat launcher.' );
tl_test_assert( false !== strpos( $css, 'border-top-right-radius: 18px' ), 'Homepage mobile action bar does not round the edge beside the chat launcher.' );
tl_test_assert( false !== strpos( $markup, 'יום־יום' ), 'Homepage public copy is missing the preferred יום־יום spelling.' );
tl_test_assert( false === strpos( $markup, 'יום יום' ), 'Homepage public copy contains unhyphenated יום יום.' );
tl_test_assert( false === strpos( $js, 'יום יום' ), 'Homepage JavaScript contains unhyphenated יום יום.' );

foreach ( glob( $root . '/assets/homepage/images/*.webp' ) as $image_path ) {
	$image_payload = file_get_contents( $image_path );
	tl_test_assert( false !== $image_payload && strlen( $image_payload ) <= 220000, 'Homepage image exceeds the release budget.' );
	tl_test_assert( 'RIFF' === substr( $image_payload, 0, 4 ) && 'WEBP' === substr( $image_payload, 8, 4 ), 'Homepage image is not a valid WebP container.' );
}

/* Off mode is inert on every request. */
tl_test_set_request( FeatureFlag::MODE_OFF, true, false, false );
tl_test_assert( FeatureFlag::MODE_OFF === FeatureFlag::mode(), 'Off mode mismatch.' );
tl_test_assert( ! Context::should_render(), 'Off mode rendered the homepage.' );
tl_test_assert( 'legacy.php' === tl_test_apply_filters( 'template_include', 'legacy.php' ), 'Off mode replaced the theme template.' );
foreach ( array( 'pre_get_document_title', 'wpseo_title', 'wpseo_opengraph_title', 'wpseo_twitter_title' ) as $title_filter ) {
	tl_test_assert( 'Legacy title' === tl_test_apply_filters( $title_filter, 'Legacy title' ), 'Off mode changed title filter: ' . $title_filter );
}
foreach ( array( 'wpseo_metadesc', 'wpseo_opengraph_desc', 'wpseo_twitter_description' ) as $description_filter ) {
	tl_test_assert( 'Legacy description' === tl_test_apply_filters( $description_filter, 'Legacy description' ), 'Off mode changed description filter: ' . $description_filter );
}
foreach ( array( 'wpseo_opengraph_image', 'wpseo_twitter_image' ) as $image_filter ) {
	tl_test_assert( 'legacy.webp' === tl_test_apply_filters( $image_filter, 'legacy.webp' ), 'Off mode changed social image filter: ' . $image_filter );
}
tl_test_assert( '640' === tl_test_apply_filters( 'wpseo_opengraph_image_width', '640' ), 'Off mode changed Open Graph image width.' );
tl_test_assert( '421' === tl_test_apply_filters( 'wpseo_opengraph_image_height', '421' ), 'Off mode changed Open Graph image height.' );
$GLOBALS['tl_test_styles']      = array();
$GLOBALS['tl_test_scripts']     = array();
$GLOBALS['tl_test_script_data'] = array();
$GLOBALS['tl_test_inline_scripts'] = array();
tl_test_do_action( 'wp_enqueue_scripts' );
tl_test_assert( array() === $GLOBALS['tl_test_styles'], 'Off mode enqueued homepage CSS.' );
tl_test_assert( array() === $GLOBALS['tl_test_scripts'], 'Off mode enqueued homepage JavaScript.' );
tl_test_assert( array() === $GLOBALS['tl_test_inline_scripts'], 'Off mode added inline analytics configuration.' );

/* Live mode changes presentation only on the canonical front page. */
tl_test_set_request( FeatureFlag::MODE_LIVE, false, false, false );
tl_test_assert( ! Context::should_render(), 'Live mode rendered away from the front page.' );
tl_test_set_request( FeatureFlag::MODE_LIVE, true, false, false );
tl_test_assert( Context::should_render(), 'Live mode did not render on the front page.' );
$expected_template = THAILAND_PLATFORM_DIR . 'templates/front-page.php';
tl_test_assert(
	$expected_template === tl_test_apply_filters( 'template_include', 'legacy.php' ),
	'Live mode did not select the plugin homepage template.'
);

$GLOBALS['tl_test_styles']       = array();
$GLOBALS['tl_test_scripts']      = array();
$GLOBALS['tl_test_script_data'] = array();
$GLOBALS['tl_test_inline_scripts'] = array();
tl_test_do_action( 'wp_enqueue_scripts' );
tl_test_assert( isset( $GLOBALS['tl_test_styles']['thailand-platform-homepage'] ), 'Live homepage CSS was not enqueued.' );
tl_test_assert( isset( $GLOBALS['tl_test_scripts']['thailand-platform-homepage'] ), 'Live homepage JavaScript was not enqueued.' );
tl_test_assert( isset( $GLOBALS['tl_test_scripts']['thailand-platform-google-analytics'] ), 'Public Live analytics was not enqueued.' );
$style  = $GLOBALS['tl_test_styles']['thailand-platform-homepage'];
$script = $GLOBALS['tl_test_scripts']['thailand-platform-homepage'];
$analytics = $GLOBALS['tl_test_scripts']['thailand-platform-google-analytics'];
tl_test_assert( THAILAND_PLATFORM_VERSION === $style['version'], 'Homepage CSS version mismatch.' );
tl_test_assert( THAILAND_PLATFORM_VERSION === $script['version'], 'Homepage JavaScript version mismatch.' );
tl_test_assert( array() === $style['dependencies'], 'Homepage CSS has a runtime dependency.' );
tl_test_assert( array() === $script['dependencies'], 'Homepage JavaScript has a runtime dependency.' );
tl_test_assert( true === $script['in_footer'], 'Homepage JavaScript is not footer-loaded.' );
tl_test_assert(
	'defer' === $GLOBALS['tl_test_script_data']['thailand-platform-homepage']['strategy'],
	'Homepage JavaScript defer strategy missing.'
);
tl_test_assert(
	'https://www.googletagmanager.com/gtag/js?id=G-R3THSJW0TT' === $analytics['source'],
	'Public Live analytics source or measurement ID mismatch.'
);
tl_test_assert( array() === $analytics['dependencies'], 'Analytics unexpectedly has a runtime dependency.' );
tl_test_assert( null === $analytics['version'], 'Analytics URL was modified with a WordPress version.' );
tl_test_assert( false === $analytics['in_footer'], 'Analytics loader is not head-loaded.' );
tl_test_assert(
	'async' === $GLOBALS['tl_test_script_data']['thailand-platform-google-analytics']['strategy'],
	'Analytics async strategy missing.'
);
tl_test_assert(
	isset( $GLOBALS['tl_test_inline_scripts']['thailand-platform-google-analytics'] )
	&& 1 === count( $GLOBALS['tl_test_inline_scripts']['thailand-platform-google-analytics'] ),
	'Analytics inline configuration count mismatch.'
);
$analytics_inline = $GLOBALS['tl_test_inline_scripts']['thailand-platform-google-analytics'][0];
tl_test_assert( 'after' === $analytics_inline['position'], 'Analytics configuration position mismatch.' );
tl_test_assert(
	"window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js',new Date());gtag('config','G-R3THSJW0TT');" === $analytics_inline['data'],
	'Analytics initialization or config ID mismatch.'
);

ob_start();
tl_test_do_action( 'wp_head' );
$preload = ob_get_clean();
tl_test_assert( 3 === substr_count( $preload, 'rel="preload"' ), 'Homepage hero preload count mismatch.' );
tl_test_assert( 3 === substr_count( $preload, 'fetchpriority="high"' ), 'Each homepage hero preload must be high priority.' );
tl_test_assert( false === stripos( $preload, 'imagesrcset' ), 'Homepage hero preload must not include imagesrcset.' );
tl_test_assert( false === stripos( $preload, 'imagesizes' ), 'Homepage hero preload must not include imagesizes.' );
preg_match_all(
	'/<link rel="preload" href="([^"]+)" as="image" type="image\/webp" media="([^"]+)" fetchpriority="high">/',
	$preload,
	$preload_matches,
	PREG_SET_ORDER
);
tl_test_assert( 3 === count( $preload_matches ), 'Homepage hero preload markup contract mismatch.' );
$expected_preloads = array(
	array( '-640.webp', '(max-width: 640px)' ),
	array( '-1024.webp', '(min-width: 641px) and (max-width: 1279px)' ),
	array( '-1713.webp', '(min-width: 1280px)' ),
);
foreach ( $expected_preloads as $index => $expected_preload ) {
	tl_test_assert( false !== strpos( $preload_matches[ $index ][1], $expected_preload[0] ), 'Homepage hero preload image mismatch for range: ' . $expected_preload[1] );
	tl_test_assert( $expected_preload[1] === $preload_matches[ $index ][2], 'Homepage hero preload media range mismatch.' );
	tl_test_assert( 1 === substr_count( $preload, $expected_preload[0] ), 'Homepage hero variant was preloaded more than once.' );
}

$live_classes = tl_test_apply_filters( 'body_class', array( 'home' ) );
tl_test_assert( in_array( 'thailand-platform-home', $live_classes, true ), 'Live homepage root class missing.' );
tl_test_assert( in_array( 'thailand-platform-live', $live_classes, true ), 'Live homepage mode class missing.' );
tl_test_assert( array() === tl_test_apply_filters( 'wp_robots', array() ), 'Live mode unexpectedly changed robots.' );
tl_test_assert( 'index, follow' === tl_test_apply_filters( 'wpseo_robots', 'index, follow' ), 'Live mode changed Yoast robots.' );
foreach ( array( 'pre_get_document_title', 'wpseo_title', 'wpseo_opengraph_title', 'wpseo_twitter_title' ) as $title_filter ) {
	tl_test_assert( Seo::TITLE === tl_test_apply_filters( $title_filter, 'Legacy title' ), 'Live homepage title mismatch: ' . $title_filter );
}
tl_test_assert( 'Existing owner' === tl_test_apply_filters( 'wpseo_metadesc', 'Existing owner' ), 'Existing SEO description was replaced.' );
tl_test_assert( Seo::DESCRIPTION === tl_test_apply_filters( 'wpseo_metadesc', '' ), 'Missing live SEO description was not filled.' );
tl_test_assert( Seo::DESCRIPTION === tl_test_apply_filters( 'wpseo_opengraph_desc', '' ), 'Open Graph description mismatch.' );
tl_test_assert( Seo::DESCRIPTION === tl_test_apply_filters( 'wpseo_twitter_description', '' ), 'Twitter description mismatch.' );
tl_test_assert( Seo::DESCRIPTION === tl_test_apply_filters( 'wpseo_opengraph_desc', 'Legacy social description' ), 'Legacy Open Graph description remained live.' );
tl_test_assert( Seo::DESCRIPTION === tl_test_apply_filters( 'wpseo_twitter_description', 'Legacy social description' ), 'Legacy Twitter description remained live.' );
$expected_social_image = plugins_url( Seo::SOCIAL_IMAGE, THAILAND_PLATFORM_FILE );
tl_test_assert( $expected_social_image === tl_test_apply_filters( 'wpseo_opengraph_image', 'legacy.webp' ), 'Live Open Graph image mismatch.' );
tl_test_assert( $expected_social_image === tl_test_apply_filters( 'wpseo_twitter_image', 'legacy.webp' ), 'Live Twitter image mismatch.' );
tl_test_assert( Seo::SOCIAL_IMAGE_WIDTH === tl_test_apply_filters( 'wpseo_opengraph_image_width', '640' ), 'Live Open Graph image width mismatch.' );
tl_test_assert( Seo::SOCIAL_IMAGE_HEIGHT === tl_test_apply_filters( 'wpseo_opengraph_image_height', '421' ), 'Live Open Graph image height mismatch.' );

/* Canary mode is administrator-only, noindex, and private. */
tl_test_set_request( FeatureFlag::MODE_CANARY, true, false, true );
tl_test_assert( FeatureFlag::canary_requested(), 'Exact canary request was not recognized.' );
tl_test_assert( ! Context::should_render(), 'Unauthorized canary rendered.' );
$GLOBALS['wp_query']              = new TL_Test_Query();
$GLOBALS['tl_test_status_headers'] = array();
$GLOBALS['tl_test_nocache_calls'] = 0;
( new Context() )->protect_canary();
tl_test_assert( $GLOBALS['wp_query']->is_404, 'Unauthorized canary did not set the query to 404.' );
tl_test_assert( array( 404 ) === $GLOBALS['tl_test_status_headers'], 'Unauthorized canary status mismatch.' );
tl_test_assert( 1 === $GLOBALS['tl_test_nocache_calls'], 'Unauthorized canary did not send no-cache headers.' );

tl_test_set_request( FeatureFlag::MODE_CANARY, true, true, true );
tl_test_assert( Context::should_render(), 'Administrator canary did not render.' );
tl_test_assert( Context::is_authorized_canary(), 'Administrator canary context mismatch.' );
tl_test_assert(
	$expected_template === tl_test_apply_filters( 'template_include', 'legacy.php' ),
	'Administrator canary did not select the plugin template.'
);
$GLOBALS['tl_test_styles']         = array();
$GLOBALS['tl_test_scripts']        = array();
$GLOBALS['tl_test_script_data']    = array();
$GLOBALS['tl_test_inline_scripts'] = array();
tl_test_do_action( 'wp_enqueue_scripts' );
tl_test_assert( isset( $GLOBALS['tl_test_styles']['thailand-platform-homepage'] ), 'Canary homepage CSS was not enqueued.' );
tl_test_assert( isset( $GLOBALS['tl_test_scripts']['thailand-platform-homepage'] ), 'Canary homepage JavaScript was not enqueued.' );
tl_test_assert( ! isset( $GLOBALS['tl_test_scripts']['thailand-platform-google-analytics'] ), 'Authorized canary enqueued public analytics.' );
tl_test_assert( ! isset( $GLOBALS['tl_test_inline_scripts']['thailand-platform-google-analytics'] ), 'Authorized canary added analytics configuration.' );
$GLOBALS['wp_query']               = new TL_Test_Query();
$GLOBALS['tl_test_status_headers'] = array();
$GLOBALS['tl_test_nocache_calls']  = 0;
( new Context() )->protect_canary();
tl_test_assert( ! $GLOBALS['wp_query']->is_404, 'Authorized canary was changed to 404.' );
tl_test_assert( array() === $GLOBALS['tl_test_status_headers'], 'Authorized canary emitted an error status.' );
tl_test_assert( 1 === $GLOBALS['tl_test_nocache_calls'], 'Authorized canary did not send no-cache headers.' );
$canary_classes = tl_test_apply_filters( 'body_class', array( 'home' ) );
tl_test_assert( in_array( 'thailand-platform-canary', $canary_classes, true ), 'Canary body class missing.' );
$canary_robots = tl_test_apply_filters( 'wp_robots', array( 'max-image-preview' => 'large' ) );
tl_test_assert( true === $canary_robots['noindex'], 'Canary noindex directive missing.' );
tl_test_assert( true === $canary_robots['nofollow'], 'Canary nofollow directive missing.' );
tl_test_assert( true === $canary_robots['noarchive'], 'Canary noarchive directive missing.' );
tl_test_assert(
	'noindex, nofollow, noarchive' === tl_test_apply_filters( 'wpseo_robots', 'index, follow' ),
	'Canary Yoast robots mismatch.'
);
$canary_headers = tl_test_apply_filters( 'wp_headers', array( 'X-Test' => 'preserved' ) );
tl_test_assert( 'noindex, nofollow, noarchive' === $canary_headers['X-Robots-Tag'], 'Canary X-Robots-Tag missing.' );
tl_test_assert( 'private, no-store, max-age=0' === $canary_headers['Cache-Control'], 'Canary cache policy mismatch.' );
tl_test_assert( 'preserved' === $canary_headers['X-Test'], 'Canary headers removed an existing value.' );

$_GET[ FeatureFlag::CANARY_QUERY ] = '0';
tl_test_assert( ! FeatureFlag::canary_requested(), 'Non-exact canary value was accepted.' );

/* The emergency constant overrides even a stored Live value. */
define( 'THAILAND_PLATFORM_DISABLE_HOMEPAGE', true );
tl_test_set_request( FeatureFlag::MODE_LIVE, true, true, false );
tl_test_assert( FeatureFlag::MODE_OFF === FeatureFlag::mode(), 'Emergency homepage override failed.' );
tl_test_assert( ! Context::should_render(), 'Emergency override still rendered the homepage.' );
tl_test_assert( 'legacy.php' === tl_test_apply_filters( 'template_include', 'legacy.php' ), 'Emergency override did not restore the theme template.' );

$GLOBALS['wp_version'] = '6.8.7';
$activation_rejected   = false;

try {
	call_user_func( $GLOBALS['tl_test_activation'][ THAILAND_PLATFORM_FILE ] );
} catch ( RuntimeException $exception ) {
	$activation_rejected = false !== strpos( $exception->getMessage(), 'WordPress 6.9' );
}

tl_test_assert( $activation_rejected, 'Unsupported WordPress activation was not rejected.' );

fwrite( STDOUT, "PASS: Thailand Platform release contract\n" );
