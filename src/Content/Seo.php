<?php
/**
 * Exact-route SEO metadata for managed content.
 *
 * @package Thailand_Platform
 */

namespace Thailand_Platform\Content;

final class Seo {
	/**
	 * @return void
	 */
	public function register() {
		add_filter( 'body_class', array( $this, 'body_classes' ) );
		add_filter( 'wp_robots', array( $this, 'robots' ) );
		add_filter( 'wpseo_robots', array( $this, 'yoast_robots' ) );
		add_filter( 'pre_get_document_title', array( $this, 'title' ) );
		add_filter( 'wpseo_title', array( $this, 'title' ) );
		add_filter( 'wpseo_metadesc', array( $this, 'description' ) );
		add_filter( 'wpseo_canonical', array( $this, 'canonical' ) );
		add_filter( 'wpseo_opengraph_title', array( $this, 'title' ) );
		add_filter( 'wpseo_opengraph_desc', array( $this, 'description' ) );
		add_filter( 'wpseo_opengraph_url', array( $this, 'canonical' ) );
		add_filter( 'wpseo_opengraph_image', array( $this, 'image' ) );
		add_filter( 'wpseo_opengraph_image_width', array( $this, 'image_width' ) );
		add_filter( 'wpseo_opengraph_image_height', array( $this, 'image_height' ) );
		add_filter( 'wpseo_twitter_title', array( $this, 'title' ) );
		add_filter( 'wpseo_twitter_description', array( $this, 'description' ) );
		add_filter( 'wpseo_twitter_image', array( $this, 'image' ) );
	}

	/**
	 * @param string $value Existing title.
	 * @return string
	 */
	public function title( $value ) {
		$route = Context::route();
		return is_array( $route ) ? $route['public']['seo_title'] . ' | Thai-Land.co.il' : $value;
	}

	/**
	 * @param string $value Existing description.
	 * @return string
	 */
	public function description( $value ) {
		$route = Context::route();
		return is_array( $route ) ? $route['public']['meta_description'] : $value;
	}

	/**
	 * @param string $value Existing canonical URL.
	 * @return string
	 */
	public function canonical( $value ) {
		$route = Context::route();
		return is_array( $route ) ? home_url( $route['path'] ) : $value;
	}

	/**
	 * @param string $value Existing social image.
	 * @return string
	 */
	public function image( $value ) {
		return Context::should_render() ? Assets::hero_url( '1717' ) : $value;
	}

	/**
	 * @param string $value Existing social image width.
	 * @return string
	 */
	public function image_width( $value ) {
		return Context::should_render() ? Assets::SOCIAL_IMAGE_WIDTH : $value;
	}

	/**
	 * @param string $value Existing social image height.
	 * @return string
	 */
	public function image_height( $value ) {
		return Context::should_render() ? Assets::SOCIAL_IMAGE_HEIGHT : $value;
	}

	/**
	 * @param array $robots Existing directives.
	 * @return array
	 */
	public function robots( $robots ) {
		if ( ! Context::should_render() ) {
			return $robots;
		}

		unset( $robots['noindex'], $robots['nofollow'], $robots['noarchive'] );
		$robots['index']             = true;
		$robots['follow']            = true;
		$robots['max-image-preview'] = 'large';
		return $robots;
	}

	/**
	 * @param string $robots Existing Yoast robots value.
	 * @return string
	 */
	public function yoast_robots( $robots ) {
		return Context::should_render() ? 'index, follow, max-image-preview:large' : $robots;
	}

	/**
	 * @param string[] $classes Existing classes.
	 * @return string[]
	 */
	public function body_classes( $classes ) {
		$route = Context::route();
		if ( is_array( $route ) ) {
			$classes[] = 'thp-real-estate';
			$classes[] = 'hub' === $route['kind'] ? 'thp-real-estate-hub' : 'thp-real-estate-spoke';
		}
		return $classes;
	}
}
