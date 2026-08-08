<?php
/**
 * Immutable Thailand geography registry.
 *
 * @package Thailand_Platform
 */

namespace Thailand_Platform\Geography;

use RuntimeException;

final class Registry {
	const EXPECTED_PROVINCES = 77;
	const EXPECTED_REGIONS   = 7;
	const EXPECTED_PROVINCE_CODES = array(
		'10', '11', '12', '13', '14', '15', '16', '17', '18', '19',
		'20', '21', '22', '23', '24', '25', '26', '27',
		'30', '31', '32', '33', '34', '35', '36', '37', '38', '39',
		'40', '41', '42', '43', '44', '45', '46', '47', '48', '49',
		'50', '51', '52', '53', '54', '55', '56', '57', '58',
		'60', '61', '62', '63', '64', '65', '66', '67',
		'70', '71', '72', '73', '74', '75', '76', '77',
		'80', '81', '82', '83', '84', '85', '86',
		'90', '91', '92', '93', '94', '95', '96',
	);
	const EXPECTED_REGION_COUNTS = array(
		'bangkok-vicinity' => 6,
		'central'          => 6,
		'eastern'          => 8,
		'northeastern'     => 20,
		'northern'         => 17,
		'southern'         => 14,
		'western'          => 6,
	);

	/**
	 * Cached validated registry for the current request.
	 *
	 * @var array|null
	 */
	private static $registry = null;

	/**
	 * Load and validate the complete first-level geography registry.
	 *
	 * @return array
	 * @throws RuntimeException When an immutable source is missing or invalid.
	 */
	public static function all() {
		if ( null !== self::$registry ) {
			return self::$registry;
		}

		$metadata = self::read_metadata();
		$regions  = self::index_regions( $metadata );
		$provinces = self::read_provinces( $regions );

		self::$registry = array(
			'schema_version'          => $metadata['schema_version'],
			'country'                 => $metadata['country'],
			'administrative_hierarchy' => $metadata['administrative_hierarchy'],
			'editorial_entity_types'  => $metadata['editorial_entity_types'],
			'region_model'            => $metadata['region_model'],
			'sources'                 => $metadata['sources'],
			'provinces'               => $provinces,
		);

		return self::$registry;
	}

	/**
	 * Resolve a province by its two digit official code or stable Latin slug.
	 *
	 * @param string $identifier Province code or slug.
	 * @return array|null
	 */
	public static function province( $identifier ) {
		$needle = strtolower( trim( (string) $identifier ) );

		if ( '' === $needle ) {
			return null;
		}

		foreach ( self::all()['provinces'] as $province ) {
			if ( $needle === $province['code'] || $needle === $province['slug'] ) {
				return $province;
			}
		}

		return null;
	}

	/**
	 * Return the public, source-backed registry payload.
	 *
	 * @return array
	 */
	public static function public_payload() {
		$registry = self::all();

		return array(
			'schema_version' => $registry['schema_version'],
			'country'        => $registry['country'],
			'counts'         => array(
				'regions'   => count( $registry['region_model']['regions'] ),
				'provinces' => count( $registry['provinces'] ),
			),
			'region_model'   => array(
				'id'                       => $registry['region_model']['id'],
				'kind'                     => $registry['region_model']['kind'],
				'is_administrative_parent' => $registry['region_model']['is_administrative_parent'],
				'as_of'                    => $registry['region_model']['as_of'],
				'regions'                  => $registry['region_model']['regions'],
			),
			'provinces'      => $registry['provinces'],
		);
	}

	/**
	 * Produce a stable content digest for cache validation.
	 *
	 * @return string
	 */
	public static function digest() {
		$payload = wp_json_encode( self::public_payload(), JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );

		if ( false === $payload ) {
			throw new RuntimeException( 'The geography registry could not be encoded.' );
		}

		return hash( 'sha256', $payload );
	}

