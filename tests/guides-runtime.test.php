<?php
/**
 * Dependency-free tests for the isolated priority guides runtime.
 */

define( 'ABSPATH', __DIR__ . '/wordpress/' );
define( 'THAILAND_PLATFORM_DIR', dirname( __DIR__ ) . DIRECTORY_SEPARATOR );
define( 'THAILAND_PLATFORM_FILE', THAILAND_PLATFORM_DIR . 'thailand-platform.php' );
define( 'THAILAND_PLATFORM_VERSION', 'test' );

$GLOBALS['thp_guides_test_actions']      = array();
$GLOBALS['thp_guides_test_filters']      = array();
$GLOBALS['thp_guides_test_options']      = array();
$GLOBALS['thp_guides_test_capabilities'] = array();
$GLOBALS['thp_guides_test_singular']     = false;
$GLOBALS['thp_guides_test_preview']      = false;
$GLOBALS['thp_guides_test_feed']         = false;
$GLOBALS['thp_guides_test_embed']        = false;
$GLOBALS['thp_guides_test_post_id']      = 0;
$GLOBALS['thp_guides_test_types']        = array();
$GLOBALS['thp_guides_test_statuses']     = array();
$GLOBALS['thp_guides_test_passwords']    = array();
$GLOBALS['thp_guides_test_stored_passwords'] = array();
$GLOBALS['thp_guides_test_permalinks']   = array();
$GLOBALS['thp_guides_test_styles']       = array();
$GLOBALS['thp_guides_test_scripts']      = array();
$GLOBALS['thp_guides_test_script_data']  = array();
$GLOBALS['thp_guides_test_status_headers'] = array();
$GLOBALS['thp_guides_test_nocache_calls']  = 0;
$GLOBALS['thp_guides_test_cache_flush_calls'] = 0;

function thp_guides_assert( $condition, $message ) {
	if ( ! $condition ) {
		fwrite( STDERR, 'FAIL: ' . $message . PHP_EOL );
		exit( 1 );
	}
}

function thp_guides_register_hook( $registry, $hook, $callback, $priority, $accepted_args ) {
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
	thp_guides_register_hook( 'thp_guides_test_actions', $hook, $callback, $priority, $accepted_args );
}

function add_filter( $hook, $callback, $priority = 10, $accepted_args = 1 ) {
	thp_guides_register_hook( 'thp_guides_test_filters', $hook, $callback, $priority, $accepted_args );
}

function thp_guides_apply_filters( $hook, $value ) {
	$arguments = array_slice( func_get_args(), 2 );
	$callbacks = $GLOBALS['thp_guides_test_filters'][ $hook ] ?? array();
	usort(
		$callbacks,
		static function ( $left, $right ) {
			return $left['priority'] <=> $right['priority'];
		}
	);
	foreach ( $callbacks as $item ) {
		$call = array_merge( array( $value ), $arguments );
		$value = call_user_func_array(
			$item['callback'],
			array_slice( $call, 0, $item['accepted_args'] )
		);
	}
	return $value;
}

function get_option( $name, $default = false ) {
	return array_key_exists( $name, $GLOBALS['thp_guides_test_options'] )
		? $GLOBALS['thp_guides_test_options'][ $name ]
		: $default;
}

function sanitize_key( $value ) {
	return preg_replace( '/[^a-z0-9_\-]/', '', strtolower( (string) $value ) );
}

function sanitize_text_field( $value ) {
	return trim( strip_tags( (string) $value ) );
}

function sanitize_html_class( $value ) {
	return preg_replace( '/[^A-Za-z0-9_-]/', '', (string) $value );
}

function wp_unslash( $value ) {
	return is_string( $value ) ? stripslashes( $value ) : $value;
}

function current_user_can( $capability ) {
	return ! empty( $GLOBALS['thp_guides_test_capabilities'][ $capability ] );
}

function is_singular() {
	return (bool) $GLOBALS['thp_guides_test_singular'];
}

function is_preview() {
	return (bool) $GLOBALS['thp_guides_test_preview'];
}

function is_feed() {
	return (bool) $GLOBALS['thp_guides_test_feed'];
}

function is_embed() {
	return (bool) $GLOBALS['thp_guides_test_embed'];
}

function get_queried_object_id() {
	return (int) $GLOBALS['thp_guides_test_post_id'];
}

function get_post_type( $post_id ) {
	return $GLOBALS['thp_guides_test_types'][ (int) $post_id ] ?? null;
}

function get_post_status( $post_id ) {
	return $GLOBALS['thp_guides_test_statuses'][ (int) $post_id ] ?? null;
}

function get_post( $post_id ) {
	$post_id = (int) $post_id;
	if ( ! isset( $GLOBALS['thp_guides_test_types'][ $post_id ] ) ) {
		return null;
	}
	return (object) array(
		'ID'            => $post_id,
		'post_type'     => $GLOBALS['thp_guides_test_types'][ $post_id ],
		'post_status'   => $GLOBALS['thp_guides_test_statuses'][ $post_id ] ?? '',
		'post_password' => $GLOBALS['thp_guides_test_stored_passwords'][ $post_id ] ?? '',
	);
}

function get_permalink( $post_id ) {
	return $GLOBALS['thp_guides_test_permalinks'][ (int) $post_id ] ?? false;
}

function post_password_required( $post_id = 0 ) {
	return ! empty( $GLOBALS['thp_guides_test_passwords'][ (int) $post_id ] );
}

function absint( $value ) {
	return abs( (int) $value );
}

function wp_parse_url( $url, $component = -1 ) {
	return parse_url( $url, $component );
}

function home_url( $path = '/' ) {
	return 'https://example.test/' . ltrim( (string) $path, '/' );
}

function plugins_url( $path, $plugin_file = '' ) {
	unset( $plugin_file );
	return 'https://example.test/wp-content/plugins/thailand-platform/' . ltrim( (string) $path, '/' );
}

