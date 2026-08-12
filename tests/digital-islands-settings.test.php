<?php
/**
 * Focused security and exact-identity tests for Digital Islands settings.
 */

$root = dirname( __DIR__ );
define( 'THAILAND_PLATFORM_DIR', $root . DIRECTORY_SEPARATOR );
define( 'THAILAND_PLATFORM_FILE', $root . DIRECTORY_SEPARATOR . 'thailand-platform.php' );
define( 'THAILAND_PLATFORM_VERSION', '0.5.2' );

$hooks = array();
$test_options = array();
$test_asset_order = array();
$test_styles = array();
$test_scripts = array();
$test_script_data = array();
$test_inline_scripts = array();
$test_transients = array();
$test_post = array(
	'id'       => 731,
	'type'     => 'page',
	'status'   => 'publish',
	'password' => '',
	'required' => false,
	'page_uri' => '%d7%9e%d7%a4%d7%aa-%d7%a7%d7%95%d7%a4%d7%a0%d7%92%d7%9f',
	'permalink' => 'https://thai-land.co.il/%D7%9E%D7%A4%D7%AA-%D7%A7%D7%95%D7%A4%D7%A0%D7%92%D7%9F/?utm_source=test',
);

function add_action( $hook, $callback ) {
	global $hooks;
	$hooks[ $hook ] = $callback;
}
function absint( $value ) {
	return abs( (int) $value );
}
function sanitize_key( $value ) {
	return preg_replace( '/[^a-z0-9_-]/', '', strtolower( (string) $value ) );
}
function get_option( $name, $default = false ) {
	global $test_options;
	return array_key_exists( $name, $test_options ) ? $test_options[ $name ] : $default;
}
function current_user_can( $capability ) {
	return 'manage_options' === $capability;
}
function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component );
}
function get_post_type( $post_id ) {
	global $test_post;
	return $post_id === $test_post['id'] ? $test_post['type'] : false;
}
function get_post_status( $post_id ) {
	global $test_post;
	return $post_id === $test_post['id'] ? $test_post['status'] : false;
}
function get_post_field( $field, $post_id ) {
	global $test_post;
	return 'post_password' === $field && $post_id === $test_post['id'] ? $test_post['password'] : null;
}
function post_password_required( $post_id ) {
	global $test_post;
	return $post_id === $test_post['id'] ? $test_post['required'] : true;
}
function get_permalink( $post_id ) {
	global $test_post;
	return $post_id === $test_post['id'] ? $test_post['permalink'] : false;
}
function get_page_uri( $post_id ) {
	global $test_post;
	return $post_id === $test_post['id'] ? $test_post['page_uri'] : false;
}
function home_url( $path = '/' ) {
	return 'https://thai-land.co.il' . ( '/' === substr( $path, 0, 1 ) ? $path : '/' . $path );
}
function plugins_url( $path, $plugin_file = '' ) {
	unset( $plugin_file );
	return 'https://thai-land.co.il/wp-content/plugins/thailand-platform/' . ltrim( $path, '/' );
}
function is_singular() {
	return true;
}
function is_preview() {
	return false;
}
function is_feed() {
	return false;
}
function is_embed() {
	return false;
}
function get_queried_object_id() {
	global $test_post;
	return $test_post['id'];
}
function wp_unslash( $value ) {
	return $value;
}
function wp_json_encode( $value, $flags = 0, $depth = 512 ) {
	return json_encode( $value, $flags, $depth );
}
function wp_enqueue_style( $handle, $source, $dependencies = array(), $version = false, $media = 'all' ) {
	global $test_asset_order, $test_styles;
	$test_asset_order[] = 'style:' . $handle;
	$test_styles[ $handle ] = compact( 'source', 'dependencies', 'version', 'media' );
}
function wp_enqueue_script( $handle, $source, $dependencies = array(), $version = false, $in_footer = false ) {
	global $test_asset_order, $test_scripts;
	$test_asset_order[] = 'script:' . $handle;
	$test_scripts[ $handle ] = compact( 'source', 'dependencies', 'version', 'in_footer' );
}
function wp_script_add_data( $handle, $key, $value ) {
	global $test_script_data;
	$test_script_data[ $handle ][ $key ] = $value;
	return true;
}
function wp_add_inline_script( $handle, $data, $position = 'after' ) {
	global $test_asset_order, $test_inline_scripts;
	$test_asset_order[] = 'inline:' . $handle . ':' . $position;
	$test_inline_scripts[ $handle ][] = compact( 'data', 'position' );
	return true;
}
function get_transient( $key ) {
	global $test_transients;
	return array_key_exists( $key, $test_transients ) ? $test_transients[ $key ]['value'] : false;
}
function set_transient( $key, $value, $expiration ) {
	global $test_transients;
	$test_transients[ $key ] = compact( 'value', 'expiration' );
	return true;
}

