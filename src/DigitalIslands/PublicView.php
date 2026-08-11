<?php
/**
 * Public-safe projection of the private-review Digital Islands registry.
 *
 * @package Thailand_Platform
 */

namespace Thailand_Platform\DigitalIslands;

use RuntimeException;

final class PublicView {
	const REPRESENTATION_CANARY = 'private_canary';
	const REPRESENTATION_PUBLIC = 'public_live';
	const ISLAND_CONTRACT   = 'thailand-digital-island-island-v1';
	const LAYERS_CONTRACT   = 'thailand-digital-island-layers-v1';
	const ENTITIES_CONTRACT = 'thailand-digital-island-entities-v1';
	const SEARCH_CONTRACT   = 'thailand-digital-island-search-v1';

	const SAFE_LAYER_IDS = array(
		'layer:business-services',
		'layer:education',
		'layer:government',
		'layer:health',
		'layer:landmarks',
		'layer:parcel-lookup',
		'layer:property-projects',
		'layer:roads',
		'layer:settlements',
		'layer:telecom',
		'layer:transport',
		'layer:utilities',
	);

	const SAFE_ENTITY_TYPES = array(
		'banking',
		'education',
		'government',
		'health',
		'landmark',
		'postal',
		'professional_service',
		'property_project',
		'road',
		'settlement',
		'telecom',
		'transport',
		'utility',
	);

	/**
	 * @param string|null $as_of Optional UTC date for deterministic tests.
	 * @return array
	 */
	public static function island_payload( $as_of = null, $representation = self::REPRESENTATION_CANARY ) {
		$as_of    = self::reference_date( $as_of );
		$representation = self::representation( $representation );
		$registry = Repository::all();
		$island   = $registry['island'];

		return array(
			'contract_id'         => self::ISLAND_CONTRACT,
			'schema_version'      => 1,
			'dataset_version'     => $registry['dataset_version'],
			'dataset_checked_on'  => $registry['checked_on'],
			'as_of'               => $as_of,
			'representation_state' => $representation,
			'indexing_policy'     => self::REPRESENTATION_PUBLIC === $representation ? 'index_follow' : 'noindex_follow',
			'attribution'         => array(
				'text' => 'Protomaps © OpenStreetMap contributors',
				'url'  => 'https://www.openstreetmap.org/copyright',
				'use'  => 'self_hosted_vector_basemap_no_community_tiles',
			),
			'attributions'        => self::map_attributions(),
			'imagery_sources'     => self::imagery_sources( $registry['sources_by_id'] ?? array() ),
			'island'              => array(
				'geo_id'              => $island['geo_id'],
				'names'               => self::localized_values( $island['names'] ),
				'aliases'             => self::localized_lists( $island['aliases'] ),
				'province_geo_id'     => $island['province_geo_id'],
				'district_geo_id'     => $island['district_geo_id'],
				'subdistrict_geo_ids' => array_values( $island['subdistrict_geo_ids'] ),
				'center'              => self::coordinate_pair( $island['center'] ),
				'bounds'              => self::bounds( $island['bounds'] ),
			),
			'renderers'           => self::renderers( $registry['renderer_contract'] ?? array() ),
			'camera_presets'      => self::camera_presets( $registry['camera_presets'] ),
			'decision_policy'     => array(
				'parcel_data_mode'              => 'external_official_lookup_only',
				'automatic_buildability_verdict' => false,
				'automatic_title_verdict'        => false,
				'required_dimensions'            => self::decision_dimensions( $registry['land_decision_policy']['required_dimensions'] ?? array() ),
			),
			'official_tools'      => self::official_tools( $registry['official_tools'] ?? array() ),
		);
	}

