<?php
/**
 * Homepage SEO coexistence controls.
 *
 * @package Thailand_Platform
 */

namespace Thailand_Platform\Homepage;

final class Seo {
	const TITLE       = 'תאילנד: טיולים, מעבר, נדל״ן ועסקים | Thai-Land.co.il';
	const DESCRIPTION = 'תאילנד בעברית: יעדים, מסלולים, מגורים, נדל״ן ועסקים לישראלים בבנגקוק, פוקט, קוסמוי וצ׳יאנג מאי.';

	/**
	 * Register narrowly scoped filters without taking schema ownership.
	 *
	 * @return void
	 */
	public function register() {
		add_filter( 'body_class', array( $this, 'body_classes' ) );
		add_filter( 'wp_robots', array( $this, 'robots' ) );
		add_filter( 'wpseo_robots', array( $this, 'yoast_robots' ) );
		add_filter( 'pre_get_document_title', array( $this, 'title' ) );
		add_filter( 'wpseo_title', array( $this, 'title' ) );
		add_filter( 'wpseo_metadesc', array( $this, 'description' ) );
		add_filter( 'wpseo_opengraph_title', array( $this, 'title' ) );
		add_filter( 'wpseo_opengraph_desc', array( $this, 'description' ) );
		add_filter( 'wpseo_twitter_title', array( $this, 'title' ) );
		add_filter( 'wpseo_twitter_description', array( $this, 'description' ) );
		add_filter( 'wp_headers', array( $this, 'headers' ) );
	}

	/**
	 * Use the approved keyword-led homepage title in core and Yoast output.
	 *
	 * @param string $title Existing title.
	 * @return string
	 */
	public function title( $title ) {
		return Context::should_render() ? self::TITLE : $title;
	}

	/**
	 * @param string[] $classes Existing classes.
	 * @return string[]
	 */
	public function body_classes( $classes ) {
		if ( Context::should_render() ) {
			$classes[] = 'thailand-platform-home';
			$classes[] = Context::is_authorized_canary() ? 'thailand-platform-canary' : 'thailand-platform-live';
		}

		return $classes;
	}

	/**
	 * @param array $robots Existing directives.
	 * @return array
	 */
	public function robots( $robots ) {
		if ( Context::is_authorized_canary() ) {
			$robots['noindex']   = true;
			$robots['nofollow']  = true;
			$robots['noarchive'] = true;
		}

		return $robots;
	}

	/**
	 * @param string $robots Yoast robots string.
	 * @return string
	 */
	public function yoast_robots( $robots ) {
		return Context::is_authorized_canary() ? 'noindex, nofollow, noarchive' : $robots;
	}

	/**
	 * Fill a missing Yoast description without replacing an existing owner value.
	 *
	 * @param string $description Existing description.
	 * @return string
	 */
	public function description( $description ) {
		if ( ! Context::should_render() || '' !== trim( (string) $description ) ) {
			return $description;
		}

		return self::DESCRIPTION;
	}

	/**
	 * Prevent shared caches and search engines from retaining the private canary.
	 *
	 * @param array $headers Response headers.
	 * @return array
	 */
	public function headers( $headers ) {
		if ( Context::is_authorized_canary() ) {
			$headers['X-Robots-Tag'] = 'noindex, nofollow, noarchive';
			$headers['Cache-Control'] = 'private, no-store, max-age=0';
		}

		return $headers;
	}
}