function add_query_arg( $key, $value = null, $url = null ) {
	if ( is_array( $key ) ) {
		$arguments = $key;
		$target    = (string) $value;
	} else {
		$arguments = array( (string) $key => $value );
		$target    = (string) $url;
	}
	$parts = parse_url( $target );
	$query = array();
	if ( isset( $parts['query'] ) ) {
		parse_str( $parts['query'], $query );
	}
	$query = array_merge( $query, $arguments );
	$base  = ( $parts['scheme'] ?? 'https' ) . '://' . ( $parts['host'] ?? 'example.test' ) . ( $parts['path'] ?? '/' );
	return $base . '?' . http_build_query( $query, '', '&', PHP_QUERY_RFC3986 );
}

function get_preview_post_link( $post_id ) {
	return add_query_arg(
		array(
			'page_id' => absint( $post_id ),
			'preview' => 'true',
		),
		home_url( '/' )
	);
}

function nocache_headers() {
	++$GLOBALS['thp_guides_test_nocache_calls'];
}

function status_header( $status ) {
	$GLOBALS['thp_guides_test_status_headers'][] = (int) $status;
}

function wp_enqueue_style( $handle, $source, $dependencies = array(), $version = false, $media = 'all' ) {
	$GLOBALS['thp_guides_test_styles'][ $handle ] = compact( 'source', 'dependencies', 'version', 'media' );
}

function wp_enqueue_script( $handle, $source, $dependencies = array(), $version = false, $in_footer = false ) {
	$GLOBALS['thp_guides_test_scripts'][ $handle ] = compact( 'source', 'dependencies', 'version', 'in_footer' );
}

function wp_script_add_data( $handle, $key, $value ) {
	$GLOBALS['thp_guides_test_script_data'][ $handle ][ $key ] = $value;
	return true;
}

function wp_cache_flush() {
	++$GLOBALS['thp_guides_test_cache_flush_calls'];
	return true;
}

function register_setting( $group, $option, $arguments = array() ) {
	unset( $group, $option, $arguments );
}

function add_options_page( $page_title, $menu_title, $capability, $slug, $callback ) {
	unset( $page_title, $menu_title, $capability, $callback );
	return $slug;
}

function esc_html( $value ) {
	return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
}

function esc_attr( $value ) {
	return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
}

function esc_url( $value ) {
	return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
}

function wp_json_encode( $value, $flags = 0, $depth = 512 ) {
	return json_encode( $value, $flags, $depth );
}

require THAILAND_PLATFORM_DIR . 'src/Guides/FeatureFlag.php';
require THAILAND_PLATFORM_DIR . 'src/Guides/Repository.php';
require THAILAND_PLATFORM_DIR . 'src/Guides/Context.php';
require THAILAND_PLATFORM_DIR . 'src/Guides/Assets.php';
require THAILAND_PLATFORM_DIR . 'src/Guides/HomepageNavigation.php';
require THAILAND_PLATFORM_DIR . 'src/Guides/View.php';
require THAILAND_PLATFORM_DIR . 'src/Guides/Seo.php';
require THAILAND_PLATFORM_DIR . 'src/Guides/Schema.php';
require THAILAND_PLATFORM_DIR . 'src/Guides/Renderer.php';
require THAILAND_PLATFORM_DIR . 'src/Guides/Settings.php';
require THAILAND_PLATFORM_DIR . 'src/Guides/Module.php';

use Thailand_Platform\Guides\Assets as Guide_Assets;
use Thailand_Platform\Guides\Context as Guide_Context;
use Thailand_Platform\Guides\FeatureFlag as Guide_FeatureFlag;
use Thailand_Platform\Guides\HomepageNavigation as Guide_Homepage_Navigation;
use Thailand_Platform\Guides\Module as Guide_Module;
use Thailand_Platform\Guides\Renderer as Guide_Renderer;
use Thailand_Platform\Guides\Repository as Guide_Repository;
use Thailand_Platform\Guides\Schema as Guide_Schema;
use Thailand_Platform\Guides\Seo as Guide_Seo;
use Thailand_Platform\Guides\Settings as Guide_Settings;
use Thailand_Platform\Guides\View as Guide_View;

class Thp_Guides_Test_Modified_Time_Presenter {}
class_alias(
	'Thp_Guides_Test_Modified_Time_Presenter',
	'Yoast\\WP\\SEO\\Presenters\\Open_Graph\\Article_Modified_Time_Presenter'
);

( new Guide_Module() )->register();

function thp_guides_set_request( $mode, $post_id, $path, $post_type = 'post', $status = 'publish', $preview = false, $canary = false, $admin = false, $password = false ) {
	$GLOBALS['thp_guides_test_options'][ Guide_FeatureFlag::OPTION ] = $mode;
	$GLOBALS['thp_guides_test_singular']     = true;
	$GLOBALS['thp_guides_test_preview']      = $preview;
	$GLOBALS['thp_guides_test_feed']         = false;
	$GLOBALS['thp_guides_test_embed']        = false;
	$GLOBALS['thp_guides_test_post_id']      = $post_id;
	$GLOBALS['thp_guides_test_types'][ $post_id ] = $post_type;
	$GLOBALS['thp_guides_test_statuses'][ $post_id ] = $status;
	$GLOBALS['thp_guides_test_passwords'][ $post_id ] = $password;
	$GLOBALS['thp_guides_test_permalinks'][ $post_id ] = home_url( $path );
	$GLOBALS['thp_guides_test_capabilities']['manage_options'] = $admin;
	$_SERVER['REQUEST_URI'] = $path;
	$_GET = $canary ? array( Guide_FeatureFlag::CANARY_QUERY => '1' ) : array();
	Guide_Context::reset_for_tests();
}