require_once $root . '/src/DigitalIslands/StrictJson.php';
require_once $root . '/src/DigitalIslands/ArtifactVerifier.php';
require_once $root . '/src/DigitalIslands/Repository.php';
require_once $root . '/src/DigitalIslands/Context.php';
require_once $root . '/src/DigitalIslands/FeatureFlag.php';
require_once $root . '/src/DigitalIslands/RendererAssets.php';
require_once $root . '/src/DigitalIslands/Assets.php';
require_once $root . '/src/DigitalIslands/Renderer.php';
require_once $root . '/src/DigitalIslands/Settings.php';

use Thailand_Platform\DigitalIslands\Assets;
use Thailand_Platform\DigitalIslands\Context;
use Thailand_Platform\DigitalIslands\FeatureFlag;
use Thailand_Platform\DigitalIslands\Renderer;
use Thailand_Platform\DigitalIslands\RendererAssets;
use Thailand_Platform\DigitalIslands\Settings;

$assertions = 0;
$assert = static function ( $condition, $message ) use ( &$assertions ) {
	++$assertions;
	if ( ! $condition ) {
		fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
		exit( 1 );
	}
};

$settings = new Settings();
$settings->register();
$assert( isset( $hooks['admin_init'] ), 'Settings registration must retain the Settings API declaration.' );
$assert( isset( $hooks['admin_menu'] ), 'The administrator page hook is missing.' );
$assert( isset( $hooks['admin_post_' . Settings::SAVE_ACTION] ), 'The authenticated admin-post save hook is missing.' );
$assert( ! isset( $hooks['wp_ajax_nopriv_' . Settings::SAVE_ACTION] ), 'No anonymous save hook may exist.' );
$assert( isset( $hooks['add_option_' . FeatureFlag::OPTION] ) && isset( $hooks['update_option_' . FeatureFlag::OPTION] ), 'Mode changes must automatically purge caches.' );
$assert( isset( $hooks['add_option_' . FeatureFlag::PAGE_ID_OPTION] ) && isset( $hooks['update_option_' . FeatureFlag::PAGE_ID_OPTION] ), 'Page-ID changes must automatically purge caches.' );

$reflection = new ReflectionClass( Settings::class );
$assert( 49 === $reflection->getConstant( 'EXPECTED_PUBLIC_ENTITY_COUNT' ), 'The reviewed Live projection must remain exact at 49.' );

$submitted_page_id = $reflection->getMethod( 'submitted_page_id' );
$submitted_page_id->setAccessible( true );
$assert( 731 === $submitted_page_id->invoke( null, '731' ), 'A positive decimal page ID should be accepted.' );
$assert( 0 === $submitted_page_id->invoke( null, '-731' ), 'A signed page ID must fail closed.' );
$assert( 0 === $submitted_page_id->invoke( null, '731.5' ), 'A fractional page ID must fail closed.' );
$assert( 0 === $submitted_page_id->invoke( null, array( 731 ) ), 'A non-scalar page ID must fail closed.' );

$page_status = $reflection->getMethod( 'page_status' );
$page_status->setAccessible( true );
$ready = $page_status->invoke( null, 731 );
$assert( true === $ready['ready'] && 'ready' === $ready['code'], 'A percent-encoded Hebrew page URI with the exact permalink should pass identity.' );
$assert( $ready['canonical_path'] === Context::stored_page_uri_path( $test_post['page_uri'] ), 'The real WordPress-style encoded page URI did not normalize to the reviewed canonical.' );
$canary_page_status = $reflection->getMethod( 'canary_page_status' );
$canary_page_status->setAccessible( true );
$assert( true === $canary_page_status->invoke( null, 731 )['ready'], 'A matching encoded page URI should pass Canary identity.' );

