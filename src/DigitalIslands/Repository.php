<?php
/**
 * Lazy, immutable access to the generated Digital Islands registry.
 *
 * @package Thailand_Platform
 */

namespace Thailand_Platform\DigitalIslands;

use RuntimeException;

final class Repository {
	/** @var array|null */
	private static $registry = null;

	/** @return bool */
	public static function is_loaded() {
		return null !== self::$registry;
	}

	/**
	 * Load only after exact manifest and artifact verification.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null !== self::$registry ) {
			return self::$registry;
		}

		$manifest = ArtifactVerifier::verify();
		$registry = require ArtifactVerifier::registry_path();
		ArtifactVerifier::assert_registry_current();
		if ( ! is_array( $registry ) ) {
			throw new RuntimeException( 'The Digital Islands registry is invalid.' );
		}

		self::assert_exact_keys(
			$registry,
			array(
				'camera_presets',
				'canary_map_entities',
				'canonical',
				'checked_on',
				'contract_id',
				'counts',
				'coverage_summary',
				'dataset_version',
				'entities_by_id',
				'island',
				'land_decision_policy',
				'layers_by_id',
				'official_tools',
				'publication_state',
				'public_map_entities',
				'renderer_contract',
				'schema_sha256',
				'schema_version',
				'source_digest',
				'sources_by_id',
			),
			'registry'
		);

		if (
			ArtifactVerifier::CONTRACT_ID !== ( $registry['contract_id'] ?? null )
			|| $manifest['publication_state'] !== ( $registry['publication_state'] ?? null )
			|| 1 !== ( $registry['schema_version'] ?? null )
			|| $manifest['dataset_version'] !== ( $registry['dataset_version'] ?? null )
			|| $manifest['checked_on'] !== ( $registry['checked_on'] ?? null )
			|| $manifest['source_digest'] !== ( $registry['source_digest'] ?? null )
			|| $manifest['schema_sha256'] !== ( $registry['schema_sha256'] ?? null )
		) {
			throw new RuntimeException( 'The Digital Islands registry identity disagrees with its manifest.' );
		}

		foreach ( array( 'sources_by_id', 'layers_by_id', 'entities_by_id', 'canary_map_entities', 'public_map_entities', 'official_tools', 'counts' ) as $key ) {
			if ( ! is_array( $registry[ $key ] ?? null ) ) {
				throw new RuntimeException( 'A Digital Islands registry collection is invalid.' );
			}
		}

		self::assert_count( $registry['sources_by_id'], $manifest['counts']['sources'], 'source' );
		self::assert_count( $registry['official_tools'], $manifest['counts']['official_tools'], 'official tool' );
		self::assert_count( $registry['layers_by_id'], $manifest['counts']['layers'], 'layer' );
		self::assert_count( $registry['entities_by_id'], $manifest['counts']['entities'], 'entity' );
		self::assert_count( $registry['canary_map_entities'], $manifest['counts']['canary_map_entities'], 'Canary entity' );
		if (
			ArtifactVerifier::PUBLICATION_STATE_PRIVATE === $registry['publication_state']
			&& ( array() !== $registry['public_map_entities'] || 0 !== $manifest['counts']['public_map_entities'] )
		) {
			throw new RuntimeException( 'A private-review Digital Islands registry contains a public map payload.' );
		}
		if (
			ArtifactVerifier::PUBLICATION_STATE_PUBLIC === $registry['publication_state']
			&& ( array() === $registry['public_map_entities'] || 0 === $manifest['counts']['public_map_entities'] )
		) {
			throw new RuntimeException( 'A public-reviewed Digital Islands registry has no public map payload.' );
		}

		self::assert_canonical( $registry['canonical'] ?? null, $registry['publication_state'] );
		self::assert_island( $registry['island'] ?? null );
		self::assert_official_tools( $registry['official_tools'], $registry['sources_by_id'] );
		self::assert_canary_entities( $registry['canary_map_entities'], $registry['entities_by_id'] );
		self::assert_public_entities( $registry['public_map_entities'], $registry['canary_map_entities'], $registry['publication_state'] );

		self::$registry = $registry;
		return self::$registry;
	}

	/** @return array */
	public static function manifest() {
		return ArtifactVerifier::verify();
	}

	/** @return array */
	public static function island() {
		return self::all()['island'];
	}

	/** @return array */
	public static function layers() {
		return self::all()['layers_by_id'];
	}