$registry = Guide_Repository::all();
thp_guides_assert( 'thailand-priority-guides-v1' === $registry['contract_id'], 'registry contract mismatch' );
thp_guides_assert( 7 === count( $registry['routes_by_id'] ), 'route count mismatch' );
thp_guides_assert( 'thailand-visas' === $registry['route_id_by_post_id']['846'], 'visa collection identity mismatch' );
thp_guides_assert( 'thailand-law-and-tax' === $registry['route_id_by_post_id']['848'], 'law collection identity mismatch' );
thp_guides_assert( array( 720, 1200, 1717 ) === $registry['asset_contract']['widths'], 'asset widths mismatch' );
thp_guides_assert( 1 === count( $GLOBALS['thp_guides_test_filters'][ Guide_Homepage_Navigation::FILTER ] ?? array() ), 'homepage navigation filter registration mismatch' );
thp_guides_assert( 1 === count( $GLOBALS['thp_guides_test_actions']['transition_post_status'] ?? array() ), 'hub transition cache hook registration mismatch' );

$contextual_anchors = array(
	'home' => $registry['parents_by_id']['home']['primary_keyword'],
);
foreach ( $registry['routes_by_id'] as $route_id => $route ) {
	$contextual_anchors[ $route_id ] = $route['ownership']['primary_keyword'];
}
foreach ( $registry['routes_by_id'] as $route_id => $route ) {
	$targets = array_column( $route['contextual_links'], 'target_owner_id' );
	thp_guides_assert( ! empty( $targets ), 'contextual links missing for ' . $route_id );
	thp_guides_assert( count( $targets ) === count( array_unique( $targets ) ), 'duplicate contextual target for ' . $route_id );
	thp_guides_assert( in_array( $route['parent_owner_id'], $targets, true ), 'canonical parent contextual target missing for ' . $route_id );
	foreach ( $route['contextual_links'] as $item ) {
		thp_guides_assert( isset( $contextual_anchors[ $item['target_owner_id'] ] ), 'unknown contextual target in artifact' );
		thp_guides_assert( $contextual_anchors[ $item['target_owner_id'] ] === $item['anchor_text'], 'contextual anchor mismatch in artifact' );
	}
}
foreach ( array( 'thailand-visas', 'thailand-law-and-tax' ) as $hub_id ) {
	thp_guides_assert( 'home' === $registry['routes_by_id'][ $hub_id ]['contextual_links'][0]['target_owner_id'], 'hub home contextual target missing for ' . $hub_id );
}
thp_guides_assert(
	in_array( 'thailand-entry-requirements', array_column( $registry['routes_by_id']['thailand-entry-april-2022']['contextual_links'], 'target_owner_id' ), true ),
	'historical current-entry contextual target missing'
);

foreach ( $registry['routes_by_id'] as $route ) {
	$GLOBALS['thp_guides_test_types'][ $route['wordpress']['post_id'] ] = $route['wordpress']['post_type'];
	$GLOBALS['thp_guides_test_statuses'][ $route['wordpress']['post_id'] ] = 'collection' === $route['kind'] ? 'draft' : 'publish';
	thp_guides_assert( Guide_Assets::ready( $route ), 'asset readiness failed for ' . $route['route_id'] );
	$paths = Guide_Assets::hero_paths( $route );
	thp_guides_assert( 3 === count( $paths ), 'hero variant count mismatch for ' . $route['route_id'] );
	foreach ( array( 720, 1200, 1717 ) as $width ) {
		$expected = 'assets/guides/images/' . $route['asset_key'] . '-' . $width . '.webp';
		$found    = false;
		foreach ( $paths as $path ) {
			$normalized_path = str_replace( '\\', '/', $path );
			if ( substr( $normalized_path, -strlen( $expected ) ) === $expected ) {
				$found = true;
			}
		}
		thp_guides_assert( $found, 'hero filename mismatch for ' . $route['route_id'] . ' at ' . $width );
	}
}

$homepage_source = file_get_contents( THAILAND_PLATFORM_DIR . 'resources/homepage.html' );
$homepage_nav    = new Guide_Homepage_Navigation();
thp_guides_assert( is_string( $homepage_source ) && '' !== $homepage_source, 'homepage source is unavailable' );
foreach ( array( Guide_Homepage_Navigation::DESKTOP_MARKER, Guide_Homepage_Navigation::MOBILE_MARKER, Guide_Homepage_Navigation::FOOTER_MARKER ) as $marker ) {
	thp_guides_assert( 1 === substr_count( $homepage_source, $marker ), 'homepage navigation marker count mismatch' );
}

$visa_route = $registry['routes_by_id']['thailand-visas'];
$law_route  = $registry['routes_by_id']['thailand-law-and-tax'];
$visa_id    = $visa_route['wordpress']['post_id'];
$law_id     = $law_route['wordpress']['post_id'];
$GLOBALS['thp_guides_test_types'][ $visa_id ]      = 'page';
$GLOBALS['thp_guides_test_types'][ $law_id ]       = 'page';
$GLOBALS['thp_guides_test_statuses'][ $visa_id ]   = 'publish';
$GLOBALS['thp_guides_test_statuses'][ $law_id ]    = 'publish';
$GLOBALS['thp_guides_test_permalinks'][ $visa_id ] = home_url( $visa_route['path'] );
$GLOBALS['thp_guides_test_permalinks'][ $law_id ]  = home_url( $law_route['path'] );
$GLOBALS['thp_guides_test_passwords'][ $visa_id ]  = false;
$GLOBALS['thp_guides_test_passwords'][ $law_id ]   = false;
$GLOBALS['thp_guides_test_stored_passwords'][ $visa_id ] = '';
$GLOBALS['thp_guides_test_stored_passwords'][ $law_id ]  = '';

$GLOBALS['thp_guides_test_options'][ Guide_FeatureFlag::OPTION ] = Guide_FeatureFlag::MODE_OFF;
thp_guides_assert( $homepage_source === $homepage_nav->inject( $homepage_source ), 'Off mode changed homepage hub navigation' );
$GLOBALS['thp_guides_test_options'][ Guide_FeatureFlag::OPTION ] = Guide_FeatureFlag::MODE_CANARY;
thp_guides_assert( $homepage_source === $homepage_nav->inject( $homepage_source ), 'Canary mode exposed homepage hub navigation' );