$safe_page_uri = $test_post['page_uri'];
foreach (
	array(
		$safe_page_uri . '%zz',
		$safe_page_uri . '%2fchild',
		'%2e%2e/' . $safe_page_uri,
		$safe_page_uri . '%00',
		str_replace( '%', '%25', $safe_page_uri ),
		$safe_page_uri . chr( 1 ),
	) as $unsafe_page_uri
) {
	$test_post['page_uri'] = $unsafe_page_uri;
	$assert( 'wrong_permalink' === $page_status->invoke( null, 731 )['code'], 'Live settings accepted an unsafe stored page URI.' );
	$assert( 'wrong_permalink' === $canary_page_status->invoke( null, 731 )['code'], 'Canary settings accepted an unsafe stored page URI.' );
}
$test_post['page_uri'] = $safe_page_uri;

$test_post['type'] = 'post';
$assert( 'wrong_type' === $page_status->invoke( null, 731 )['code'], 'A non-page object must be rejected.' );
$test_post['type'] = 'page';
$test_post['status'] = 'draft';
$assert( 'not_published' === $page_status->invoke( null, 731 )['code'], 'A draft page must be rejected.' );
$assert( true === $canary_page_status->invoke( null, 731 )['ready'], 'A matching draft page should remain available only to administrator Canary review.' );
$test_post['status'] = 'publish';
$test_post['password'] = 'stored-secret';
$assert( 'password_protected' === $page_status->invoke( null, 731 )['code'], 'A raw stored post password must be rejected.' );
$test_post['password'] = '';
$test_post['permalink'] = 'https://thai-land.co.il/not-the-map/';
$assert( 'wrong_permalink' === $page_status->invoke( null, 731 )['code'], 'A mismatched permalink must be rejected.' );

$settings_source = file_get_contents( $root . '/src/DigitalIslands/Settings.php' );
$module_source   = file_get_contents( $root . '/src/DigitalIslands/Module.php' );
$assert( false !== strpos( $settings_source, "current_user_can( 'manage_options' )" ), 'The save surface must enforce manage_options.' );
$assert( false !== strpos( $settings_source, "'POST' !== \$request_method" ), 'The save surface must reject non-POST requests.' );
$assert( false !== strpos( $settings_source, 'check_admin_referer( self::NONCE_ACTION, self::NONCE_NAME )' ), 'The save surface must enforce its dedicated nonce.' );
$assert( false !== strpos( $settings_source, 'Renderer::ready_for_public()' ), 'Live must depend on the public renderer readiness gate.' );
$assert( false !== strpos( $settings_source, "update_option( FeatureFlag::OPTION, FeatureFlag::MODE_OFF" ), 'The save transaction must switch Off before rebinding identity.' );
$assert( false !== strpos( $settings_source, "'litespeed_purge_all'" ) && false !== strpos( $settings_source, '\\\\Upress\\\\EzCache\\\\Cache' ), 'The optional cache request must cover LiteSpeed and UPress.' );
$assert( false !== strpos( $settings_source, "'thailand_platform_homepage_cache_purge_requested'" ), 'A Digital Islands identity change must request homepage cache invalidation.' );
$assert( false !== strpos( $module_source, 'new Settings()' ), 'Digital Islands Module must register the settings surface.' );
$assert( false === strpos( $module_source, "add_action( 'admin_init', array( FeatureFlag::class" ), 'FeatureFlag setting registration must not be duplicated in Module.' );
$assert( FeatureFlag::MODE_OFF === FeatureFlag::sanitize( 'not-a-mode' ), 'Unknown modes must sanitize to Off.' );

/* Exact renderer inventory and dependency/config execution order. */
$test_post['permalink'] = 'https://thai-land.co.il/%D7%9E%D7%A4%D7%AA-%D7%A7%D7%95%D7%A4%D7%A0%D7%92%D7%9F/';
$test_options = array(
	FeatureFlag::OPTION         => FeatureFlag::MODE_LIVE,
	FeatureFlag::PAGE_ID_OPTION => 731,
);
$_SERVER['REQUEST_URI'] = '/%D7%9E%D7%A4%D7%AA-%D7%A7%D7%95%D7%A4%D7%A0%D7%92%D7%9F/?utm_source=test';