	/** @return array */
	public static function official_tools() {
		return self::all()['official_tools'];
	}

	/** @return array */
	public static function canary_entities() {
		return self::all()['canary_map_entities'];
	}

	/** @return array */
	public static function public_entities() {
		return self::all()['public_map_entities'];
	}

	/** @return string */
	public static function canonical_path() {
		return self::all()['canonical']['canonical_path'];
	}

	/** @return string */
	public static function dataset_version() {
		return self::all()['dataset_version'];
	}

	/** @return string */
	public static function checked_on() {
		return self::all()['checked_on'];
	}

	/** @return string */
	public static function publication_state() {
		return self::all()['publication_state'];
	}

	/** @return bool */
	public static function canary_ready() {
		try {
			return array() !== self::canary_entities();
		} catch ( \Throwable $exception ) {
			unset( $exception );
			return false;
		}
	}

	/** @return bool */
	public static function public_ready() {
		try {
			$registry = self::all();
			return ArtifactVerifier::PUBLICATION_STATE_PUBLIC === $registry['publication_state']
				&& 'index' === ( $registry['canonical']['indexing_policy'] ?? null )
				&& array() !== $registry['public_map_entities'];
		} catch ( \Throwable $exception ) {
			unset( $exception );
			return false;
		}
	}

	/** @return void */
	public static function reset_for_tests() {
		self::$registry = null;
		ArtifactVerifier::reset_for_tests();
	}

	/**
	 * @param array $items    Collection.
	 * @param int   $expected Expected count.
	 * @param string $label   Error label.
	 * @return void
	 */
	private static function assert_count( $items, $expected, $label ) {
		if ( ! is_int( $expected ) || count( $items ) !== $expected ) {
			throw new RuntimeException( 'The Digital Islands ' . $label . ' count is inconsistent.' );
		}
	}

	/**
	 * @param mixed  $canonical        Canonical route contract.
	 * @param string $publication_state Reviewed publication state.
	 * @return void
	 */
	private static function assert_canonical( $canonical, $publication_state ) {
		self::assert_exact_keys(
			$canonical,
			array( 'breadcrumb_owner_ids', 'canonical_path', 'indexing_policy', 'owner_id', 'parent_owner_id', 'primary_keyword' ),
			'canonical route'
		);
		if (
			'koh-phangan-map' !== $canonical['owner_id']
			|| (
				ArtifactVerifier::PUBLICATION_STATE_PRIVATE === $publication_state
				&& 'noindex_follow' !== $canonical['indexing_policy']
			)
			|| (
				ArtifactVerifier::PUBLICATION_STATE_PUBLIC === $publication_state
				&& 'index' !== $canonical['indexing_policy']
			)
			|| ! is_string( $canonical['canonical_path'] )
			|| 1 !== preg_match( '/\A\/[^?#]+\/\z/uD', $canonical['canonical_path'] )
			|| ! is_array( $canonical['breadcrumb_owner_ids'] )
		) {
			throw new RuntimeException( 'The Digital Islands canonical route is unsafe.' );
		}
	}

	/** @param mixed $island Island contract. @return void */
	private static function assert_island( $island ) {
		self::assert_exact_keys(
			$island,
			array( 'aliases', 'bounds', 'center', 'district_geo_id', 'excluded_geo_ids', 'geo_id', 'geometry_provenance', 'names', 'province_geo_id', 'source_ids', 'subdistrict_geo_ids' ),
			'island'
		);
		if (
			'geo:th:island:ko-pha-ngan' !== $island['geo_id']
			|| 'geo:th:district:8405' !== $island['district_geo_id']
			|| array( 'geo:th:subdistrict:840501', 'geo:th:subdistrict:840502' ) !== $island['subdistrict_geo_ids']
			|| array( 'geo:th:subdistrict:840503' ) !== $island['excluded_geo_ids']
			|| ! is_array( $island['center'] )
			|| ! is_array( $island['bounds'] )
		) {
			throw new RuntimeException( 'The Digital Islands island boundary contract is invalid.' );
		}
	}

