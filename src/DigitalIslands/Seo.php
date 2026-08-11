<?php
/**
 * Exact-owner SEO metadata for the public Koh Phangan map.
 *
 * @package Thailand_Platform
 */

namespace Thailand_Platform\DigitalIslands;

final class Seo {
	const TITLE = 'מפת קופנגן: עסקים, שירותים ופרויקטים | Thai-Land.co.il';
	const DESCRIPTION = 'מפת קופנגן אינטראקטיבית עם יישובים, שירותים רשמיים, תשתיות ופרויקטים לתכנון מגורים ועסקים באי.';

	/** @var bool */
	private $fallback_emitted = false;

	/** @return void */
	public function register() {
		add_filter( 'pre_get_document_title', array( $this, 'title' ) );
		add_filter( 'wpseo_title', array( $this, 'title' ) );
		add_filter( 'wpseo_metadesc', array( $this, 'description' ) );
		add_filter( 'wpseo_canonical', array( $this, 'canonical' ) );
		add_filter( 'wpseo_opengraph_title', array( $this, 'title' ) );
		add_filter( 'wpseo_opengraph_desc', array( $this, 'description' ) );
		add_filter( 'wpseo_opengraph_url', array( $this, 'canonical' ) );
		add_filter( 'wpseo_twitter_title', array( $this, 'title' ) );
		add_filter( 'wpseo_twitter_description', array( $this, 'description' ) );
		add_filter( 'wpseo_schema_graph', array( $this, 'yoast_schema_graph' ) );
		add_filter( 'saswp_filter_comparison_logic_checker', array( $this, 'saswp_schema_gate' ) );
		add_filter( 'wpseo_sitemap_entry', array( $this, 'sitemap_entry' ), 10, 3 );
		add_action( 'wp_head', array( $this, 'fallback_meta' ), 1 );
	}

	/** @param string $value Existing title. @return string */
	public function title( $value ) {
		return self::owns_live_document() ? self::TITLE : $value;
	}

	/** @param string $value Existing description. @return string */
	public function description( $value ) {
		return self::owns_live_document() ? self::DESCRIPTION : $value;
	}

	/** @param string $value Existing URL. @return string */
	public function canonical( $value ) {
		return self::owns_live_document() ? home_url( Repository::canonical_path() ) : $value;
	}

	/** @param array $graph Existing Yoast graph. @return array */
	public function yoast_schema_graph( $graph ) {
		return self::owns_live_document() ? array() : $graph;
	}

	/** @param mixed $value Existing SASWP comparison result. @return mixed */
	public function saswp_schema_gate( $value ) {
		return self::owns_live_document() ? 0 : $value;
	}

	/**
	 * WordPress core owns the title and normally the singular canonical. When
	 * Yoast is absent, emit the exact reviewed description and replace core's
	 * later canonical callback so the document still has exactly one of each.
	 *
	 * @return void
	 */
	public function fallback_meta() {
		if (
			$this->fallback_emitted
			|| ! self::owns_live_document()
			|| defined( 'WPSEO_VERSION' )
			|| class_exists( '\WPSEO_Frontend' )
			|| function_exists( 'YoastSEO' )
		) {
			return;
		}

		remove_action( 'wp_head', 'rel_canonical' );
		$this->fallback_emitted = true;
		echo '<meta name="description" content="' . esc_attr( self::DESCRIPTION ) . '">' . "\n";
		echo '<link rel="canonical" href="' . esc_url( home_url( Repository::canonical_path() ) ) . '">' . "\n";
	}

	/**
	 * Keep the one verified child in the sitemap without inventing the planned
	 * Thailand Map parent.
	 *
	 * @param mixed  $entry  Sitemap entry.
	 * @param string $type   Yoast object type.
	 * @param mixed  $object Sitemap object.
	 * @return mixed
	 */
	public function sitemap_entry( $entry, $type, $object ) {
		$page_id = FeatureFlag::page_id();
		if (
			'post' !== $type
			|| ! is_object( $object )
			|| 0 === $page_id
			|| $page_id !== absint( $object->ID ?? 0 )
		) {
			return $entry;
		}

		// Never advertise the bound page while its public representation is hidden.
		if ( ! Context::public_api_ready() ) {
			return false;
		}

		// Respect an earlier sitemap exclusion instead of resurrecting the entry.
		if ( ! is_array( $entry ) ) {
			return $entry;
		}

		$entry['loc'] = home_url( Repository::canonical_path() );
		$entry['mod'] = Repository::checked_on() . 'T00:00:00+00:00';
		return $entry;
	}

	/** @return bool */
	private static function owns_live_document() {
		return Context::is_live_request() && Context::should_render();
	}
}
