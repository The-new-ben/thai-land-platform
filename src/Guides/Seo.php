<?php
/**
 * Exact-route SEO metadata for priority guides.
 *
 * @package Thailand_Platform
 */

namespace Thailand_Platform\Guides;

final class Seo {
	/**
	 * @return void
	 */
	public function register() {
		add_filter( 'body_class', array( $this, 'body_classes' ) );
		add_filter( 'wp_robots', array( $this, 'robots' ) );
		add_filter( 'wpseo_robots', array( $this, 'yoast_robots' ) );
		add_filter( 'wpseo_frontend_presenters', array( $this, 'yoast_frontend_presenters' ), 20 );
		add_filter( 'wpseo_exclude_from_sitemap_by_post_ids', array( $this, 'sitemap_excluded_post_ids' ) );
		add_filter( 'wpseo_sitemap_entry', array( $this, 'sitemap_entry' ), 10, 3 );
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
		add_filter( 'wpseo_schema_graph', array( $this, 'yoast_schema_graph' ) );
		add_filter( 'wp_headers', array( $this, 'headers' ) );
		add_action( 'wp_head', array( $this, 'modified_time_meta' ), 18 );
	}

	/**
	 * Remove Yoast's database-backed modified-time tag for managed documents.
	 *
	 * The reviewed guide artifact owns freshness while stored post bodies remain
	 * untouched for immediate Off-mode recovery.
	 *
	 * @param array $presenters Yoast presenter instances.
	 * @return array
	 */
	public function yoast_frontend_presenters( $presenters ) {
		if ( ! is_array( $presenters ) || ! Context::should_render() ) {
			return $presenters;
		}

		return array_values(
			array_filter(
				$presenters,
				static function ( $presenter ) {
					return ! is_object( $presenter ) || ! is_a(
						$presenter,
						'Yoast\\WP\\SEO\\Presenters\\Open_Graph\\Article_Modified_Time_Presenter'
					);
				}
			)
		);
	}

	/**
	 * Emit the one Open Graph modified time owned by the reviewed artifact.
	 *
	 * @return void
	 */
	public function modified_time_meta() {
		$route = Context::route();
		if (
			! is_array( $route )
			|| ! Context::should_render()
			|| ! self::valid_date( $route['modified_on'] ?? '' )
		) {
			return;
		}

		echo '<meta property="article:modified_time" content="' . esc_attr( self::date_time( $route['modified_on'] ) ) . '">' . "\n";
	}

	/**
	 * Keep reviewed noindex documents out of Yoast XML sitemaps in Live mode.
	 *
	 * @param array $post_ids Existing exclusions.
	 * @return array
	 */
	public function sitemap_excluded_post_ids( $post_ids ) {
		$excluded = is_array( $post_ids ) ? $post_ids : array();
		if ( FeatureFlag::MODE_LIVE !== FeatureFlag::mode() || ! Repository::ready() ) {
			return $excluded;
		}

		$registry = Repository::all();
		foreach ( $registry['routes_by_id'] ?? array() as $route ) {
			if (
				'noindex' === ( $route['indexing']['policy'] ?? '' )
				&& self::published_route_ready( $route )
			) {
				$excluded[] = absint( $route['wordpress']['post_id'] ?? 0 );
			}
		}

		return array_values( array_unique( $excluded, SORT_REGULAR ) );
	}

	/**
	 * Align Yoast sitemap freshness with the immutable reviewed artifact.
	 *
	 * @param mixed  $entry  Existing sitemap entry.
	 * @param string $type   Yoast object type.
	 * @param mixed  $object Sitemap object.
	 * @return mixed
	 */
	public function sitemap_entry( $entry, $type, $object ) {
		if (
			FeatureFlag::MODE_LIVE !== FeatureFlag::mode()
			|| 'post' !== $type
			|| ! is_array( $entry )
			|| ! is_object( $object )
			|| ! isset( $object->ID )
			|| ! isset( $object->post_type )
			|| ! Repository::ready()
		) {
			return $entry;
		}

		$route = Repository::route_by_post_id( absint( $object->ID ) );
		if (
			! is_array( $route )
			|| ( $route['wordpress']['post_type'] ?? '' ) !== $object->post_type
			|| ! self::published_route_ready( $route )
		) {
			return $entry;
		}

		if ( 'noindex' === ( $route['indexing']['policy'] ?? '' ) ) {
			return false;
		}
		if ( ! self::valid_date( $route['modified_on'] ?? '' ) ) {
			return $entry;
		}

		$entry['mod'] = self::date_time( $route['modified_on'] );
		return $entry;
	}

	/**
	 * @param string $value Existing title.
	 * @return string
	 */
	public function title( $value ) {
		$route = Context::route();
		return is_array( $route ) && Context::should_render()
			? $route['public']['seo_title'] . ' | Thai-Land.co.il'
			: $value;
	}

	/**
	 * @param string $value Existing description.
	 * @return string
	 */
	public function description( $value ) {
		$route = Context::route();
		return is_array( $route ) && Context::should_render()
			? $route['public']['meta_description']
			: $value;
	}

