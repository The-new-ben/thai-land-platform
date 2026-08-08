<?php
/**
 * Lazy reader for the compiled geography registry.
 *
 * @package Thailand_Platform
 */

namespace Thailand_Platform\Geography;

use RuntimeException;

final class Repository {
	/**
	 * Validated compiled registry for the current request.
	 *
	 * @var array|null
	 */
	private static $registry = null;

	/**
	 * Expose lazy-load state for release contract verification.
	 *
	 * @return bool
	 */
	public static function is_loaded() {
		return null !== self::$registry;
	}

	/**
	 * Load the generated PHP index only when geography is requested.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null !== self::$registry ) {
			return self::$registry;
		}

		$path = THAILAND_PLATFORM_DIR . 'resources/geography/registry.php';

		if ( ! is_readable( $path ) || 0 === filesize( $path ) ) {
			throw new RuntimeException( 'The compiled geography registry is unavailable.' );
		}

		$registry = require $path;

		if ( ! is_array( $registry ) ) {
			throw new RuntimeException( 'The compiled geography registry is invalid.' );
		}

		self::assert_exact_keys(
			$registry,
			array(
				'schema_version',
				'dataset_version',
				'country_id',
				'public_digest',
				'entities_by_id',
				'indexes',
				'public_payload',
			),
			'compiled registry'
		);

		if (
			! is_string( $registry['schema_version'] )
			|| 1 !== preg_match( '/^[0-9]+\.[0-9]+\.[0-9]+$/', $registry['schema_version'] )
			|| ! is_string( $registry['dataset_version'] )
			|| 1 !== preg_match( '/^[0-9]+(?:\.[0-9]+){2,3}$/', $registry['dataset_version'] )
			|| ! is_string( $registry['country_id'] )
			|| '' === $registry['country_id']
			|| ! is_string( $registry['public_digest'] )
			|| 1 !== preg_match( '/^[0-9a-f]{64}$/', $registry['public_digest'] )
			|| ! is_array( $registry['entities_by_id'] )
			|| ! is_array( $registry['indexes'] )
			|| ! is_array( $registry['public_payload'] )
		) {
			throw new RuntimeException( 'The compiled geography registry identity is invalid.' );
		}

		self::assert_exact_keys(
			$registry['indexes'],
			array(
				'by_external_id',
				'by_slug',
				'by_alias',
				'relations_by_subject',
				'children_by_parent',
				'members_by_scheme',
			),
			'compiled geography indexes'
		);

		self::assert_exact_keys(
			$registry['public_payload'],
			array(
				'schema_version',
				'dataset_version',
				'country',
				'classification_schemes',
				'regions',
				'provinces',
			),
			'public geography payload'
		);

		if (
			$registry['schema_version'] !== $registry['public_payload']['schema_version']
			|| $registry['dataset_version'] !== $registry['public_payload']['dataset_version']
			|| ! isset( $registry['entities_by_id'][ $registry['country_id'] ] )
			|| 77 !== count( $registry['public_payload']['provinces'] )
		) {
			throw new RuntimeException( 'The compiled geography payload is inconsistent.' );
		}

		foreach ( $registry['entities_by_id'] as $entity_id => $entity ) {
			self::assert_entity( $entity_id, $entity );
		}

		self::$registry = $registry;

		return self::$registry;
	}

	/**
	 * Return a canonical entity with an indexed lookup.
	 *
	 * @param string $entity_id Canonical entity ID.
	 * @return array|null
	 */
	public static function entity( $entity_id ) {
		$registry = self::all();
		$key      = (string) $entity_id;

		return isset( $registry['entities_by_id'][ $key ] )
			? $registry['entities_by_id'][ $key ]
			: null;
	}

	/**
	 * Return the exact public client payload without rebuilding it.
	 *
	 * @return array
	 */
	public static function public_payload() {
		return self::all()['public_payload'];
	}

	/**
	 * @return string
	 */
	public static function public_digest() {
		return self::all()['public_digest'];
	}

	/**
	 * @return string
	 */
	public static function dataset_version() {
		return self::all()['dataset_version'];
	}

	/**
	 * Resolve an external identifier through a direct namespace index.
	 *
	 * @param string $namespace External namespace.
	 * @param string $value External value.
	 * @return string|null
	 */
	public static function entity_id_by_external_id( $namespace, $value ) {
		$index = self::all()['indexes']['by_external_id'];
		$namespace = (string) $namespace;
		$value     = (string) $value;

		return isset( $index[ $namespace ][ $value ] )
			? $index[ $namespace ][ $value ]
			: null;
	}