	/**
	 * Read the geography metadata document.
	 *
	 * @return array
	 */
	private static function read_metadata() {
		$path = THAILAND_PLATFORM_DIR . 'data/geography/regions.json';

		if ( ! is_readable( $path ) ) {
			throw new RuntimeException( 'The geography metadata source is unavailable.' );
		}

		$raw = file_get_contents( $path );

		if ( false === $raw || '' === trim( $raw ) ) {
			throw new RuntimeException( 'The geography metadata source is empty.' );
		}

		$metadata = json_decode( $raw, true );

		if ( ! is_array( $metadata ) || JSON_ERROR_NONE !== json_last_error() ) {
			throw new RuntimeException( 'The geography metadata source is invalid JSON.' );
		}

		$required = array(
			'schema_version',
			'country',
			'administrative_hierarchy',
			'editorial_entity_types',
			'region_model',
			'sources',
		);

		if ( $required !== array_keys( $metadata ) ) {
			throw new RuntimeException( 'The geography metadata fields are missing or unexpected.' );
		}

		if ( 1 !== preg_match( '/^[0-9]+\.[0-9]+\.[0-9]+$/', (string) $metadata['schema_version'] ) ) {
			throw new RuntimeException( 'The geography schema version is invalid.' );
		}

		if (
			! is_array( $metadata['country'] )
			|| array( 'id', 'name_he', 'name_en', 'name_th' ) !== array_keys( $metadata['country'] )
			|| 'TH' !== $metadata['country']['id']
		) {
			throw new RuntimeException( 'The geography country record is invalid.' );
		}

		if (
			array( 'country', 'province', 'district', 'subdistrict', 'village' ) !== $metadata['administrative_hierarchy']
			|| ! is_array( $metadata['editorial_entity_types'] )
			|| count( $metadata['editorial_entity_types'] ) !== count( array_unique( $metadata['editorial_entity_types'] ) )
		) {
			throw new RuntimeException( 'The geography hierarchy or entity types are invalid.' );
		}

		foreach ( $metadata['editorial_entity_types'] as $entity_type ) {
			if ( 1 !== preg_match( '/^[a-z0-9]+(?:_[a-z0-9]+)*$/', (string) $entity_type ) ) {
				throw new RuntimeException( 'An editorial entity type is invalid.' );
			}
		}

		if ( ! is_array( $metadata['sources'] ) || 3 > count( $metadata['sources'] ) ) {
			throw new RuntimeException( 'The geography source register is incomplete.' );
		}

		$source_ids = array();
		foreach ( $metadata['sources'] as $source ) {
			if (
				! is_array( $source )
				|| array( 'id', 'authority', 'url', 'covers' ) !== array_keys( $source )
				|| 1 !== preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', (string) $source['id'] )
				|| isset( $source_ids[ $source['id'] ] )
				|| 0 !== strpos( (string) $source['url'], 'https://' )
				|| '' === trim( (string) $source['authority'] )
				|| ! is_array( $source['covers'] )
				|| array() === $source['covers']
			) {
				throw new RuntimeException( 'A geography source record is invalid.' );
			}

			$source_ids[ $source['id'] ] = true;
		}

		if ( self::contains_forbidden_dash( $metadata ) ) {
			throw new RuntimeException( 'The geography metadata contains a forbidden dash character.' );
		}

		return $metadata;
	}

	/**
	 * Validate and index the seven statistical regions.
	 *
	 * @param array $metadata Registry metadata.
	 * @return array
	 */
	private static function index_regions( $metadata ) {
		$model = $metadata['region_model'];

		if (
			! is_array( $model )
			|| array( 'id', 'kind', 'is_administrative_parent', 'as_of', 'regions' ) !== array_keys( $model )
			|| true === $model['is_administrative_parent']
			|| 'statistical_facet' !== $model['kind']
			|| ! is_array( $model['regions'] )
			|| self::EXPECTED_REGIONS !== count( $model['regions'] )
		) {
			throw new RuntimeException( 'The statistical region model is invalid.' );
		}

		$regions = array();

		foreach ( $model['regions'] as $region ) {
			if (
				! is_array( $region )
				|| array( 'id', 'name_he', 'name_en', 'name_th' ) !== array_keys( $region )
				|| 1 !== preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', (string) $region['id'] )
				|| isset( $regions[ $region['id'] ] )
			) {
				throw new RuntimeException( 'A statistical region record is invalid.' );
			}

			foreach ( array( 'name_he', 'name_en', 'name_th' ) as $name_key ) {
				if ( '' === trim( (string) $region[ $name_key ] ) ) {
					throw new RuntimeException( 'A statistical region name is empty.' );
				}
			}

			$regions[ $region['id'] ] = true;
		}

		return $regions;
	}