$GLOBALS['thp_guides_test_options'][ Guide_FeatureFlag::OPTION ] = Guide_FeatureFlag::MODE_LIVE;
$GLOBALS['thp_guides_test_statuses'][ $law_id ] = 'draft';
thp_guides_assert( $homepage_source === $homepage_nav->inject( $homepage_source ), 'one draft hub produced a partial homepage bridge' );
$GLOBALS['thp_guides_test_statuses'][ $law_id ] = 'publish';

$GLOBALS['thp_guides_test_passwords'][ $law_id ] = true;
thp_guides_assert( $homepage_source === $homepage_nav->inject( $homepage_source ), 'password-protected hub entered homepage navigation' );
$GLOBALS['thp_guides_test_passwords'][ $law_id ] = false;

$GLOBALS['thp_guides_test_stored_passwords'][ $law_id ] = 'protected-with-valid-cookie';
thp_guides_assert( $homepage_source === $homepage_nav->inject( $homepage_source ), 'stored-password hub entered homepage navigation after a visitor cookie' );
$GLOBALS['thp_guides_test_stored_passwords'][ $law_id ] = '';

$GLOBALS['thp_guides_test_types'][ $law_id ] = 'post';
thp_guides_assert( $homepage_source === $homepage_nav->inject( $homepage_source ), 'wrong-type hub entered homepage navigation' );
$GLOBALS['thp_guides_test_types'][ $law_id ] = 'page';

$GLOBALS['thp_guides_test_permalinks'][ $law_id ] = home_url( '/wrong-law-path/' );
thp_guides_assert( $homepage_source === $homepage_nav->inject( $homepage_source ), 'wrong-path hub entered homepage navigation' );
$GLOBALS['thp_guides_test_permalinks'][ $law_id ] = home_url( $law_route['path'] );

$missing_marker_source = str_replace( Guide_Homepage_Navigation::MOBILE_MARKER, '', $homepage_source );
thp_guides_assert( $missing_marker_source === $homepage_nav->inject( $missing_marker_source ), 'incomplete homepage markers produced a partial bridge' );

$missing_asset        = THAILAND_PLATFORM_DIR . 'assets/guides/images/' . $visa_route['asset_key'] . '-720.webp';
$held_asset           = $missing_asset . '.navigation-test-unavailable';
$missing_asset_result = null;
$asset_moved          = false;
$asset_restored       = false;
try {
	$asset_moved = rename( $missing_asset, $held_asset );
	if ( $asset_moved ) {
		$missing_asset_result = $homepage_nav->inject( $homepage_source );
	}
} finally {
	if ( $asset_moved ) {
		$asset_restored = rename( $held_asset, $missing_asset );
	}
}
thp_guides_assert( $asset_moved && $asset_restored, 'missing-asset homepage test could not restore the reviewed asset' );
thp_guides_assert( $homepage_source === $missing_asset_result, 'missing hub asset produced homepage links' );

$live_homepage = $homepage_nav->inject( $homepage_source );
thp_guides_assert( $live_homepage !== $homepage_source, 'two published hubs did not activate homepage navigation' );
thp_guides_assert( 0 === substr_count( $live_homepage, 'THP_GUIDES_' ), 'live homepage retained navigation placeholders' );
foreach ( array( 'desktop', 'mobile', 'footer' ) as $surface ) {
	thp_guides_assert( 1 === substr_count( $live_homepage, 'data-thp-guides-home-nav="' . $surface . '"' ), 'homepage surface count mismatch for ' . $surface );
}
foreach ( array( $visa_route, $law_route ) as $hub_route ) {
	$route_id = $hub_route['route_id'];
	$href     = 'href="' . home_url( $hub_route['path'] ) . '"';
	$anchor   = '>' . $hub_route['ownership']['primary_keyword'] . '</a>';
	thp_guides_assert( 3 === substr_count( $live_homepage, 'data-thp-guides-home-link="' . $route_id . '"' ), 'hub surface coverage mismatch for ' . $route_id );
	thp_guides_assert( 3 === substr_count( $live_homepage, $href ), 'hub canonical href count mismatch for ' . $route_id );
	thp_guides_assert( 3 === substr_count( $live_homepage, $anchor ), 'hub primary-keyword anchor count mismatch for ' . $route_id );
}

$cache_calls_before = $GLOBALS['thp_guides_test_cache_flush_calls'];
$homepage_nav->purge_after_hub_change( 'publish', 'draft', (object) array( 'ID' => 999, 'post_type' => 'page' ) );
thp_guides_assert( $cache_calls_before === $GLOBALS['thp_guides_test_cache_flush_calls'], 'unmanaged page purged homepage cache' );
$homepage_nav->purge_after_hub_change( 'publish', 'draft', (object) array( 'ID' => $visa_id, 'post_type' => 'page' ) );
thp_guides_assert( $cache_calls_before + 1 === $GLOBALS['thp_guides_test_cache_flush_calls'], 'published hub did not purge homepage cache' );
$GLOBALS['thp_guides_test_cache_flush_calls'] = $cache_calls_before;

$entry = Guide_Repository::route_by_id( 'thailand-entry-requirements' );
$cannabis = Guide_Repository::route_by_id( 'thailand-cannabis-law' );
thp_guides_assert(
	Guide_Assets::hero_url( $entry, 1717 ) !== Guide_Assets::hero_url( $cannabis, 1717 ),
	'route-specific assets collide'
);
$invalid_asset = $entry;
$invalid_asset['asset_key'] = '../unsafe';
thp_guides_assert( ! Guide_Assets::ready( $invalid_asset ), 'invalid asset key did not fail closed' );