	/**
	 * @param string|null $as_of Optional UTC date.
	 * @return array
	 */
	public static function layers_payload( $as_of = null, $representation = self::REPRESENTATION_CANARY ) {
		$as_of      = self::reference_date( $as_of );
		$representation = self::representation( $representation );
		$registry   = Repository::all();
		$entities   = self::entities( $as_of, $representation );
		$referenced = array();
		foreach ( $entities as $entity ) {
			foreach ( $entity['layer_ids'] as $layer_id ) {
				$referenced[ $layer_id ] = true;
			}
		}

		$layers = array();
		foreach ( self::SAFE_LAYER_IDS as $layer_id ) {
			if ( ! isset( $referenced[ $layer_id ], $registry['layers_by_id'][ $layer_id ] ) ) {
				continue;
			}
			$layer = $registry['layers_by_id'][ $layer_id ];
			$layers[] = array(
				'layer_id'          => $layer_id,
				'label_he'          => self::bounded_string( $layer['label_he'] ?? '', 160 ),
				'category'          => self::safe_token( $layer['category'] ?? '' ),
				'priority'          => is_int( $layer['priority'] ?? null ) ? $layer['priority'] : 0,
				'coverage_state'    => self::safe_token( $layer['coverage_state'] ?? '' ),
				'public_state'      => self::safe_token( $layer['public_state'] ?? '' ),
				'geometry_contract' => self::safe_token( $layer['geometry_contract'] ?? '' ),
				'delivery'          => self::safe_token( $layer['delivery'] ?? '' ),
				'next_review_on'    => self::safe_date_or_null( $layer['next_review_on'] ?? null ),
			);
		}

		return array(
			'contract_id'         => self::LAYERS_CONTRACT,
			'schema_version'      => 1,
			'dataset_version'     => $registry['dataset_version'],
			'dataset_checked_on'  => $registry['checked_on'],
			'as_of'               => $as_of,
			'representation_state' => $representation,
			'layer_count'         => count( $layers ),
			'layers'              => $layers,
		);
	}

	/**
	 * @param string|null $as_of Optional UTC date.
	 * @return array
	 */
	public static function entities_payload( $as_of = null, $representation = self::REPRESENTATION_CANARY ) {
		$as_of    = self::reference_date( $as_of );
		$representation = self::representation( $representation );
		$entities = self::entities( $as_of, $representation );

		return array(
			'contract_id'         => self::ENTITIES_CONTRACT,
			'schema_version'      => 1,
			'dataset_version'     => Repository::dataset_version(),
			'dataset_checked_on'  => Repository::checked_on(),
			'as_of'               => $as_of,
			'representation_state' => $representation,
			'entity_count'        => count( $entities ),
			'entities'            => $entities,
		);
	}

	/**
	 * @param string      $entity_id Stable entity ID.
	 * @param string|null $as_of     Optional UTC date.
	 * @return array|null
	 */
	public static function entity( $entity_id, $as_of = null, $representation = self::REPRESENTATION_CANARY ) {
		foreach ( self::entities( $as_of, $representation ) as $entity ) {
			if ( $entity_id === $entity['entity_id'] ) {
				return $entity;
			}
		}
		return null;
	}

	/**
	 * @param string      $term  Search term already sanitized at the REST boundary.
	 * @param string|null $as_of Optional UTC date.
	 * @return array
	 */
	public static function search_payload( $term, $as_of = null, $representation = self::REPRESENTATION_CANARY ) {
		$as_of      = self::reference_date( $as_of );
		$representation = self::representation( $representation );
		$term       = trim( (string) $term );
		$needle     = self::lower( $term );
		$matches    = array();

		foreach ( self::entities( $as_of, $representation ) as $entity ) {
			$haystack = self::lower(
				implode(
					' ',
					array_merge(
						array_values( array_filter( $entity['names'], 'is_string' ) ),
						self::flatten_localized_lists( $entity['aliases'] ),
						array( $entity['location_label_he'] )
					)
				)
			);
			if ( '' !== $needle && false !== strpos( $haystack, $needle ) ) {
				$matches[] = $entity;
				if ( 20 === count( $matches ) ) {
					break;
				}
			}
		}

		return array(
			'contract_id'         => self::SEARCH_CONTRACT,
			'schema_version'      => 1,
			'dataset_version'     => Repository::dataset_version(),
			'dataset_checked_on'  => Repository::checked_on(),
			'as_of'               => $as_of,
			'representation_state' => $representation,
			'term'                => $term,
			'result_count'        => count( $matches ),
			'results'             => $matches,
		);
	}