$assert( RendererAssets::ready(), 'The exact self-hosted renderer asset contract is not ready.' );
$assert( 1 === count( $test_transients ), 'Successful multi-megabyte receipt verification must create one bounded persistent cache entry.' );
$cache_entry = reset( $test_transients );
$assert( RendererAssets::VALIDATION_CACHE_TTL === $cache_entry['expiration'], 'The asset receipt cache has the wrong lifetime.' );
$assert( true === $cache_entry['value']['valid'], 'The asset receipt cache cannot record an ambiguous result.' );
RendererAssets::reset_for_tests();
$assert( RendererAssets::ready() && 1 === count( $test_transients ), 'An unchanged renderer inventory must reuse its exact cached verification receipt.' );
$assert( Renderer::ready_for_public(), 'Public renderer readiness does not require the complete asset contract.' );
$renderer_source = file_get_contents( $root . '/src/DigitalIslands/Renderer.php' );
$assert( false !== strpos( $renderer_source, 'if ( ! RendererAssets::ready() )' ), 'Renderer readiness must fail closed through the exact asset contract.' );
$manifest = RendererAssets::verify();
$assert(
	RendererAssets::MANIFEST_SHA256 === hash_file( 'sha256', $root . '/' . RendererAssets::MANIFEST_PATH ),
	'The renderer manifest is not hard-pinned by the PHP release boundary.'
);
$assert( 58 === $manifest['terrain']['tile_count'], 'The terrain contract must contain exactly 58 z8-z13 tiles.' );
$assert( 1092999 === $manifest['terrain']['total_bytes'], 'The exact terrain byte receipt changed.' );
$assert( 65 === count( $manifest['inventory'] ), 'The renderer inventory must contain seven pinned files and 58 terrain tiles.' );
$assert( RendererAssets::SATELLITE_BYTES === $manifest['inventory'][ RendererAssets::SATELLITE_PATH ]['bytes'], 'The satellite image byte receipt changed.' );
$assert( RendererAssets::SATELLITE_SHA256 === $manifest['inventory'][ RendererAssets::SATELLITE_PATH ]['sha256'], 'The satellite image SHA-256 receipt changed.' );
$assert( RendererAssets::SATELLITE_WIDTH === $manifest['satellite']['width'] && RendererAssets::SATELLITE_HEIGHT === $manifest['satellite']['height'], 'The satellite image dimensions changed.' );
$assert( 'EPSG:3857' === $manifest['satellite']['projection'], 'The satellite image projection changed.' );
$assert( RendererAssets::SATELLITE_SOURCE_ITEM_ID === $manifest['satellite']['source_item_id'], 'The reviewed Sentinel-2 source item changed.' );
$assert( RendererAssets::MAPLIBRE_VERSION === $manifest['dependencies']['maplibre']['version'], 'MapLibre is not pinned to the reviewed version.' );
$assert( RendererAssets::PMTILES_VERSION === $manifest['dependencies']['pmtiles']['version'], 'PMTiles is not pinned to the reviewed version.' );

