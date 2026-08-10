<?php
/**
 * Fail-closed homepage links for the two published Guides hubs.
 *
 * @package Thailand_Platform
 */

namespace Thailand_Platform\Guides;

final class HomepageNavigation {
	const FILTER = 'thailand_platform_homepage_markup';

	const DESKTOP_MARKER = '<!-- THP_GUIDES_DESKTOP_NAV -->';
	const MOBILE_MARKER  = '<!-- THP_GUIDES_MOBILE_NAV -->';
	const FOOTER_MARKER  = '<!-- THP_GUIDES_FOOTER_NAV -->';

	const HUB_ROUTE_IDS = array(
		'thailand-visas',
		'thailand-law-and-tax',
	);

	/**
	 * @return void
	 */
	public function register() {
		add_filter( self::FILTER, array( $this, 'inject' ), 20 );
		add_action( 'transition_post_status', array( $this, 'purge_after_hub_change' ), 10, 3 );
	}

	/**
	 * Add all three homepage navigation surfaces only when both hubs are public.
	 *
	 * @param mixed $markup Reviewed homepage body markup.
	 * @return mixed
	 */
	public function inject( $markup ) {
		if (
			! is_string( $markup )
			|| FeatureFlag::MODE_LIVE !== FeatureFlag::mode()
			|| ! Repository::ready()
			|| ! $this->has_exact_markers( $markup )
		) {
			return $markup;
		}

		$hubs = array();
		foreach ( self::HUB_ROUTE_IDS as $route_id ) {
			$route = Repository::route_by_id( $route_id );
			if ( ! $this->published_hub_is_ready( $route ) ) {
				return $markup;
			}
			$hubs[ $route_id ] = $route;
		}

		$visas = $hubs['thailand-visas'];
		$laws  = $hubs['thailand-law-and-tax'];

		$replacements = array(
			self::DESKTOP_MARKER => $this->desktop_markup( $visas, $laws ),
			self::MOBILE_MARKER  => $this->mobile_markup( $visas, $laws ),
			self::FOOTER_MARKER  => $this->footer_markup( $visas, $laws ),
		);

		return str_replace( array_keys( $replacements ), array_values( $replacements ), $markup );
	}

	/**
	 * Purge the homepage cache whenever either exact hub changes publication state.
	 *
	 * @param string $new_status New post status.
	 * @param string $old_status Previous post status.
	 * @param mixed  $post       WordPress post object.
	 * @return void
	 */
	public function purge_after_hub_change( $new_status, $old_status, $post ) {
		unset( $new_status, $old_status );
		if ( ! is_object( $post ) || 'page' !== ( $post->post_type ?? '' ) ) {
			return;
		}

		$post_id = absint( $post->ID ?? 0 );
		foreach ( self::HUB_ROUTE_IDS as $route_id ) {
			$route = Repository::route_by_id( $route_id );
			if ( is_array( $route ) && $post_id === absint( $route['wordpress']['post_id'] ?? 0 ) ) {
				Settings::purge_caches();
				return;
			}
		}
	}

	/**
	 * @param string $markup Homepage markup.
	 * @return bool
	 */
	private function has_exact_markers( $markup ) {
		foreach ( array( self::DESKTOP_MARKER, self::MOBILE_MARKER, self::FOOTER_MARKER ) as $marker ) {
			if ( 1 !== substr_count( $markup, $marker ) ) {
				return false;
			}
		}
		return true;
	}

	/**
	 * @param mixed $route Compiled Guides route.
	 * @return bool
	 */
	private function published_hub_is_ready( $route ) {
		if (
			! is_array( $route )
			|| 'collection' !== ( $route['kind'] ?? '' )
			|| 'home' !== ( $route['parent_owner_id'] ?? '' )
			|| ( $route['route_id'] ?? '' ) !== ( $route['seo_owner_id'] ?? '' )
			|| ! Renderer::ready( $route )
		) {
			return false;
		}

		$post_id   = absint( $route['wordpress']['post_id'] ?? 0 );
		$post_type = (string) ( $route['wordpress']['post_type'] ?? '' );
		$path      = (string) ( $route['path'] ?? '' );
		$post      = 0 < $post_id ? get_post( $post_id ) : null;
		if (
			0 === $post_id
			|| ! is_object( $post )
			|| $post_id !== absint( $post->ID ?? 0 )
			|| '' !== (string) ( $post->post_password ?? '' )
			|| 'page' !== $post_type
			|| 'publish' !== get_post_status( $post_id )
			|| $post_type !== get_post_type( $post_id )
			|| post_password_required( $post_id )
			|| '' === $path
		) {
			return false;
		}

		$permalink = get_permalink( $post_id );
		return is_string( $permalink ) && $this->permalink_matches_path( $permalink, $path );
	}

