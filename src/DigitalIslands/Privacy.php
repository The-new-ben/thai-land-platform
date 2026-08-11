<?php
/**
 * Noindex and no-store policy for the private Digital Islands Canary.
 *
 * @package Thailand_Platform
 */

namespace Thailand_Platform\DigitalIslands;

final class Privacy {
	/** @return void */
	public function register() {
		add_filter( 'wp_robots', array( $this, 'robots' ) );
		add_filter( 'wpseo_robots', array( $this, 'yoast_robots' ) );
		add_filter( 'wp_headers', array( $this, 'headers' ) );
		add_filter( 'body_class', array( $this, 'body_classes' ) );
	}

	/** @param array $robots Existing directives. @return array */
	public function robots( $robots ) {
		if ( Context::is_live_request() && Context::should_render() ) {
			unset( $robots['noindex'], $robots['nofollow'], $robots['noarchive'] );
			$robots['index']             = true;
			$robots['follow']            = true;
			$robots['max-image-preview'] = 'large';
			return $robots;
		}
		if ( ! Context::is_authorized_canary() ) {
			return $robots;
		}
		$robots['noindex']   = true;
		$robots['nofollow']  = true;
		$robots['noarchive'] = true;
		return $robots;
	}

	/** @param string $robots Existing Yoast value. @return string */
	public function yoast_robots( $robots ) {
		if ( Context::is_authorized_canary() ) {
			return 'noindex, nofollow, noarchive';
		}
		return Context::is_live_request() && Context::should_render()
			? 'index, follow, max-image-preview:large'
			: $robots;
	}

	/** @param array $headers Existing headers. @return array */
	public function headers( $headers ) {
		if ( ! Context::is_authorized_canary() ) {
			return $headers;
		}
		$headers['X-Robots-Tag'] = 'noindex, nofollow, noarchive';
		$headers['Cache-Control'] = 'private, no-store, max-age=0';
		$headers['Pragma']        = 'no-cache';
		$headers['Vary']          = 'Cookie, Authorization';
		return $headers;
	}

	/** @param string[] $classes Existing classes. @return string[] */
	public function body_classes( $classes ) {
		if ( Context::is_authorized_canary() ) {
			$classes[] = 'thailand-platform-digital-islands';
			$classes[] = 'thailand-platform-canary';
		} elseif ( Context::is_live_request() && Context::should_render() ) {
			$classes[] = 'thailand-platform-digital-islands';
			$classes[] = 'thailand-platform-live';
		}
		return array_values( array_unique( $classes ) );
	}
}
