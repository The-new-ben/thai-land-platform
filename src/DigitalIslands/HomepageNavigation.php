<?php
/**
 * Fail-closed homepage discovery for the public Koh Phangan map.
 *
 * @package Thailand_Platform
 */

namespace Thailand_Platform\DigitalIslands;

final class HomepageNavigation {
	const FILTER = 'thailand_platform_homepage_markup';
	const MARKER = '<!-- THP_DIGITAL_ISLANDS_HOME_LINK -->';

	/** @return void */
	public function register() {
		add_filter( self::FILTER, array( $this, 'inject' ), 30 );
		add_action( 'transition_post_status', array( $this, 'purge_after_page_change' ), 10, 3 );
	}

	/** @param mixed $markup Reviewed homepage markup. @return mixed */
	public function inject( $markup ) {
		if ( ! is_string( $markup ) || 1 !== substr_count( $markup, self::MARKER ) ) {
			return $markup;
		}

		$link = '';
		if ( Context::public_api_ready() ) {
			$link = '<a class="card-link" href="' . esc_url( home_url( Repository::canonical_path() ) )
				. '" data-thp-digital-island-home-link="koh-phangan-map">'
				. esc_html( 'מפת קופנגן: עסקים, שירותים ופרויקטים' )
				. ' <span aria-hidden="true">←</span></a>';
		}

		return str_replace( self::MARKER, $link, $markup );
	}

	/**
	 * @param string $new_status New status.
	 * @param string $old_status Old status.
	 * @param mixed  $post       WordPress post.
	 * @return void
	 */
	public function purge_after_page_change( $new_status, $old_status, $post ) {
		unset( $new_status, $old_status );
		if ( ! is_object( $post ) || FeatureFlag::page_id() !== absint( $post->ID ?? 0 ) ) {
			return;
		}
		if ( class_exists( '\Thailand_Platform\Homepage\Settings' ) ) {
			\Thailand_Platform\Homepage\Settings::purge_caches();
		}
	}
}