thp_guides_set_request( Guide_FeatureFlag::MODE_LIVE, 1, '/hello-world/' );
$route = Guide_Context::route();
thp_guides_assert( is_array( $route ) && 'thailand-entry-requirements' === $route['route_id'], 'published entry route did not resolve' );
thp_guides_assert( Guide_Context::should_render(), 'complete entry route is not renderable' );
$renderer = new Guide_Renderer();
thp_guides_assert(
	THAILAND_PLATFORM_DIR . 'templates/guides/document.php' === $renderer->template( 'legacy.php' ),
	'guide template was not selected'
);

$seo = new Guide_Seo();
thp_guides_assert(
	$entry['public']['seo_title'] . ' | Thai-Land.co.il' === $seo->title( 'Legacy' ),
	'entry SEO title mismatch'
);
$robots = $seo->robots( array( 'noindex' => true, 'nofollow' => true ) );
thp_guides_assert( isset( $robots['index'], $robots['follow'] ) && ! isset( $robots['noindex'], $robots['nofollow'] ), 'live robots mismatch' );
$presenters = $seo->yoast_frontend_presenters(
	array(
		new Thp_Guides_Test_Modified_Time_Presenter(),
		new stdClass(),
	)
);
thp_guides_assert( 1 === count( $presenters ) && $presenters[0] instanceof stdClass, 'stale Yoast modified-time presenter was not removed' );
ob_start();
$seo->modified_time_meta();
$modified_time_markup = ob_get_clean();
thp_guides_assert( 1 === substr_count( $modified_time_markup, 'property="article:modified_time"' ), 'route-owned modified-time tag count mismatch' );
thp_guides_assert( false !== strpos( $modified_time_markup, $entry['modified_on'] . 'T00:00:00+00:00' ), 'route-owned modified-time value mismatch' );

$GLOBALS['thp_guides_test_types'][62]      = 'post';
$GLOBALS['thp_guides_test_statuses'][62]   = 'publish';
$GLOBALS['thp_guides_test_permalinks'][62] = home_url( Guide_Repository::route_by_id( 'thailand-entry-april-2022' )['path'] );
$excluded = $seo->sitemap_excluded_post_ids( array( 9001, 62 ) );
thp_guides_assert( array( 9001, 62 ) === $excluded, 'Yoast sitemap exclusions were not merged and deduplicated' );
$entry_sitemap = $seo->sitemap_entry(
	array( 'loc' => home_url( $entry['path'] ), 'mod' => '2022-10-29T00:00:00+00:00' ),
	'post',
	(object) array( 'ID' => 1, 'post_type' => 'post' )
);
thp_guides_assert( $entry['modified_on'] . 'T00:00:00+00:00' === $entry_sitemap['mod'], 'Yoast sitemap modified time did not use the reviewed artifact' );
$historical_sitemap = $seo->sitemap_entry(
	array( 'loc' => $GLOBALS['thp_guides_test_permalinks'][62] ),
	'post',
	(object) array( 'ID' => 62, 'post_type' => 'post' )
);
thp_guides_assert( false === $historical_sitemap, 'reviewed noindex route remained in the Yoast sitemap' );
$unmanaged_sitemap = array( 'loc' => home_url( '/unmanaged/' ), 'mod' => '2020-01-01T00:00:00+00:00' );
thp_guides_assert(
	$unmanaged_sitemap === $seo->sitemap_entry( $unmanaged_sitemap, 'post', (object) array( 'ID' => 999, 'post_type' => 'post' ) ),
	'unmanaged sitemap entry was changed'
);
thp_guides_assert(
	$unmanaged_sitemap === $seo->sitemap_entry( $unmanaged_sitemap, 'user', (object) array( 'ID' => 1 ) ),
	'non-post sitemap object collided with a managed post ID'
);

$GLOBALS['thp_guides_test_permalinks'][1] = home_url( '/wrong-path/' );
thp_guides_assert(
	'unmodified' === $seo->sitemap_entry( array( 'mod' => 'unmodified' ), 'post', (object) array( 'ID' => 1, 'post_type' => 'post' ) )['mod'],
	'permalink identity drift did not fail closed'
);
$GLOBALS['thp_guides_test_permalinks'][1] = home_url( $entry['path'] );

thp_guides_set_request( Guide_FeatureFlag::MODE_LIVE, 62, '/החל-מאפריל-2022-מטיילים-יורשו-להיכנס-לתאי/' );
$historical_robots = $seo->robots( array() );
thp_guides_assert( isset( $historical_robots['noindex'], $historical_robots['follow'] ) && ! isset( $historical_robots['index'] ), 'historical noindex/follow mismatch' );

thp_guides_set_request( Guide_FeatureFlag::MODE_LIVE, 1, '/hello-world-extra/' );
thp_guides_assert( null === Guide_Context::route(), 'near-prefix path matched' );
thp_guides_set_request( Guide_FeatureFlag::MODE_LIVE, 1, '/hello-world' );
thp_guides_assert( null === Guide_Context::route(), 'missing trailing slash matched' );
thp_guides_set_request( Guide_FeatureFlag::MODE_LIVE, 1, '/%2Fhello-world/' );
thp_guides_assert( null === Guide_Context::route(), 'encoded path separator matched' );
thp_guides_set_request( Guide_FeatureFlag::MODE_LIVE, 1, '/hello-world/', 'page' );
thp_guides_assert( null === Guide_Context::route(), 'wrong post type matched' );
thp_guides_set_request( Guide_FeatureFlag::MODE_LIVE, 1, '/hello-world/', 'post', 'draft' );
thp_guides_assert( null === Guide_Context::route(), 'published-only draft matched' );
thp_guides_set_request( Guide_FeatureFlag::MODE_LIVE, 1, '/hello-world/', 'post', 'publish', false, false, false, true );
thp_guides_assert( null === Guide_Context::route(), 'password-protected route matched' );
thp_guides_set_request( Guide_FeatureFlag::MODE_OFF, 1, '/hello-world/' );
thp_guides_assert( null === Guide_Context::route(), 'Off mode resolved a route' );
$off_exclusions = array( 9001 );
thp_guides_assert( $off_exclusions === $seo->sitemap_excluded_post_ids( $off_exclusions ), 'Off mode changed Yoast sitemap exclusions' );
$off_entry = array( 'loc' => home_url( $entry['path'] ), 'mod' => 'legacy' );
thp_guides_assert( $off_entry === $seo->sitemap_entry( $off_entry, 'post', (object) array( 'ID' => 1, 'post_type' => 'post' ) ), 'Off mode changed a Yoast sitemap entry' );
$off_presenters = array( new Thp_Guides_Test_Modified_Time_Presenter() );
thp_guides_assert( $off_presenters === $seo->yoast_frontend_presenters( $off_presenters ), 'Off mode removed a Yoast presenter' );
ob_start();
$seo->modified_time_meta();
$off_modified_time_markup = ob_get_clean();
thp_guides_assert( '' === $off_modified_time_markup, 'Off mode emitted a modified-time tag' );
thp_guides_set_request( Guide_FeatureFlag::MODE_LIVE, 1, '/hello-world/', 'post', 'publish', false, true, true );
thp_guides_assert( null === Guide_Context::route(), 'Live mode accepted a canary URL' );