	/**
	 * Build a second allowlist over the generated Canary projection.
	 *
	 * @param string|null $as_of Optional UTC date.
	 * @return array
	 */
	public static function entities( $as_of = null, $representation = self::REPRESENTATION_CANARY ) {
		$as_of   = self::reference_date( $as_of );
		$representation = self::representation( $representation );
		$entities = array();
		$candidates = self::REPRESENTATION_PUBLIC === $representation
			? Repository::public_entities()
			: Repository::canary_entities();
		foreach ( $candidates as $candidate ) {
			$entity = self::sanitize_entity( $candidate, $as_of );
			if ( null !== $entity ) {
				$entities[] = $entity;
			}
		}
		return $entities;
	}

	/** @param mixed $representation Requested projection. @return string */
	private static function representation( $representation ) {
		if ( self::REPRESENTATION_CANARY === $representation ) {
			return self::REPRESENTATION_CANARY;
		}
		if ( self::REPRESENTATION_PUBLIC === $representation && Repository::public_ready() ) {
			return self::REPRESENTATION_PUBLIC;
		}
		throw new RuntimeException( 'The requested Digital Islands representation is unavailable.' );
	}

	/**
	 * @param mixed  $candidate Generated Canary entity.
	 * @param string $as_of     Reference date.
	 * @return array|null
	 */
	private static function sanitize_entity( $candidate, $as_of ) {
		if ( ! is_array( $candidate ) ) {
			return null;
		}

		$entity_id      = $candidate['entity_id'] ?? null;
		$entity_type    = $candidate['entity_type'] ?? null;
		$next_review_on = self::safe_date_or_null( $candidate['next_review_on'] ?? null );
		if (
			! is_string( $entity_id )
			|| 1 !== preg_match( '/\A[a-z_]+:th:[a-z0-9:._-]+\z/D', $entity_id )
			|| ! in_array( $entity_type, self::SAFE_ENTITY_TYPES, true )
			|| 'map_only' !== ( $candidate['public_state'] ?? null )
			|| 'map_only' !== ( $candidate['indexing_policy'] ?? null )
		) {
			return null;
		}

		$layer_ids = array_values(
			array_filter(
				array_map( 'strval', is_array( $candidate['layer_ids'] ?? null ) ? $candidate['layer_ids'] : array() ),
				static function ( $layer_id ) {
					return in_array( $layer_id, self::SAFE_LAYER_IDS, true );
				}
			)
		);
		if ( array() === $layer_ids ) {
			return null;
		}

		$facts = array();
		foreach ( is_array( $candidate['facts'] ?? null ) ? $candidate['facts'] : array() as $fact ) {
			$fact_review = self::safe_date_or_null( is_array( $fact ) ? ( $fact['next_review_on'] ?? null ) : null );
			if ( ! is_array( $fact ) || ( null !== $fact_review && $fact_review < $as_of ) ) {
				continue;
			}
			$fact_id = self::safe_token( $fact['fact_id'] ?? '' );
			$label   = self::bounded_string( $fact['label_he'] ?? '', 160 );
			$value   = self::bounded_string( $fact['value_he'] ?? '', 1000 );
			if ( '' === $fact_id || '' === $label || '' === $value ) {
				continue;
			}
			$facts[] = array(
				'fact_id'        => $fact_id,
				'label_he'       => $label,
				'value_he'       => $value,
				'checked_on'     => self::safe_date_or_null( $fact['checked_on'] ?? null ),
				'next_review_on' => $fact_review,
				'evidence'       => self::evidence( $fact['evidence'] ?? array() ),
			);
		}

		return array(
			'entity_id'         => $entity_id,
			'entity_type'       => $entity_type,
			'names'             => self::localized_values( $candidate['names'] ?? array() ),
			'aliases'           => self::localized_lists( $candidate['aliases'] ?? array() ),
			'geo_ids'           => self::safe_ids( $candidate['geo_ids'] ?? array(), '/\Ageo:th:[a-z0-9:.-]+\z/D' ),
			'location_label_he' => self::bounded_string( $candidate['location_label_he'] ?? '', 240 ),
			'coordinates'       => self::coordinates( $candidate['coordinates'] ?? null ),
			'geometry'          => self::geometry( $candidate['geometry'] ?? null ),
			'layer_ids'         => $layer_ids,
			'public_state'      => 'map_only',
			'indexing_policy'   => 'map_only',
			'facts'             => $facts,
			'evidence'          => self::evidence( $candidate['evidence'] ?? array() ),
			'checked_on'        => self::safe_date_or_null( $candidate['checked_on'] ?? null ),
			'next_review_on'    => $next_review_on,
			'freshness_state'   => null !== $next_review_on && $next_review_on < $as_of ? 'review_due' : 'current',
		);
	}

