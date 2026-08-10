<?php
/**
 * Thailand Platform uninstall boundary.
 *
 * Remove only the bounded presentation preferences owned by this plugin.
 *
 * @package Thailand_Platform
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

delete_option( 'thailand_platform_homepage_mode' );
delete_option( 'thailand_platform_real_estate_mode' );
delete_option( 'thailand_platform_guides_mode' );

if ( function_exists( 'wp_cache_flush' ) ) {
	wp_cache_flush();
}

$cache_class = '\\Upress\\EzCache\\Cache';
if ( class_exists( $cache_class ) && method_exists( $cache_class, 'instance' ) ) {
	try {
		$cache = call_user_func( array( $cache_class, 'instance' ) );
		if ( is_object( $cache ) && method_exists( $cache, 'clear_cache' ) ) {
			$cache->clear_cache( false );
		}
	} catch ( Throwable $exception ) {
		unset( $exception );
	}
}