	/**
	 * @param string $value Existing canonical URL.
	 * @return string
	 */
	public function canonical( $value ) {
		$route = Context::route();
		return is_array( $route ) && Context::should_render()
			? home_url( $route['path'] )
			: $value;
	}

	/**
	 * @param string $value Existing image URL.
	 * @return string
	 */
	public function image( $value ) {
		$route = Context::route();
		return is_array( $route ) && Context::should_render()
			? Assets::hero_url( $route, 1717 )
			: $value;
	}

	/**
	 * @param string $value Existing width.
	 * @return string
	 */
	public function image_width( $value ) {
		return Context::should_render() ? Assets::SOCIAL_IMAGE_WIDTH : $value;
	}

	/**
	 * @param string $value Existing height.
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
		$route = Context::route();
		if ( ! is_array( $route ) || ! Context::should_render() ) {
			return $robots;
		}

		unset( $robots['index'], $robots['noindex'], $robots['follow'], $robots['nofollow'], $robots['noarchive'] );
		if ( Context::is_authorized_canary() ) {
			$robots['noindex']   = true;
			$robots['nofollow']  = true;
			$robots['noarchive'] = true;
			return $robots;
		}

		if ( 'noindex' === ( $route['indexing']['policy'] ?? '' ) ) {
			$robots['noindex'] = true;
			$robots['follow']  = true;
		} else {
			$robots['index']  = true;
			$robots['follow'] = true;
		}
		$robots['max-image-preview'] = 'large';
		return $robots;
	}

	/**
	 * @param string $robots Existing Yoast value.
	 * @return string
	 */
	public function yoast_robots( $robots ) {
		$route = Context::route();
		if ( ! is_array( $route ) || ! Context::should_render() ) {
			return $robots;
		}
		if ( Context::is_authorized_canary() ) {
			return 'noindex, nofollow, noarchive';
		}
		return 'noindex' === ( $route['indexing']['policy'] ?? '' )
			? 'noindex, follow, max-image-preview:large'
			: 'index, follow, max-image-preview:large';
	}

	/**
	 * The module emits one complete graph for its full document.
	 *
	 * @param array $graph Existing Yoast graph.
	 * @return array
	 */
	public function yoast_schema_graph( $graph ) {
		return Context::should_render() ? array() : $graph;
	}

	/**
	 * @param array $headers Existing response headers.
	 * @return array
	 */
	public function headers( $headers ) {
		if ( Context::is_authorized_canary() && Context::should_render() ) {
			$headers['Cache-Control'] = 'private, no-store, no-cache, must-revalidate, max-age=0';
			$headers['X-Robots-Tag']  = 'noindex, nofollow, noarchive';
		}
		return $headers;
	}

	/**
	 * Confirm that a sitemap object is the same publishable route the renderer owns.
	 *
	 * @param array $route Managed route.
	 * @return bool
	 */
	private static function published_route_ready( $route ) {
		$post_id   = absint( $route['wordpress']['post_id'] ?? 0 );
		$post_type = (string) ( $route['wordpress']['post_type'] ?? '' );
		$path      = (string) ( $route['path'] ?? '' );
		if (
			0 === $post_id
			|| '' === $post_type
			|| '' === $path
			|| 'publish' !== get_post_status( $post_id )
			|| $post_type !== get_post_type( $post_id )
			|| ! Renderer::ready( $route )
		) {
			return false;
		}

		$permalink = get_permalink( $post_id );
		if ( ! is_string( $permalink ) || '' === $permalink ) {
			return false;
		}
		$permalink_path = wp_parse_url( $permalink, PHP_URL_PATH );
		return is_string( $permalink_path ) && rawurldecode( $permalink_path ) === $path;
	}

	/**
	 * Validate a compiled ISO calendar date without relying on server timezone.
	 *
	 * @param mixed $value Candidate value.
	 * @return bool
	 */
	private static function valid_date( $value ) {
		if ( ! is_string( $value ) || 1 !== preg_match( '/^\d{4}-\d{2}-\d{2}$/', $value ) ) {
			return false;
		}
		$parts = array_map( 'intval', explode( '-', $value ) );
		return 3 === count( $parts ) && checkdate( $parts[1], $parts[2], $parts[0] );
	}

	/**
	 * Return the deterministic UTC Open Graph and sitemap representation.
	 *
	 * @param string $date ISO date.
	 * @return string
	 */
	private static function date_time( $date ) {
		return $date . 'T00:00:00+00:00';
	}

	/**
	 * @param string[] $classes Existing classes.
	 * @return string[]
	 */
	public function body_classes( $classes ) {
		$route = Context::route();
		if ( is_array( $route ) && Context::should_render() ) {
			$classes[] = 'thp-guides';
			$classes[] = 'collection' === ( $route['kind'] ?? '' )
				? 'thp-guide-collection'
				: 'thp-guide-detail';
			$classes[] = 'thp-guide-route-' . sanitize_html_class( $route['route_id'] );
		}
		return $classes;
	}
}