	/** @param mixed $value Coordinates. @return array|null */
	private static function coordinates( $value ) {
		if ( null === $value ) {
			return null;
		}
		if ( ! is_array( $value ) ) {
			return null;
		}
		$latitude  = $value['latitude'] ?? null;
		$longitude = $value['longitude'] ?? null;
		$accuracy  = $value['accuracy_class'] ?? null;
		if (
			! is_numeric( $latitude ) || ! is_numeric( $longitude )
			|| ! is_finite( (float) $latitude ) || ! is_finite( (float) $longitude )
			|| -90 > (float) $latitude || 90 < (float) $latitude
			|| -180 > (float) $longitude || 180 < (float) $longitude
			|| ! in_array( $accuracy, array( 'area_centroid', 'community_mapped_feature', 'first_party_pin', 'official_point' ), true )
		) {
			return null;
		}
		$accuracy_m = $value['accuracy_m'] ?? null;
		return array(
			'latitude'       => (float) $latitude,
			'longitude'      => (float) $longitude,
			'accuracy_class' => $accuracy,
			'accuracy_m'     => is_int( $accuracy_m ) && 0 <= $accuracy_m ? $accuracy_m : null,
			'basis_label'    => self::bounded_string( $value['basis_label'] ?? '', 320 ),
			'evidence'       => self::evidence( $value['evidence'] ?? array() ),
		);
	}

	/** @param mixed $value Reviewed public citations. @return array */
	private static function evidence( $value ) {
		$result = array();
		$seen   = array();
		foreach ( is_array( $value ) ? $value : array() as $citation ) {
			if ( ! is_array( $citation ) ) {
				continue;
			}
			$source_id = self::bounded_string( $citation['source_id'] ?? '', 160 );
			$publisher = self::bounded_string( $citation['publisher'] ?? '', 240 );
			$title     = self::bounded_string( $citation['title'] ?? '', 320 );
			$url       = is_string( $citation['url'] ?? null ) ? $citation['url'] : '';
			$parts     = '' !== $url ? wp_parse_url( $url ) : false;
			$checked   = self::safe_date_or_null( $citation['checked_on'] ?? null );
			if (
				'' === $source_id
				|| isset( $seen[ $source_id ] )
				|| '' === $publisher
				|| '' === $title
				|| ! is_array( $parts )
				|| 'https' !== ( $parts['scheme'] ?? null )
				|| '' === ( $parts['host'] ?? '' )
				|| isset( $parts['user'] )
				|| isset( $parts['pass'] )
				|| null === $checked
			) {
				continue;
			}
			$result[] = array(
				'publisher'  => $publisher,
				'title'      => $title,
				'url'        => $url,
				'checked_on' => $checked,
			);
			$seen[ $source_id ] = true;
		}
		return $result;
	}

	/** @param mixed $value Geometry. @return array|null */
	private static function geometry( $value ) {
		if ( null === $value ) {
			return null;
		}
		if ( ! is_array( $value ) || 'point' !== ( $value['kind'] ?? null ) || 'orientation_only' !== ( $value['state'] ?? null ) ) {
			return null;
		}
		return array( 'kind' => 'point', 'state' => 'orientation_only' );
	}

