<?php
/**
 * Structured data for the public Koh Phangan map owner.
 *
 * @package Thailand_Platform
 */

namespace Thailand_Platform\DigitalIslands;

final class Schema {
	/** @return void */
	public function register() {
		add_action( 'wp_head', array( $this, 'output' ), 20 );
	}

	/** @return void */
	public function output() {
		if ( ! Context::is_live_request() || ! Context::should_render() ) {
			return;
		}
		$json = wp_json_encode(
			self::graph(),
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP
		);
		if ( is_string( $json ) && '' !== $json ) {
			echo '<script type="application/ld+json">' . $json . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/** @return array */
	public static function graph() {
		$home       = home_url( '/' );
		$canonical  = home_url( Repository::canonical_path() );
		$webpage_id = $canonical . '#webpage';
		$citations  = array();
		foreach ( Repository::public_entities() as $entity ) {
			foreach ( is_array( $entity['evidence'] ?? null ) ? $entity['evidence'] : array() as $citation ) {
				$url = is_array( $citation ) ? ( $citation['url'] ?? null ) : null;
				if ( is_string( $url ) && ! in_array( $url, $citations, true ) ) {
					$citations[] = $url;
				}
			}
		}

		return array(
			'@context' => 'https://schema.org',
			'@graph'   => array(
				array(
					'@type' => 'Organization',
					'@id'   => $home . '#organization',
					'name'  => 'Thai-Land.co.il',
					'url'   => $home,
				),
				array(
					'@type'      => 'WebSite',
					'@id'        => $home . '#website',
					'name'       => 'Thai-Land.co.il',
					'url'        => $home,
					'inLanguage' => 'he-IL',
					'publisher'  => array( '@id' => $home . '#organization' ),
				),
				array(
					'@type'           => 'BreadcrumbList',
					'@id'             => $canonical . '#breadcrumb',
					'itemListElement' => array(
						array( '@type' => 'ListItem', 'position' => 1, 'name' => 'ראשי', 'item' => $home ),
						array( '@type' => 'ListItem', 'position' => 2, 'name' => 'מפת תאילנד' ),
						array( '@type' => 'ListItem', 'position' => 3, 'name' => 'מפת קופנגן', 'item' => $canonical ),
					),
				),
				array(
					'@type'       => 'CollectionPage',
					'@id'         => $webpage_id,
					'url'         => $canonical,
					'name'        => Seo::TITLE,
					'description' => Seo::DESCRIPTION,
					'inLanguage'  => 'he-IL',
					'isPartOf'    => array( '@id' => $home . '#website' ),
					'breadcrumb'  => array( '@id' => $canonical . '#breadcrumb' ),
					'dateModified' => Repository::checked_on(),
					'mainEntity'  => array( '@id' => $canonical . '#dataset' ),
				),
				array(
					'@type'              => 'Dataset',
					'@id'                => $canonical . '#dataset',
					'name'               => 'מפת קופנגן - מקומות, שירותים ופרויקטים',
					'description'        => Seo::DESCRIPTION,
					'url'                => $canonical,
					'inLanguage'         => 'he-IL',
					'isAccessibleForFree' => true,
					'dateModified'       => Repository::checked_on(),
					'spatialCoverage'    => array( '@type' => 'Place', 'name' => 'Ko Pha-ngan, Surat Thani, Thailand' ),
					'citation'           => $citations,
					'mainEntityOfPage'   => array( '@id' => $webpage_id ),
				),
			),
		);
	}
}