	/**
	 * Resolve a slug within an explicit geography type.
	 *
	 * @param string $type Geography type.
	 * @param string $slug Stable slug.
	 * @return string|null
	 */
	public static function entity_id_by_slug( $type, $slug ) {
		$index = self::all()['indexes']['by_slug'];
		$type  = (string) $type;
		$slug  = (string) $slug;

		return isset( $index[ $type ][ $slug ] )
			? $index[ $type ][ $slug ]
			: null;
	}

	/**
	 * Return all exact alias candidates for a normalized locale key.
	 *
	 * @param string $locale Locale key.
	 * @param string $normalized_alias Normalized alias.
	 * @return array
	 */
	public static function alias_candidates( $locale, $normalized_alias ) {
		$index = self::all()['indexes']['by_alias'];
		$locale = (string) $locale;
		$normalized_alias = (string) $normalized_alias;

		return isset( $index[ $locale ][ $normalized_alias ] )
			? $index[ $locale ][ $normalized_alias ]
			: array();
	}

	/**
	 * Return typed outbound relations for an entity.
	 *
	 * @param string      $subject_id Subject entity ID.
	 * @param string|null $type Optional relation type.
	 * @return array
	 */
	public static function relations( $subject_id, $type = null ) {
		$index     = self::all()['indexes']['relations_by_subject'];
		$relations = isset( $index[ $subject_id ] ) ? $index[ $subject_id ] : array();

		if ( null === $type ) {
			return $relations;
		}

		return array_values(
			array_filter(
				$relations,
				static function ( $relation ) use ( $type ) {
					return isset( $relation['type'] ) && (string) $type === $relation['type'];
				}
			)
		);
	}

	/**
	 * Return direct children from a named relation index.
	 *
	 * @param string $parent_id Parent entity ID.
	 * @param string $relation_type Relation type.
	 * @return array
	 */
	public static function children( $parent_id, $relation_type = 'admin_parent' ) {
		$index = self::all()['indexes']['children_by_parent'];

		return isset( $index[ $relation_type ][ $parent_id ] )
			? $index[ $relation_type ][ $parent_id ]
			: array();
	}

	/**
	 * Return the members of one classification entity within one scheme.
	 *
	 * @param string $scheme_id Classification scheme ID.
	 * @param string $classification_id Classification entity ID.
	 * @return array
	 */
	public static function members( $scheme_id, $classification_id ) {
		$index = self::all()['indexes']['members_by_scheme'];

		return isset( $index[ $scheme_id ][ $classification_id ] )
			? $index[ $scheme_id ][ $classification_id ]
			: array();
	}

	/**
	 * Validate one generated geography entity.
	 *
	 * @param string $entity_id Index key.
	 * @param mixed  $entity Entity payload.
	 * @return void
	 */
	private static function assert_entity( $entity_id, $entity ) {
		if ( ! is_array( $entity ) ) {
			throw new RuntimeException( 'A compiled geography entity is invalid.' );
		}

		self::assert_exact_keys(
			$entity,
			array( 'id', 'kind', 'type', 'status', 'slug', 'names', 'external_ids', 'priority', 'geometry' ),
			'compiled geography entity'
		);
		if ( is_array( $entity['names'] ) ) {
			self::assert_exact_keys( $entity['names'], array( 'he', 'en', 'th' ), 'compiled geography names' );
		}

		if (
			(string) $entity_id !== $entity['id']
			|| 'geography' !== $entity['kind']
			|| ! in_array( $entity['type'], array( 'country', 'statistical_region', 'province' ), true )
			|| ! in_array( $entity['status'], array( 'active', 'retired' ), true )
			|| ! is_array( $entity['names'] )
			|| ! is_array( $entity['external_ids'] )
			|| ! is_bool( $entity['priority'] )
			|| ( null !== $entity['geometry'] && ! is_array( $entity['geometry'] ) )
		) {
			throw new RuntimeException( 'A compiled geography entity has an invalid identity.' );
		}
	}

	/**
	 * Enforce exact generated contracts without depending on key order.
	 *
	 * @param array  $value Value to inspect.
	 * @param array  $expected Expected keys.
	 * @param string $label Contract label.
	 * @return void
	 */
	private static function assert_exact_keys( $value, $expected, $label ) {
		$actual = array_keys( $value );
		sort( $actual, SORT_STRING );
		sort( $expected, SORT_STRING );

		if ( $actual !== $expected ) {
			throw new RuntimeException( ucfirst( $label ) . ' fields are missing or unexpected.' );
		}
	}
}