	/** @param mixed $value Names. @return array */
	private static function localized_values( $value ) {
		$value = is_array( $value ) ? $value : array();
		return array(
			'he' => self::nullable_bounded_string( $value['he'] ?? null, 240 ),
			'en' => self::nullable_bounded_string( $value['en'] ?? null, 240 ),
			'th' => self::nullable_bounded_string( $value['th'] ?? null, 240 ),
		);
	}

	/** @param mixed $value Aliases. @return array */
	private static function localized_lists( $value ) {
		$value  = is_array( $value ) ? $value : array();
		$result = array();
		foreach ( array( 'he', 'en', 'th' ) as $locale ) {
			$result[ $locale ] = array();
			foreach ( is_array( $value[ $locale ] ?? null ) ? $value[ $locale ] : array() as $alias ) {
				$alias = self::bounded_string( $alias, 240 );
				if ( '' !== $alias && ! in_array( $alias, $result[ $locale ], true ) ) {
					$result[ $locale ][] = $alias;
				}
			}
		}
		return $result;
	}

	/** @param array $localized Localized lists. @return string[] */
	private static function flatten_localized_lists( $localized ) {
		$result = array();
		foreach ( $localized as $list ) {
			foreach ( $list as $value ) {
				$result[] = $value;
			}
		}
		return $result;
	}

	/** @param mixed $values IDs. @param string $pattern Pattern. @return string[] */
	private static function safe_ids( $values, $pattern ) {
		$result = array();
		foreach ( is_array( $values ) ? $values : array() as $value ) {
			if ( is_string( $value ) && 1 === preg_match( $pattern, $value ) && ! in_array( $value, $result, true ) ) {
				$result[] = $value;
			}
		}
		return $result;
	}

	/** @param mixed $contracts Reviewed renderer contracts. @return array */
	private static function renderers( $contracts ) {
		$expected = array(
			'immersive_3d' => array(
				'role'              => 'primary_capable_device',
				'capabilities'      => array( 'camera_presets', 'entity_focus', 'globe', 'hillshade', 'terrain', 'building_extrusion', 'satellite_imagery' ),
				'fallback_triggers' => array( 'webgl_unavailable', 'data_saver', 'user_choice' ),
			),
			'practical_2d' => array(
				'role'              => 'fallback_and_operational',
				'capabilities'      => array( 'camera_presets', 'entity_focus', 'filters', 'keyboard_list', 'vector_basemap' ),
				'fallback_triggers' => array(),
			),
		);
		$indexed = array();
		foreach ( is_array( $contracts ) ? $contracts : array() as $contract ) {
			if ( ! is_array( $contract ) ) {
				continue;
			}
			$renderer_id = self::safe_token( $contract['renderer_id'] ?? '' );
			if ( ! isset( $expected[ $renderer_id ] ) || isset( $indexed[ $renderer_id ] ) ) {
				continue;
			}
			$indexed[ $renderer_id ] = $contract;
		}

		$result = array();
		foreach ( $expected as $renderer_id => $required ) {
			$contract = $indexed[ $renderer_id ] ?? null;
			if (
				! is_array( $contract )
				|| $required['role'] !== ( $contract['role'] ?? null )
				|| 'MapLibre GL JS' !== ( $contract['library'] ?? null )
				|| '5.18.0' !== ( $contract['library_version'] ?? null )
				|| 'self_hosted_pinned' !== ( $contract['delivery'] ?? null )
				|| $required['capabilities'] !== ( $contract['capabilities'] ?? null )
				|| $required['fallback_triggers'] !== ( $contract['fallback_triggers'] ?? null )
			) {
				continue;
			}
			$result[] = array(
				'renderer_id'       => $renderer_id,
				'adapter'           => 'MapLibre',
				'library_version'   => '5.18.0',
				'delivery'          => 'self_hosted_pinned',
				'state'             => 'available',
				'capabilities'      => $required['capabilities'],
				'fallback_triggers' => $required['fallback_triggers'],
			);
		}
		$result[] = array( 'renderer_id' => 'accessible_list', 'adapter' => 'HTML', 'state' => 'available' );
		return $result;
	}