thp_guides_set_request( Guide_FeatureFlag::MODE_CANARY, 1, '/hello-world/', 'post', 'publish', false, true, true );
thp_guides_assert( 'thailand-entry-requirements' === Guide_Context::route()['route_id'], 'published administrator canary failed' );
$canary_robots = $seo->robots( array( 'index' => true, 'follow' => true ) );
thp_guides_assert( isset( $canary_robots['noindex'], $canary_robots['nofollow'], $canary_robots['noarchive'] ), 'canary robots mismatch' );
$canary_headers = $seo->headers( array() );
thp_guides_assert( false !== strpos( $canary_headers['Cache-Control'], 'private' ) && 'noindex, nofollow, noarchive' === $canary_headers['X-Robots-Tag'], 'canary headers mismatch' );

thp_guides_set_request( Guide_FeatureFlag::MODE_CANARY, 846, '/?page_id=846&preview=true', 'page', 'draft', true, true, true );
$visa_hub = Guide_Context::route();
thp_guides_assert( is_array( $visa_hub ) && 'thailand-visas' === $visa_hub['route_id'], 'protected draft hub canary failed' );
$preview_url = Guide_Context::canary_url( $visa_hub );
thp_guides_assert( false !== strpos( $preview_url, 'page_id=846' ) && false !== strpos( $preview_url, 'preview=true' ) && false !== strpos( $preview_url, Guide_FeatureFlag::CANARY_QUERY . '=1' ), 'draft canary URL mismatch' );

thp_guides_set_request( Guide_FeatureFlag::MODE_LIVE, 846, '/ויזות-לתאילנד/', 'page', 'draft', true );
thp_guides_assert( null === Guide_Context::route(), 'draft collection rendered in Live mode' );
thp_guides_set_request( Guide_FeatureFlag::MODE_CANARY, 846, '/?page_id=846&preview=true', 'page', 'draft', true, false, true );
thp_guides_assert( null === Guide_Context::route(), 'draft collection rendered without canary flag' );
thp_guides_set_request( Guide_FeatureFlag::MODE_CANARY, 1, '/?p=1&preview=true', 'post', 'draft', true, true, true );
thp_guides_assert( null === Guide_Context::route(), 'published-only route allowed a draft canary' );

thp_guides_set_request( Guide_FeatureFlag::MODE_CANARY, 846, '/?page_id=846&preview=true', 'page', 'draft', true, true, false );
$GLOBALS['wp_query'] = new class() {
	public $is_404 = false;
	public function set_404() {
		$this->is_404 = true;
	}
};
( new Guide_Context() )->protect_canary();
thp_guides_assert( $GLOBALS['wp_query']->is_404 && in_array( 404, $GLOBALS['thp_guides_test_status_headers'], true ), 'unauthorized canary did not return 404' );

$GLOBALS['thp_guides_test_statuses'][846] = 'publish';
thp_guides_set_request( Guide_FeatureFlag::MODE_LIVE, 846, '/ויזות-לתאילנד/', 'page', 'publish' );
$visa_hub = Guide_Context::route();
thp_guides_assert( is_array( $visa_hub ) && 'collection' === $visa_hub['kind'], 'published collection did not resolve in Live mode' );
ob_start();
$seo->modified_time_meta();
$hub_modified_time_markup = ob_get_clean();
thp_guides_assert( 1 === substr_count( $hub_modified_time_markup, 'property="article:modified_time"' ), 'collection modified-time tag count mismatch' );
thp_guides_assert( false !== strpos( $hub_modified_time_markup, $visa_hub['modified_on'] . 'T00:00:00+00:00' ), 'collection modified-time value mismatch' );

$entry_graph = Guide_Schema::graph( $entry );
$entry_types = array();
foreach ( $entry_graph['@graph'] as $node ) {
	$entry_types[] = $node['@type'];
}
thp_guides_assert( in_array( 'WebPage', $entry_types, true ) && in_array( 'Article', $entry_types, true ), 'guide schema types mismatch' );
thp_guides_assert( ! in_array( 'FAQPage', $entry_types, true ), 'question schema must not be emitted' );
$hub_graph = Guide_Schema::graph( $visa_hub );
$hub_types = array();
foreach ( $hub_graph['@graph'] as $node ) {
	$hub_types[] = $node['@type'];
}
thp_guides_assert( in_array( 'CollectionPage', $hub_types, true ) && ! in_array( 'Article', $hub_types, true ), 'collection schema types mismatch' );

