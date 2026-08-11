<?php
/**
 * Focused security and exact-identity tests for Digital Islands settings.
 */

$root = dirname( __DIR__ );
define( 'THAILAND_PLATFORM_DIR', $root . DIRECTORY_SEPARATOR );

$hooks = array();
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
	unset( $name );
	return $default;
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

require_once $root . '/src/DigitalIslands/StrictJson.php';
require_once $root . '/src/DigitalIslands/ArtifactVerifier.php';
require_once $root . '/src/DigitalIslands/Repository.php';
require_once $root . '/src/DigitalIslands/Context.php';
require_once $root . '/src/DigitalIslands/FeatureFlag.php';
require_once $root . '/src/DigitalIslands/Settings.php';

use Thailand_Platform\DigitalIslands\Context;
use Thailand_Platform\DigitalIslands\FeatureFlag;
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

fwrite( STDOUT, sprintf( 'PASS: digital islands administrator settings security gates (%d assertions).', $assertions ) . PHP_EOL );