	/** @return array */
	private static function map_attributions() {
		return array(
			array(
				'attribution_id' => 'basemap',
				'text'           => 'Protomaps © OpenStreetMap contributors',
				'url'            => 'https://www.openstreetmap.org/copyright',
				'license'        => 'ODbL-1.0',
			),
			array(
				'attribution_id' => 'terrain',
				'text'           => 'Mapzen Terrain Tiles; SRTM and GMTED2010 data courtesy of the U.S. Geological Survey; ETOPO1 courtesy of NOAA/NCEI. Not for navigation.',
				'url'            => 'https://github.com/tilezen/joerd/blob/master/docs/attribution.md',
				'license'        => 'source-specific-public-data-terms',
			),
			array(
				'attribution_id' => 'landcover',
				'text'           => 'ESA WorldCover 2021 land-cover data, licensed under CC BY 4.0.',
				'url'            => 'https://esa-worldcover.org/en/data-access',
				'license'        => 'CC-BY-4.0',
			),
			array(
				'attribution_id' => 'satellite',
				'text'           => 'Contains modified Copernicus Sentinel data 2026. Image observed 26.03.2026.',
				'url'            => 'https://sentinels.copernicus.eu/documents/247904/690755/Sentinel_Data_Legal_Notice',
				'license'        => 'Copernicus-Sentinel-Data-Legal-Notice',
			),
		);
	}

	/** @param mixed $sources Reviewed source catalog. @return array */
	private static function imagery_sources( $sources ) {
		$source_id = 'source:copernicus.sentinel2.s2b_47ppl_20260326_0_l2a';
		$expected  = array(
			'item_id'                   => 'S2B_47PPL_20260326_0_L2A',
			'observed_at'               => '2026-03-26T03:55:36.171000Z',
			'tile_cloud_cover_percent'  => 14.307985,
			'tile_cloud_metadata_scope' => 'source_tile_not_cropped_island',
			'registry_url'              => 'https://registry.opendata.aws/sentinel-2-l2a-cogs/',
			'legal_notice_url'          => 'https://sentinels.copernicus.eu/documents/247904/690755/Sentinel_Data_Legal_Notice',
			'processed_bounds'          => array(
				'west'  => 99.92,
				'south' => 9.63,
				'east'  => 100.12,
				'north' => 9.84,
			),
			'processing'                => array( 'cropped_to_bounds', 'reprojected_to_epsg_3857', 'compressed_to_webp' ),
			'processed_projection'      => 'EPSG:3857',
			'processed_format'          => 'webp',
			'usage_scope'               => 'orientation_only',
			'limitations'               => array( 'not_current_evidence', 'not_parcel_evidence', 'not_title_evidence', 'not_buildability_evidence' ),
			'attribution'               => 'Contains modified Copernicus Sentinel data 2026',
		);
		foreach ( is_array( $sources ) ? $sources : array() as $source ) {
			if ( ! is_array( $source ) || $source_id !== ( $source['source_id'] ?? null ) ) {
				continue;
			}
			if (
				$expected !== ( $source['imagery'] ?? null )
				|| 'https://sentinel-cogs.s3.us-west-2.amazonaws.com/sentinel-s2-l2a-cogs/47/P/PL/2026/3/S2B_47PPL_20260326_0_L2A/TCI.tif' !== ( $source['url'] ?? null )
				|| 'licensed_registry' !== ( $source['authority_tier'] ?? null )
				|| 'official_dataset' !== ( $source['source_type'] ?? null )
				|| 'current' !== ( $source['access_state'] ?? null )
				|| 'attribute_and_geometry' !== ( $source['permitted_reuse'] ?? null )
				|| 'orientation_only' !== ( $source['geometry_use'] ?? null )
			) {
				return array();
			}
			return array(
				array(
					'source_id'                 => $source_id,
					'publisher'                 => self::bounded_string( $source['publisher'] ?? '', 200 ),
					'title'                     => self::bounded_string( $source['title'] ?? '', 240 ),
					'source_cog_url'            => $source['url'],
					'item_id'                   => $expected['item_id'],
					'observed_at'               => $expected['observed_at'],
					'tile_cloud_cover_percent'  => $expected['tile_cloud_cover_percent'],
					'tile_cloud_metadata_scope' => $expected['tile_cloud_metadata_scope'],
					'registry_url'              => $expected['registry_url'],
					'legal_notice_url'          => $expected['legal_notice_url'],
					'processed_bounds'          => self::bounds( $expected['processed_bounds'] ),
					'processing'                => $expected['processing'],
					'processed_projection'      => $expected['processed_projection'],
					'processed_format'          => $expected['processed_format'],
					'usage_scope'               => $expected['usage_scope'],
					'limitations'               => $expected['limitations'],
					'attribution'               => $expected['attribution'],
				)
			);
		}
		return array();
	}