ob_start();
Guide_View::questions( $entry );
$questions_markup = ob_get_clean();
thp_guides_assert( 5 === substr_count( $questions_markup, '<details' ) && 5 === substr_count( $questions_markup, '<summary>' ), 'visible question markup mismatch' );
ob_start();
Guide_View::sources( $entry );
$sources_markup = ob_get_clean();
thp_guides_assert( false !== strpos( $sources_markup, '<bdi dir="auto">' ) && false !== strpos( $sources_markup, 'dir="ltr"' ), 'mixed-script source markup mismatch' );

$historical = Guide_Repository::route_by_id( 'thailand-entry-april-2022' );
$law_hub    = Guide_Repository::route_by_id( 'thailand-law-and-tax' );
$GLOBALS['thp_guides_test_statuses'][1]   = 'publish';
$GLOBALS['thp_guides_test_statuses'][846] = 'draft';
thp_guides_set_request( Guide_FeatureFlag::MODE_LIVE, 1, '/hello-world/' );
ob_start();
Guide_View::contextual_links( $entry );
$live_contextual_markup = ob_get_clean();
thp_guides_assert( 2 === substr_count( $live_contextual_markup, 'data-thp-contextual-target=' ), 'entry contextual paragraph count mismatch' );
thp_guides_assert( false !== strpos( $live_contextual_markup, 'href="https://example.test/" data-thp-contextual-owner="home"' ), 'entry home contextual link missing' );
thp_guides_assert( false !== strpos( $live_contextual_markup, 'data-thp-contextual-owner="thailand-visas" data-thp-contextual-unlinked' ), 'draft parent lacks unlinked owner marker' );
thp_guides_assert( 1 === substr_count( $live_contextual_markup, '<a ' ) && false === strpos( $live_contextual_markup, 'href="https://example.test/ויזות-לתאילנד/"' ), 'live route linked to an unpublished draft parent' );
thp_guides_assert( false !== strpos( $live_contextual_markup, '>ויזות לתאילנד</span>' ), 'entry parent anchor text drift' );

ob_start();
Guide_View::contextual_links( $historical );
$historical_contextual_markup = ob_get_clean();
thp_guides_assert( 3 === substr_count( $historical_contextual_markup, 'data-thp-contextual-target=' ), 'historical contextual paragraph count mismatch' );
thp_guides_assert( false !== strpos( $historical_contextual_markup, 'href="https://example.test/" data-thp-contextual-owner="home"' ), 'historical home contextual link missing' );
thp_guides_assert( false !== strpos( $historical_contextual_markup, 'href="https://example.test/hello-world/" data-thp-contextual-owner="thailand-entry-requirements"' ), 'historical page lacks current-entry link' );
thp_guides_assert( false !== strpos( $historical_contextual_markup, 'data-thp-contextual-owner="thailand-visas" data-thp-contextual-unlinked' ), 'historical page exposed draft parent' );

thp_guides_set_request( Guide_FeatureFlag::MODE_CANARY, 1, '/hello-world/', 'post', 'publish', false, true, true );
$GLOBALS['thp_guides_test_statuses'][846] = 'draft';
ob_start();
Guide_View::contextual_links( $entry );
$canary_contextual_markup = ob_get_clean();
thp_guides_assert( false !== strpos( $canary_contextual_markup, '<a href="' ) && false !== strpos( $canary_contextual_markup, 'page_id=846' ), 'authorized Canary lacks draft parent link' );
thp_guides_assert( false !== strpos( $canary_contextual_markup, 'preview=true' ) && false !== strpos( $canary_contextual_markup, Guide_FeatureFlag::CANARY_QUERY . '=1' ), 'authorized Canary contextual URL mismatch' );
thp_guides_assert( false !== strpos( $canary_contextual_markup, 'data-thp-contextual-owner="thailand-visas"' ), 'authorized Canary owner marker missing' );

thp_guides_set_request( Guide_FeatureFlag::MODE_LIVE, 1, '/hello-world/' );
$GLOBALS['thp_guides_test_statuses'][846] = 'publish';
ob_start();
Guide_View::contextual_links( $entry );
$published_parent_markup = ob_get_clean();
thp_guides_assert( false !== strpos( $published_parent_markup, 'href="https://example.test/ויזות-לתאילנד/" data-thp-contextual-owner="thailand-visas"' ), 'published parent contextual link missing' );
thp_guides_assert( false === strpos( $published_parent_markup, 'data-thp-contextual-unlinked' ), 'published parent remained unlinked' );

foreach ( array( $visa_hub, $law_hub ) as $hub ) {
	ob_start();
	Guide_View::contextual_links( $hub );
	$hub_contextual_markup = ob_get_clean();
	thp_guides_assert( false !== strpos( $hub_contextual_markup, 'href="https://example.test/" data-thp-contextual-owner="home"' ), 'hub home contextual link missing' );
	thp_guides_assert( false !== strpos( $hub_contextual_markup, '>תאילנד</a>' ), 'hub home anchor text drift' );
}

$unsafe_contextual = $visa_hub;
$unsafe_contextual['contextual_links'] = array(
	array(
		'target_owner_id' => 'home',
		'leading_text'    => '<b>לפני</b>',
		'anchor_text'     => '<script>קישור</script>',
		'trailing_text'   => '<i>אחרי</i>',
	),
);
ob_start();
Guide_View::contextual_links( $unsafe_contextual );
$escaped_contextual_markup = ob_get_clean();
thp_guides_assert( false === strpos( $escaped_contextual_markup, '<script>' ) && false === strpos( $escaped_contextual_markup, '<b>' ) && false === strpos( $escaped_contextual_markup, '<i>' ), 'contextual copy is not escaped' );
thp_guides_assert( false !== strpos( $escaped_contextual_markup, '&lt;script&gt;קישור&lt;/script&gt;' ), 'escaped contextual anchor is missing' );

