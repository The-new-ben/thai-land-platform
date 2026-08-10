<?php
/**
 * Structured data for priority guide documents.
 *
 * @package Thailand_Platform
 */

namespace Thailand_Platform\Guides;

final class Schema {
	/**
	 * @return void
	 */
	public function register() {
		add_action( 'wp_head', array( $this, 'output' ), 20 );
	}

	/**
	 * @return void
	 */
	public function output() {
		$route = Context::route();
		if ( ! is_array( $route ) || ! Context::should_render() ) {
			return;
		}
		$json = wp_json_encode(
			self::graph( $route ),
			JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP
		);
		if ( is_string( $json ) && '' !== $json ) {
			echo '<script type="application/ld+json">' . $json . '</script>' . "\n"; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
		}
	}

	/**
	 * @param array $route Managed route.
	 * @return array
	 */
	public static function graph( $route ) {
		$registry       = Repository::all();
		$site           = $registry['site'] ?? array();
		$home           = home_url( '/' );
		$canonical      = home_url( $route['path'] );
		$organization   = $home . '#organization';
		$website        = $home . '#website';
		$webpage        = $canonical . '#webpage';
		$breadcrumb_id  = $canonical . '#breadcrumb';
		$image           = Assets::hero_url( $route, 1717 );
		$breadcrumb_list = array();

		foreach ( $route['breadcrumbs'] as $index => $crumb ) {
			$item = array(
				'@type'    => 'ListItem',
				'position' => $index + 1,
				'name'     => $crumb['label'],
			);
			if ( '/' === $crumb['path'] || ! empty( $crumb['current'] ) ) {
				$item['item'] = home_url( $crumb['path'] );
			} else {
				$target = Repository::route_by_path( $crumb['path'] );
				if ( is_array( $target ) && 'publish' === get_post_status( absint( $target['wordpress']['post_id'] ) ) ) {
					$item['item'] = home_url( $crumb['path'] );
				}
			}
			$breadcrumb_list[] = $item;
		}

		$citations = array();
		foreach ( $route['source_ids'] as $source_id ) {
			$source = Repository::source( $source_id );
			if ( is_array( $source ) ) {
				$citations[] = $source['url'];
			}
		}

		$graph = array(
			array(
				'@type' => 'Organization',
				'@id'   => $organization,
				'name'  => $site['name'] ?? 'Thai-Land.co.il',
				'url'   => $home,
			),
			array(
				'@type'      => 'WebSite',
				'@id'        => $website,
				'url'        => $home,
				'name'       => $site['name'] ?? 'Thai-Land.co.il',
				'inLanguage' => $site['locale'] ?? 'he-IL',
				'publisher'  => array( '@id' => $organization ),
			),
			array(
				'@type'           => 'BreadcrumbList',
				'@id'             => $breadcrumb_id,
				'itemListElement' => $breadcrumb_list,
			),
			array(
				'@type'         => 'collection' === ( $route['kind'] ?? '' ) ? 'CollectionPage' : 'WebPage',
				'@id'           => $webpage,
				'url'           => $canonical,
				'name'          => $route['public']['h1'],
				'description'   => $route['public']['meta_description'],
				'inLanguage'    => $site['locale'] ?? 'he-IL',
				'isPartOf'      => array( '@id' => $website ),
				'breadcrumb'    => array( '@id' => $breadcrumb_id ),
				'primaryImageOfPage' => array(
					'@type'  => 'ImageObject',
					'url'    => $image,
					'width'  => 1717,
					'height' => 916,
				),
				'datePublished' => $route['published_on'],
				'dateModified'  => $route['modified_on'],
				'citation'      => $citations,
			),
		);

		if ( 'guide' === ( $route['kind'] ?? '' ) ) {
			$graph[] = array(
				'@type'            => 'Article',
				'@id'              => $canonical . '#article',
				'headline'         => $route['public']['h1'],
				'description'      => $route['public']['meta_description'],
				'image'            => array( $image ),
				'datePublished'    => $route['published_on'],
				'dateModified'     => $route['modified_on'],
				'inLanguage'       => $site['locale'] ?? 'he-IL',
				'mainEntityOfPage' => array( '@id' => $webpage ),
				'author'           => array( '@id' => $organization ),
				'publisher'        => array( '@id' => $organization ),
				'citation'         => $citations,
			);
		}

		return array(
			'@context' => 'https://schema.org',
			'@graph'   => $graph,
		);
	}
}