	/**
	 * Read and validate all province rows.
	 *
	 * @param array $regions Valid region identifiers.
	 * @return array
	 */
	private static function read_provinces( $regions ) {
		$path = THAILAND_PLATFORM_DIR . 'data/geography/provinces.csv';

		if ( ! is_readable( $path ) ) {
			throw new RuntimeException( 'The province registry source is unavailable.' );
		}

		$handle = fopen( $path, 'rb' );

		if ( false === $handle ) {
			throw new RuntimeException( 'The province registry source could not be opened.' );
		}

		$expected_header = array( 'code', 'slug', 'name_en', 'name_th', 'name_he', 'region_id', 'priority' );
		$header          = fgetcsv( $handle, 0, ',', '"', '' );
		$provinces       = array();
		$codes           = array();
		$slugs           = array();
		$names_en        = array();
		$names_th        = array();
		$names_he        = array();
		$region_counts   = array();

		if ( $expected_header !== $header ) {
			fclose( $handle );
			throw new RuntimeException( 'The province registry header is invalid.' );
		}

		while ( false !== ( $row = fgetcsv( $handle, 0, ',', '"', '' ) ) ) {
			if ( array( null ) === $row ) {
				continue;
			}

			if ( count( $expected_header ) !== count( $row ) ) {
				fclose( $handle );
				throw new RuntimeException( 'A province registry row has the wrong field count.' );
			}

			$province = array_combine( $expected_header, $row );

			if ( false === $province ) {
				fclose( $handle );
				throw new RuntimeException( 'A province registry row could not be parsed.' );
			}

			$code = (string) $province['code'];
			$slug = (string) $province['slug'];

			if (
				1 !== preg_match( '/^[0-9]{2}$/', $code )
				|| 1 !== preg_match( '/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug )
				|| isset( $codes[ $code ] )
				|| isset( $slugs[ $slug ] )
				|| ! isset( $regions[ $province['region_id'] ] )
				|| ! in_array( $province['priority'], array( '0', '1' ), true )
			) {
				fclose( $handle );
				throw new RuntimeException( 'A province registry identity is invalid.' );
			}

			foreach ( array( 'name_en', 'name_th', 'name_he' ) as $name_key ) {
				if ( '' === trim( (string) $province[ $name_key ] ) || trim( (string) $province[ $name_key ] ) !== $province[ $name_key ] ) {
					fclose( $handle );
					throw new RuntimeException( 'A province registry name is empty.' );
				}
			}

			if (
				isset( $names_en[ $province['name_en'] ] )
				|| isset( $names_th[ $province['name_th'] ] )
				|| isset( $names_he[ $province['name_he'] ] )
			) {
				fclose( $handle );
				throw new RuntimeException( 'A province registry name is duplicated.' );
			}

			if ( self::contains_forbidden_dash( $province ) ) {
				fclose( $handle );
				throw new RuntimeException( 'A province registry row contains a forbidden dash character.' );
			}

			$province['id']       = 'TH-' . $code;
			$province['priority'] = '1' === $province['priority'];
			$codes[ $code ]       = true;
			$slugs[ $slug ]       = true;
			$names_en[ $province['name_en'] ] = true;
			$names_th[ $province['name_th'] ] = true;
			$names_he[ $province['name_he'] ] = true;
			$region_counts[ $province['region_id'] ] = isset( $region_counts[ $province['region_id'] ] )
				? $region_counts[ $province['region_id'] ] + 1
				: 1;
			$provinces[]          = $province;
		}

		fclose( $handle );

		if ( self::EXPECTED_PROVINCES !== count( $provinces ) ) {
			throw new RuntimeException( 'The province registry must contain exactly 77 provinces.' );
		}

		if ( self::EXPECTED_PROVINCE_CODES !== array_map( 'strval', array_keys( $codes ) ) ) {
			throw new RuntimeException( 'The province registry code set or ordering is invalid.' );
		}

		ksort( $region_counts );
		if ( self::EXPECTED_REGION_COUNTS !== $region_counts ) {
			throw new RuntimeException( 'The province registry region distribution is invalid.' );
		}

		return $provinces;
	}

	/**
	 * Recursively reject the two public-copy dash characters.
	 *
	 * @param mixed $value Value to inspect.
	 * @return bool
	 */
	private static function contains_forbidden_dash( $value ) {
		if ( is_string( $value ) ) {
			return false !== strpos( $value, "\xE2\x80\x93" ) || false !== strpos( $value, "\xE2\x80\x94" );
		}

		if ( is_array( $value ) ) {
			foreach ( $value as $nested ) {
				if ( self::contains_forbidden_dash( $nested ) ) {
					return true;
				}
			}
		}

		return false;
	}
}