	/**
	 * @param array $tools   Official external tools.
	 * @param array $sources Reviewed evidence catalog.
	 * @return void
	 */
	private static function assert_official_tools( $tools, $sources ) {
		$expected = array(
			'koh-phangan-land-office',
			'lands-maps-parcel-lookup',
			'onep-environmental-rules',
		);
		$actual = array();
		foreach ( $tools as $tool ) {
			self::assert_exact_keys(
				$tool,
				array( 'checked_on', 'delivery', 'label_he', 'limitations_he', 'next_review_on', 'purpose', 'source_id', 'supports_dimensions', 'tool_id', 'url' ),
				'official tool'
			);
			$source_id = $tool['source_id'];
			if (
				! is_string( $tool['tool_id'] )
				|| 1 !== preg_match( '/\A[a-z0-9]+(?:-[a-z0-9]+)*\z/D', $tool['tool_id'] )
				|| ! isset( $sources[ $source_id ] )
				|| 'current' !== ( $sources[ $source_id ]['access_state'] ?? null )
				|| ( $sources[ $source_id ]['url'] ?? null ) !== $tool['url']
				|| 'external_official_link' !== $tool['delivery']
				|| ! is_array( $tool['supports_dimensions'] )
				|| ! is_array( $tool['limitations_he'] )
			) {
				throw new RuntimeException( 'A Digital Islands official tool is invalid.' );
			}
			$actual[] = $tool['tool_id'];
		}
		sort( $actual, SORT_STRING );
		if ( $expected !== $actual ) {
			throw new RuntimeException( 'The Digital Islands official tool set is incomplete.' );
		}
	}

	/**
	 * @param array $canary   Generated Canary payload.
	 * @param array $entities Full private registry.
	 * @return void
	 */
	private static function assert_canary_entities( $canary, $entities ) {
		$seen = array();
		foreach ( $canary as $entity ) {
			$entity_id = is_array( $entity ) ? ( $entity['entity_id'] ?? null ) : null;
			if (
				! is_string( $entity_id )
				|| isset( $seen[ $entity_id ] )
				|| ! isset( $entities[ $entity_id ] )
				|| 'map_only' !== ( $entity['public_state'] ?? null )
				|| 'map_only' !== ( $entity['indexing_policy'] ?? null )
				|| isset( $entity['holds'] )
				|| isset( $entity['conflicts'] )
			) {
				throw new RuntimeException( 'A Digital Islands Canary entity is not safely allowlisted.' );
			}
			$seen[ $entity_id ] = true;
		}
	}

	/**
	 * A public projection can never add or alter a private record at runtime.
	 * In the reviewed Live release it must be byte-for-byte identical to the
	 * complete 49-record Canary-safe projection, so omissions and injections
	 * both fail closed.
	 *
	 * @param array $public Public projection.
	 * @param array $canary Canary projection.
	 * @param string $publication_state Reviewed publication state.
	 * @return void
	 */
	private static function assert_public_entities( $public, $canary, $publication_state ) {
		$canary_by_id = array();
		foreach ( $canary as $entity ) {
			if ( is_array( $entity ) && is_string( $entity['entity_id'] ?? null ) ) {
				$canary_by_id[ $entity['entity_id'] ] = $entity;
			}
		}

		$seen = array();
		foreach ( $public as $entity ) {
			$entity_id = is_array( $entity ) ? ( $entity['entity_id'] ?? null ) : null;
			if (
				! is_string( $entity_id )
				|| isset( $seen[ $entity_id ] )
				|| ! isset( $canary_by_id[ $entity_id ] )
				|| $canary_by_id[ $entity_id ] !== $entity
			) {
				throw new RuntimeException( 'A public Digital Islands entity is outside the reviewed Canary projection.' );
			}
			$seen[ $entity_id ] = true;
		}

		if (
			ArtifactVerifier::PUBLICATION_STATE_PUBLIC === $publication_state
			&& ( 49 !== count( $public ) || 49 !== count( $canary ) || $public !== $canary )
		) {
			throw new RuntimeException( 'The Live Digital Islands projection is not the exact reviewed 49-entity set.' );
		}
	}

	/**
	 * @param mixed    $value    Candidate object.
	 * @param string[] $expected Exact keys.
	 * @param string   $label    Error label.
	 * @return void
	 */
	private static function assert_exact_keys( $value, $expected, $label ) {
		if ( ! is_array( $value ) ) {
			throw new RuntimeException( 'The Digital Islands ' . $label . ' is invalid.' );
		}
		$actual = array_keys( $value );
		sort( $actual, SORT_STRING );
		sort( $expected, SORT_STRING );
		if ( $actual !== $expected ) {
			throw new RuntimeException( 'The Digital Islands ' . $label . ' shape is invalid.' );
		}
	}
}