$assets = new Assets();
$assets->enqueue();
$assert(
	array(
		'style:' . Assets::MAPLIBRE_STYLE_HANDLE,
		'style:' . Assets::STYLE_HANDLE,
		'script:' . Assets::PMTILES_SCRIPT_HANDLE,
		'script:' . Assets::MAPLIBRE_SCRIPT_HANDLE,
		'script:' . Assets::SCRIPT_HANDLE,
		'inline:' . Assets::SCRIPT_HANDLE . ':before',
	) === $test_asset_order,
	'The local renderer dependencies or reviewed config are not enqueued in exact order.'
);
$assert( array() === $test_styles[ Assets::MAPLIBRE_STYLE_HANDLE ]['dependencies'], 'MapLibre CSS must be the first renderer style.' );
$assert( array( Assets::MAPLIBRE_STYLE_HANDLE ) === $test_styles[ Assets::STYLE_HANDLE ]['dependencies'], 'The app CSS must depend on MapLibre CSS.' );
$assert( array() === $test_scripts[ Assets::PMTILES_SCRIPT_HANDLE ]['dependencies'], 'PMTiles must be the first renderer script.' );
$assert( array( Assets::PMTILES_SCRIPT_HANDLE ) === $test_scripts[ Assets::MAPLIBRE_SCRIPT_HANDLE ]['dependencies'], 'MapLibre must load after PMTiles.' );
$assert( array( Assets::MAPLIBRE_SCRIPT_HANDLE ) === $test_scripts[ Assets::SCRIPT_HANDLE ]['dependencies'], 'The app must load after the reviewed renderer stack.' );
foreach ( array( Assets::PMTILES_SCRIPT_HANDLE, Assets::MAPLIBRE_SCRIPT_HANDLE, Assets::SCRIPT_HANDLE ) as $script_handle ) {
	$assert( 'defer' === $test_script_data[ $script_handle ]['strategy'], 'Every ordered renderer script must use the defer strategy.' );
	$assert( THAILAND_PLATFORM_VERSION === $test_scripts[ $script_handle ]['version'], 'Every renderer script must use the plugin release version.' );
}
$inline = $test_inline_scripts[ Assets::SCRIPT_HANDLE ][0];
$assert( 'before' === $inline['position'], 'The reviewed renderer config must execute before the app.' );
$matched = preg_match( '/\Awindow\.ThailandDigitalIslandsConfig=Object\.freeze\((.*)\);\z/s', $inline['data'], $inline_match );
$assert( 1 === $matched, 'The reviewed renderer config assignment is malformed.' );
$config = json_decode( $inline_match[1], true );
$assert( is_array( $config ) && true === $config['reviewed'], 'The inline renderer config is not explicitly reviewed.' );
$assert( RendererAssets::ISLAND_ID === $config['islandGeoId'], 'The inline renderer config is bound to the wrong island.' );
$assert( 8 === $config['maplibre']['terrainMinZoom'] && 13 === $config['maplibre']['terrainMaxZoom'], 'The inline terrain zoom boundary changed.' );
$assert( $manifest['terrain']['inventory_sha256'] === $config['maplibre']['terrainManifestSha256'], 'The inline terrain digest is not manifest-bound.' );
$assert( $manifest['inventory'][ RendererAssets::BASEMAP_PATH ]['sha256'] === $config['maplibre']['basemapSha256'], 'The inline basemap digest is not manifest-bound.' );
$assert( RendererAssets::BOUNDS === $config['maplibre']['satelliteBounds'], 'The inline satellite crop bounds changed.' );
$assert( RendererAssets::SATELLITE_ATTRIBUTION === $config['maplibre']['satelliteAttribution'], 'The inline satellite attribution changed.' );
$assert( RendererAssets::SATELLITE_OBSERVED_AT === $config['maplibre']['satelliteObservedAt'], 'The inline satellite observation timestamp changed.' );
foreach ( array( 'vectorPmtilesUrl', 'terrainUrlTemplate', 'satelliteUrl' ) as $url_key ) {
	$url = parse_url( $config['maplibre'][ $url_key ] );
	$assert( is_array( $url ) && 'https' === $url['scheme'] && 'thai-land.co.il' === $url['host'], 'A renderer data URL is not exact same-origin HTTPS.' );
}
$assert( false === strpos( $inline['data'], 'nonce' ) && false === strpos( $inline['data'], 'token' ), 'The public renderer config must contain no nonce or token.' );

/* Isolated fail-closed manifest and file tamper tests. */
$renderer_assets_reflection = new ReflectionClass( RendererAssets::class );
$verify_at_root = $renderer_assets_reflection->getMethod( 'verify_at_root' );
$verify_at_root->setAccessible( true );
$terrain_digest = $renderer_assets_reflection->getMethod( 'terrain_inventory_digest' );
$terrain_digest->setAccessible( true );
$safe_relative_path = $renderer_assets_reflection->getMethod( 'assert_safe_relative_path' );
$safe_relative_path->setAccessible( true );
$same_origin = $renderer_assets_reflection->getMethod( 'same_origin' );
$same_origin->setAccessible( true );
$expect_failure = static function ( $callback ) {
	try {
		$callback();
		return false;
	} catch ( Throwable $exception ) {
		unset( $exception );
		return true;
	}
};
$assert(
	$expect_failure( static function () use ( $safe_relative_path ) {
		$safe_relative_path->invoke( null, '../outside-renderer.png' );
	} ),
	'A renderer manifest path with traversal must fail closed.'
);
$assert(
	false === $same_origin->invoke( null, 'https://assets.example.test/map.pmtiles', 'https://thai-land.co.il/' ),
	'An external runtime data origin must fail closed.'
);
$renderer_assets_source = file_get_contents( $root . '/src/DigitalIslands/RendererAssets.php' );
$assert( false !== strpos( $renderer_assets_source, 'is_link( $path )' ), 'Renderer file verification must explicitly reject symbolic links.' );
$fixture_root = sys_get_temp_dir() . '/thp-di-renderer-' . bin2hex( random_bytes( 8 ) );
mkdir( $fixture_root, 0777, true );
$assert(
	$expect_failure( static function () use ( $verify_at_root, $fixture_root ) {
		$verify_at_root->invoke( null, $fixture_root, false );
	} ),
	'A missing renderer manifest must fail closed.'
);