	/**
	 * @param string $permalink WordPress permalink.
	 * @param string $path      Required canonical path.
	 * @return bool
	 */
	private function permalink_matches_path( $permalink, $path ) {
		$actual = wp_parse_url( $permalink );
		$home   = wp_parse_url( home_url( '/' ) );
		if ( ! is_array( $actual ) || ! is_array( $home ) ) {
			return false;
		}

		if (
			strtolower( (string) ( $actual['scheme'] ?? '' ) ) !== strtolower( (string) ( $home['scheme'] ?? '' ) )
			|| strtolower( (string) ( $actual['host'] ?? '' ) ) !== strtolower( (string) ( $home['host'] ?? '' ) )
			|| (int) ( $actual['port'] ?? 0 ) !== (int) ( $home['port'] ?? 0 )
			|| isset( $actual['user'] )
			|| isset( $actual['pass'] )
			|| isset( $actual['query'] )
			|| isset( $actual['fragment'] )
		) {
			return false;
		}

		return rawurldecode( (string) ( $actual['path'] ?? '' ) ) === $path;
	}

	/**
	 * @param array $visas Visa hub route.
	 * @param array $laws  Law hub route.
	 * @return string
	 */
	private function desktop_markup( $visas, $laws ) {
		return '<div class="nav-item" data-thp-guides-home-nav="desktop">'
			. '<button class="nav-trigger" type="button" aria-expanded="false" aria-controls="menu-entry-laws">כניסה וחוקים</button>'
			. '<div class="mega-panel mega-panel--guides" id="menu-entry-laws" hidden>'
			. '<div class="mega-panel__intro"><span class="eyebrow">כניסה, שהייה וכללים</span><h2>מתכננים את השהייה לפי המצב שלכם</h2><p>בדקו את מסלול הכניסה, האשרה והכללים שחלים על טיול, מעבר, עבודה או עסק.</p></div>'
			. '<div><h3>כניסה ושהייה</h3>' . $this->hub_link( $visas ) . '<p>פטור מאשרה, TDAC, ויזות ותושבות.</p></div>'
			. '<div><h3>חיים ופעילות</h3>' . $this->hub_link( $laws ) . '<p>מס, עבודה, עסקים וכללים לתיירים.</p></div>'
			. '</div></div>';
	}

	/**
	 * @param array $visas Visa hub route.
	 * @param array $laws  Law hub route.
	 * @return string
	 */
	private function mobile_markup( $visas, $laws ) {
		return '<details data-thp-guides-home-nav="mobile"><summary>כניסה וחוקים</summary><div>'
			. $this->hub_link( $visas )
			. $this->hub_link( $laws )
			. '</div></details>';
	}

	/**
	 * @param array $visas Visa hub route.
	 * @param array $laws  Law hub route.
	 * @return string
	 */
	private function footer_markup( $visas, $laws ) {
		return '<nav class="site-shell footer-guides" aria-label="כניסה, ויזות וחוקים בתאילנד" data-thp-guides-home-nav="footer">'
			. '<span>כניסה וחוקים</span>'
			. $this->hub_link( $visas )
			. $this->hub_link( $laws )
			. '</nav>';
	}

	/**
	 * @param array $route Compiled hub route.
	 * @return string
	 */
	private function hub_link( $route ) {
		$route_id = (string) $route['route_id'];
		$anchor   = (string) $route['ownership']['primary_keyword'];
		$url      = home_url( (string) $route['path'] );
		return '<a href="' . esc_url( $url ) . '" data-thp-guides-home-link="' . esc_attr( $route_id ) . '">' . esc_html( $anchor ) . '</a>';
	}
}