	/** @param mixed $presets Camera presets. @return array */
	private static function camera_presets( $presets ) {
		$result = array();
		foreach ( is_array( $presets ) ? $presets : array() as $preset ) {
			if ( ! is_array( $preset ) || '' === self::safe_token( $preset['preset_id'] ?? '' ) ) {
				continue;
			}
			$position = self::coordinate_pair( $preset['position'] ?? null );
			if ( null === $position ) {
				continue;
			}
			$result[] = array(
				'preset_id'  => self::safe_token( $preset['preset_id'] ),
				'label_he'   => self::bounded_string( $preset['label_he'] ?? '', 160 ),
				'position'   => $position,
				'height_m'   => is_numeric( $preset['height_m'] ?? null ) ? (float) $preset['height_m'] : null,
				'heading_deg' => is_numeric( $preset['heading_deg'] ?? null ) ? (float) $preset['heading_deg'] : 0.0,
				'pitch_deg'  => is_numeric( $preset['pitch_deg'] ?? null ) ? (float) $preset['pitch_deg'] : 0.0,
				'roll_deg'   => is_numeric( $preset['roll_deg'] ?? null ) ? (float) $preset['roll_deg'] : 0.0,
			);
		}
		return $result;
	}

	/** @param mixed $dimensions Decision dimensions. @return string[] */
	private static function decision_dimensions( $dimensions ) {
		$allowed = array(
			'offer_availability',
			'asking_price',
			'parcel_reference_match',
			'title_document_claim',
			'seller_authority',
			'road_access',
			'utility_access',
			'planning_classification',
			'protected_area_overlap',
			'slope_and_drainage',
			'coastal_and_environmental_constraints',
			'ownership_structure',
			'building_permit',
		);
		$result = array();
		foreach ( is_array( $dimensions ) ? $dimensions : array() as $dimension ) {
			if ( in_array( $dimension, $allowed, true ) && ! in_array( $dimension, $result, true ) ) {
				$result[] = $dimension;
			}
		}
		return $result;
	}

	/** @param mixed $tools Reviewed external tools. @return array */
	private static function official_tools( $tools ) {
		$result = array();
		foreach ( is_array( $tools ) ? $tools : array() as $tool ) {
			if ( ! is_array( $tool ) ) {
				continue;
			}
			$tool_id = self::bounded_string( $tool['tool_id'] ?? '', 80 );
			$url     = is_string( $tool['url'] ?? null ) ? $tool['url'] : '';
			$parts   = '' !== $url ? wp_parse_url( $url ) : false;
			if (
				'' === $tool_id
				|| ! is_array( $parts )
				|| 'https' !== ( $parts['scheme'] ?? null )
				|| '' === ( $parts['host'] ?? '' )
				|| isset( $parts['user'] )
				|| isset( $parts['pass'] )
			) {
				continue;
			}
			$limitations = array();
			foreach ( is_array( $tool['limitations_he'] ?? null ) ? $tool['limitations_he'] : array() as $limitation ) {
				$limitation = self::bounded_string( $limitation, 320 );
				if ( '' !== $limitation ) {
					$limitations[] = $limitation;
				}
			}
			$result[] = array(
				'tool_id'             => $tool_id,
				'label_he'            => self::bounded_string( $tool['label_he'] ?? '', 160 ),
				'purpose'             => self::safe_token( $tool['purpose'] ?? '' ),
				'url'                 => $url,
				'supports_dimensions' => self::decision_dimensions( $tool['supports_dimensions'] ?? array() ),
				'limitations_he'      => $limitations,
				'checked_on'          => self::safe_date_or_null( $tool['checked_on'] ?? null ),
				'next_review_on'      => self::safe_date_or_null( $tool['next_review_on'] ?? null ),
			);
		}
		return $result;
	}