$fixture_manifest = $manifest;
$terrain_receipts = array();
foreach ( $fixture_manifest['inventory'] as $relative_path => &$receipt ) {
	$fixture_path = $fixture_root . '/' . $relative_path;
	if ( ! is_dir( dirname( $fixture_path ) ) ) {
		mkdir( dirname( $fixture_path ), 0777, true );
	}
	if ( RendererAssets::SATELLITE_PATH === $relative_path ) {
		copy( $root . '/' . $relative_path, $fixture_path );
		continue;
	}
	file_put_contents( $fixture_path, 'x' );
	$receipt = array( 'bytes' => 1, 'sha256' => hash( 'sha256', 'x' ) );
	if ( 0 === strpos( $relative_path, RendererAssets::TERRAIN_BASE_PATH . '/' ) ) {
		$terrain_receipts[ $relative_path ] = $receipt;
	}
}
unset( $receipt );
$fixture_manifest['terrain']['total_bytes'] = count( $terrain_receipts );
$fixture_manifest['terrain']['inventory_sha256'] = $terrain_digest->invoke( null, $terrain_receipts );
$fixture_manifest_path = $fixture_root . '/' . RendererAssets::MANIFEST_PATH;
mkdir( dirname( $fixture_manifest_path ), 0777, true );
$write_fixture_manifest = static function () use ( &$fixture_manifest, $fixture_manifest_path ) {
	file_put_contents(
		$fixture_manifest_path,
		json_encode( $fixture_manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE )
	);
};
$write_fixture_manifest();
$fixture_verified = $verify_at_root->invoke( null, $fixture_root, false );
$assert( RendererAssets::CONTRACT_ID === $fixture_verified['contract_id'], 'The isolated valid renderer fixture did not verify.' );

$unexpected_path = $fixture_root . '/' . RendererAssets::TERRAIN_BASE_PATH . '/8/199/unmanifested.png';
file_put_contents( $unexpected_path, 'x' );
$assert(
	$expect_failure( static function () use ( $verify_at_root, $fixture_root ) {
		$verify_at_root->invoke( null, $fixture_root, false );
	} ),
	'An unmanifested renderer file must fail the exact filesystem boundary.'
);
unlink( $unexpected_path );

file_put_contents( $fixture_manifest_path, '{"contract_id":"first","contract_id":"duplicate"}' );
$assert(
	$expect_failure( static function () use ( $verify_at_root, $fixture_root ) {
		$verify_at_root->invoke( null, $fixture_root, false );
	} ),
	'A malformed or duplicate-key renderer manifest must fail closed.'
);
$write_fixture_manifest();

$tampered_path = $fixture_root . '/' . RendererAssets::MAPLIBRE_STYLE_PATH;
file_put_contents( $tampered_path, 'y' );
$assert(
	$expect_failure( static function () use ( $verify_at_root, $fixture_root ) {
		$verify_at_root->invoke( null, $fixture_root, false );
	} ),
	'A same-byte renderer file tamper must fail closed.'
);
file_put_contents( $tampered_path, 'x' );

$missing_path = $fixture_root . '/' . RendererAssets::PMTILES_LICENSE_PATH;
unlink( $missing_path );
$assert(
	$expect_failure( static function () use ( $verify_at_root, $fixture_root ) {
		$verify_at_root->invoke( null, $fixture_root, false );
	} ),
	'A missing renderer dependency receipt must fail closed.'
);

$delete_fixture = static function ( $path ) use ( &$delete_fixture ) {
	if ( is_dir( $path ) && ! is_link( $path ) ) {
		foreach ( scandir( $path ) as $entry ) {
			if ( '.' !== $entry && '..' !== $entry ) {
				$delete_fixture( $path . '/' . $entry );
			}
		}
		rmdir( $path );
		return;
	}
	if ( file_exists( $path ) || is_link( $path ) ) {
		unlink( $path );
	}
};
$delete_fixture( $fixture_root );

fwrite( STDOUT, sprintf( 'PASS: digital islands administrator settings security gates (%d assertions).', $assertions ) . PHP_EOL );