$GLOBALS['thp_guides_test_styles']      = array();
$GLOBALS['thp_guides_test_scripts']     = array();
$GLOBALS['thp_guides_test_script_data'] = array();
$assets = new Guide_Assets();
thp_guides_set_request( Guide_FeatureFlag::MODE_LIVE, 1, '/hello-world/' );
$assets->enqueue();
thp_guides_assert( isset( $GLOBALS['thp_guides_test_styles'][ Guide_Assets::STYLE_HANDLE ] ), 'guide CSS was not enqueued' );
thp_guides_assert( isset( $GLOBALS['thp_guides_test_scripts'][ Guide_Assets::SCRIPT_HANDLE ] ), 'guide JavaScript was not enqueued' );
thp_guides_assert( 'defer' === $GLOBALS['thp_guides_test_script_data'][ Guide_Assets::SCRIPT_HANDLE ]['strategy'], 'guide script is not deferred' );

$template = file_get_contents( THAILAND_PLATFORM_DIR . 'templates/guides/document.php' );
$header   = file_get_contents( THAILAND_PLATFORM_DIR . 'templates/guides/partials/header.php' );
$css      = file_get_contents( THAILAND_PLATFORM_DIR . 'assets/guides/guides.css' );
$js       = file_get_contents( THAILAND_PLATFORM_DIR . 'assets/guides/guides.js' );
$schema_source = file_get_contents( THAILAND_PLATFORM_DIR . 'src/Guides/Schema.php' );
thp_guides_assert( 1 === preg_match_all( '/<h1\b/i', $template ), 'template must own one H1' );
thp_guides_assert( 1 === preg_match_all( '/<main\b/i', $template ), 'template must own one main landmark' );
thp_guides_assert( false !== strpos( $template, 'dir="ltr"' ), 'hero date lacks an LTR isolate' );
thp_guides_assert( false !== strpos( $header, 'class="thp-guide-close-icon" aria-hidden="true"></span>' ), 'mobile close icon markup missing' );
thp_guides_assert( 0 === preg_match( '/[\x{00D7}\x{00AA}]/u', $header ), 'mobile close control contains an encoding-sensitive glyph' );
thp_guides_assert(
	strpos( $template, 'View::sections( $route )' ) < strpos( $template, 'View::contextual_links( $route )' )
	&& strpos( $template, 'View::contextual_links( $route )' ) < strpos( $template, 'View::questions( $route )' ),
	'contextual paragraphs are not placed after the article sections'
);
thp_guides_assert( false !== strpos( $header, 'aria-modal="true"' ) && false !== strpos( $header, 'data-thp-menu-open hidden' ), 'mobile navigation fail-closed markup mismatch' );
thp_guides_assert( false !== strpos( $css, 'outline: 3px solid #063f31;' ) && false !== strpos( $css, 'box-shadow: 0 0 0 6px #ffffff;' ), 'two-tone focus treatment missing' );
thp_guides_assert( false !== strpos( $css, 'html:not(.thp-guides-enhanced) .thp-guide-desktop-nav' ), 'no-script mobile navigation fallback missing' );
thp_guides_assert( false !== strpos( $css, 'html.thp-guides-enhanced .thp-guide-menu-toggle:not([hidden])' ), 'enhanced mobile toggle gate missing' );
thp_guides_assert( false !== strpos( $css, '.thp-guide-close-icon::before' ) && false !== strpos( $css, 'transform: rotate(-45deg);' ), 'CSS close icon is incomplete' );
thp_guides_assert( false !== strpos( $css, '@media (prefers-reduced-motion: reduce)' ), 'reduced-motion CSS missing' );
thp_guides_assert( false !== strpos( $css, '.thp-guide-contextual-links p' ), 'contextual paragraph styling missing' );
thp_guides_assert( false !== strpos( $js, 'documentElement.classList.add("thp-guides-enhanced")' ), 'enhancement marker missing' );
thp_guides_assert( false !== strpos( $js, 'event.key === "Escape"' ) && false !== strpos( $js, 'event.key !== "Tab"' ), 'keyboard dialog controls missing' );
thp_guides_assert( false !== strpos( $js, 'prefers-reduced-motion: reduce' ), 'script ignores reduced motion' );
thp_guides_assert( false === strpos( $schema_source, "'@type' => 'FAQPage'" ), 'question schema owner found' );

$runtime_files = array_merge(
	glob( THAILAND_PLATFORM_DIR . 'src/Guides/*.php' ),
	glob( THAILAND_PLATFORM_DIR . 'templates/guides/*.php' ),
	glob( THAILAND_PLATFORM_DIR . 'templates/guides/partials/*.php' )
);
foreach ( $runtime_files as $runtime_file ) {
	$source = file_get_contents( $runtime_file );
	thp_guides_assert( false === strpos( $source, 'get_post_field' ), 'runtime reads a stored WordPress body: ' . $runtime_file );
	thp_guides_assert( false === strpos( $source, "apply_filters( 'the_content'" ), 'runtime calls the WordPress body filter: ' . $runtime_file );
}

$public_files = array(
	THAILAND_PLATFORM_DIR . 'data/content/priority-guides.json',
	THAILAND_PLATFORM_DIR . 'templates/guides/document.php',
	THAILAND_PLATFORM_DIR . 'templates/guides/partials/header.php',
	THAILAND_PLATFORM_DIR . 'templates/guides/partials/footer.php',
	THAILAND_PLATFORM_DIR . 'assets/guides/guides.css',
	THAILAND_PLATFORM_DIR . 'assets/guides/guides.js',
);
foreach ( $public_files as $public_file ) {
	$source = file_get_contents( $public_file );
	thp_guides_assert( 0 === preg_match( '/[\x{2013}\x{2014}]/u', $source ), 'forbidden long dash in ' . $public_file );
}

Guide_Settings::purge_caches();
thp_guides_assert( 1 === $GLOBALS['thp_guides_test_cache_flush_calls'], 'guide cache purge did not run' );

define( 'THAILAND_PLATFORM_DISABLE_GUIDES', true );
thp_guides_assert( Guide_FeatureFlag::MODE_OFF === Guide_FeatureFlag::mode(), 'emergency constant did not force Off' );

echo 'PASS: priority guides runtime tests' . PHP_EOL;