	/** @param mixed $value Pair. @return array|null */
	private static function coordinate_pair( $value ) {
		if ( ! is_array( $value ) || ! is_numeric( $value['latitude'] ?? null ) || ! is_numeric( $value['longitude'] ?? null ) ) {
			return null;
		}
		$latitude  = (float) $value['latitude'];
		$longitude = (float) $value['longitude'];
		if ( ! is_finite( $latitude ) || ! is_finite( $longitude ) || -90 > $latitude || 90 < $latitude || -180 > $longitude || 180 < $longitude ) {
			return null;
		}
		return array( 'latitude' => $latitude, 'longitude' => $longitude );
	}

	/** @param mixed $value Bounds. @return array|null */
	private static function bounds( $value ) {
		if ( ! is_array( $value ) ) {
			return null;
		}
		$result = array();
		foreach ( array( 'south', 'north', 'west', 'east' ) as $key ) {
			if ( ! is_numeric( $value[ $key ] ?? null ) || ! is_finite( (float) $value[ $key ] ) ) {
				return null;
			}
			$result[ $key ] = (float) $value[ $key ];
		}
		return $result;
	}

	/** @param mixed $value Candidate token. @return string */
	private static function safe_token( $value ) {
		return is_string( $value ) && 1 === preg_match( '/\A[a-z][a-z0-9_-]{0,79}\z/D', $value ) ? $value : '';
	}

	/** @param mixed $value Candidate string. @param int $max Maximum length. @return string */
	private static function bounded_string( $value, $max ) {
		if ( ! is_string( $value ) ) {
			return '';
		}
		$clean = preg_replace( '/[\x00-\x1F\x7F]/u', '', $value );
		if ( ! is_string( $clean ) ) {
			return '';
		}
		$value = trim( $clean );
		return self::length( $value ) <= $max ? $value : '';
	}

	/** @param mixed $value Candidate string. @param int $max Maximum length. @return string|null */
	private static function nullable_bounded_string( $value, $max ) {
		if ( null === $value ) {
			return null;
		}
		$value = self::bounded_string( $value, $max );
		return '' === $value ? null : $value;
	}

	/** @param mixed $value Candidate date. @return string|null */
	private static function safe_date_or_null( $value ) {
		return is_string( $value ) && self::valid_date( $value ) ? $value : null;
	}

	/** @param string|null $as_of Optional date. @return string */
	private static function reference_date( $as_of ) {
		$as_of = null === $as_of ? gmdate( 'Y-m-d' ) : $as_of;
		if ( ! self::valid_date( $as_of ) ) {
			throw new RuntimeException( 'The Digital Islands reference date is invalid.' );
		}
		return $as_of;
	}

	/** @param mixed $value Candidate date. @return bool */
	private static function valid_date( $value ) {
		if ( ! is_string( $value ) || 1 !== preg_match( '/\A([0-9]{4})-([0-9]{2})-([0-9]{2})\z/D', $value, $match ) ) {
			return false;
		}
		return checkdate( (int) $match[2], (int) $match[3], (int) $match[1] );
	}

	/** @param string $value Text. @return string */
	private static function lower( $value ) {
		return function_exists( 'mb_strtolower' ) ? mb_strtolower( $value, 'UTF-8' ) : strtolower( $value );
	}

	/** @param string $value Text. @return int */
	private static function length( $value ) {
		if ( function_exists( 'mb_strlen' ) ) {
			return mb_strlen( $value, 'UTF-8' );
		}
		$count = preg_match_all( '/./us', $value, $matches );
		return false === $count ? strlen( $value ) : $count;
	}
}
