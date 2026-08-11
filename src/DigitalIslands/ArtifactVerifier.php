<?php
/**
 * Exact generated-artifact verification for the Digital Islands runtime.
 *
 * @package Thailand_Platform
 */

namespace Thailand_Platform\DigitalIslands;

use RuntimeException;

final class ArtifactVerifier {
	const CONTRACT_ID           = 'thailand-digital-island-world-v1';
	const PUBLICATION_STATE_PRIVATE = 'private_review';
	const PUBLICATION_STATE_PUBLIC  = 'live';
	const PUBLICATION_STATE         = self::PUBLICATION_STATE_PUBLIC;
	const MANIFEST_PATH         = 'resources/digital-islands/manifest.json';
	const REGISTRY_PATH         = 'resources/digital-islands/registry.php';
	const MANIFEST_MAX_BYTES    = 65536;

	/** @var array|null */
	private static $manifest = null;

	/**
	 * Verify the manifest identity, package boundary, byte count and SHA-256.
	 *
	 * @return array
	 */
	public static function verify() {
		if ( null !== self::$manifest ) {
			return self::$manifest;
		}

		$manifest_path = self::absolute_path( self::MANIFEST_PATH );
		$registry_path = self::absolute_path( self::REGISTRY_PATH );
		self::assert_regular_readable_file( $manifest_path, self::MANIFEST_MAX_BYTES );

		$raw = file_get_contents( $manifest_path );
		if ( false === $raw ) {
			throw new RuntimeException( 'The Digital Islands manifest cannot be read.' );
		}

		$manifest = StrictJson::decode_object( $raw );
		self::assert_exact_keys(
			$manifest,
			array(
				'artifacts',
				'checked_on',
				'contract_id',
				'counts',
				'dataset_version',
				'publication_state',
				'schema_sha256',
				'schema_version',
				'source_digest',
			),
			'manifest'
		);

		if (
			self::CONTRACT_ID !== ( $manifest['contract_id'] ?? null )
			|| ! in_array(
				$manifest['publication_state'] ?? null,
				array( self::PUBLICATION_STATE_PRIVATE, self::PUBLICATION_STATE_PUBLIC ),
				true
			)
			|| 1 !== ( $manifest['schema_version'] ?? null )
			|| ! self::valid_version( $manifest['dataset_version'] ?? null )
			|| ! self::valid_date( $manifest['checked_on'] ?? null )
			|| ! self::valid_digest( $manifest['schema_sha256'] ?? null )
			|| ! self::valid_digest( $manifest['source_digest'] ?? null )
		) {
			throw new RuntimeException( 'The Digital Islands manifest identity is invalid.' );
		}

		self::assert_counts( $manifest['counts'] ?? null, $manifest['publication_state'] );
		self::assert_exact_keys( $manifest['artifacts'] ?? array(), array( self::REGISTRY_PATH ), 'artifact inventory' );

		$artifact = $manifest['artifacts'][ self::REGISTRY_PATH ] ?? null;
		self::assert_exact_keys( is_array( $artifact ) ? $artifact : array(), array( 'bytes', 'sha256' ), 'artifact receipt' );
		if (
			! is_int( $artifact['bytes'] ?? null )
			|| 0 >= $artifact['bytes']
			|| ! self::valid_digest( $artifact['sha256'] ?? null )
		) {
			throw new RuntimeException( 'The Digital Islands artifact receipt is invalid.' );
		}

		self::assert_regular_readable_file( $registry_path, null );
		self::assert_registry_receipt( $registry_path, $artifact );

		self::$manifest = $manifest;
		return self::$manifest;
	}

	/** @return string */
	public static function registry_path() {
		self::verify();
		return self::absolute_path( self::REGISTRY_PATH );
	}

	/**
	 * Recheck the immutable receipt after the registry has been evaluated.
	 *
	 * @return void
	 */
	public static function assert_registry_current() {
		$manifest      = self::verify();
		$registry_path = self::absolute_path( self::REGISTRY_PATH );
		self::assert_regular_readable_file( $registry_path, null );
		self::assert_registry_receipt( $registry_path, $manifest['artifacts'][ self::REGISTRY_PATH ] );
	}

	/** @return void */
	public static function reset_for_tests() {
		self::$manifest = null;
	}

	/**
	 * @param string $relative_path Plugin-relative allowlisted path.
	 * @return string
	 */
	private static function absolute_path( $relative_path ) {
		if ( ! defined( 'THAILAND_PLATFORM_DIR' ) ) {
			throw new RuntimeException( 'The Thailand Platform root is unavailable.' );
		}

		$root = rtrim( str_replace( '\\', '/', THAILAND_PLATFORM_DIR ), '/' );
		$path = $root . '/' . $relative_path;
		if ( 0 !== strpos( str_replace( '\\', '/', $path ), $root . '/' ) ) {
			throw new RuntimeException( 'The Digital Islands artifact path escaped the plugin root.' );
		}

		return $path;
	}

	/**
	 * @param string   $path      Absolute path.
	 * @param int|null $max_bytes Optional upper bound.
	 * @return void
	 */
	private static function assert_regular_readable_file( $path, $max_bytes ) {
		clearstatcache( true, $path );
		$size = is_file( $path ) && is_readable( $path ) && ! is_link( $path ) ? filesize( $path ) : false;
		if ( false === $size || 0 >= $size || ( null !== $max_bytes && $max_bytes < $size ) ) {
			throw new RuntimeException( 'A required Digital Islands artifact is unavailable.' );
		}
	}

	/**
	 * @param string $registry_path Absolute registry path.
	 * @param array  $artifact      Verified manifest receipt.
	 * @return void
	 */
	private static function assert_registry_receipt( $registry_path, $artifact ) {
		clearstatcache( true, $registry_path );
		$actual_bytes  = filesize( $registry_path );
		$actual_digest = hash_file( 'sha256', $registry_path );
		if (
			$artifact['bytes'] !== $actual_bytes
			|| ! is_string( $actual_digest )
			|| ! hash_equals( $artifact['sha256'], strtolower( $actual_digest ) )
		) {
			throw new RuntimeException( 'The Digital Islands registry does not match its manifest.' );
		}
	}

	/**
	 * @param mixed  $counts            Manifest counts.
	 * @param string $publication_state Reviewed publication state.
	 * @return void
	 */
	private static function assert_counts( $counts, $publication_state ) {
		if ( ! is_array( $counts ) ) {
			throw new RuntimeException( 'The Digital Islands manifest counts are invalid.' );
		}
		self::assert_exact_keys(
			$counts,
			array( 'canary_map_entities', 'entities', 'entity_types', 'layers', 'official_tools', 'public_map_entities', 'sources' ),
			'manifest counts'
		);

		foreach ( array( 'canary_map_entities', 'entities', 'layers', 'official_tools', 'public_map_entities', 'sources' ) as $key ) {
			if ( ! is_int( $counts[ $key ] ) || 0 > $counts[ $key ] ) {
				throw new RuntimeException( 'A Digital Islands manifest count is invalid.' );
			}
		}
		if ( ! is_array( $counts['entity_types'] ) ) {
			throw new RuntimeException( 'The Digital Islands entity-type counts are invalid.' );
		}
		if (
			( self::PUBLICATION_STATE_PRIVATE === $publication_state && 0 !== $counts['public_map_entities'] )
			|| (
				self::PUBLICATION_STATE_PUBLIC === $publication_state
				&& (
					49 !== $counts['entities']
					|| 49 !== $counts['canary_map_entities']
					|| 49 !== $counts['public_map_entities']
				)
			)
		) {
			throw new RuntimeException( 'The Digital Islands publication state disagrees with its public entity count.' );
		}
		foreach ( $counts['entity_types'] as $entity_type => $count ) {
			if ( ! is_string( $entity_type ) || 1 !== preg_match( '/\A[a-z][a-z0-9_]{0,63}\z/D', $entity_type ) || ! is_int( $count ) || 0 > $count ) {
				throw new RuntimeException( 'A Digital Islands entity-type count is invalid.' );
			}
		}
	}

	/**
	 * @param array    $value    Candidate object.
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

	/** @param mixed $value Candidate version. @return bool */
	private static function valid_version( $value ) {
		return is_string( $value ) && 1 === preg_match( '/\A[0-9]{4}\.[0-9]{2}\.[0-9]{2}(?:\.[0-9]+)?\z/D', $value );
	}

	/** @param mixed $value Candidate date. @return bool */
	private static function valid_date( $value ) {
		if ( ! is_string( $value ) || 1 !== preg_match( '/\A([0-9]{4})-([0-9]{2})-([0-9]{2})\z/D', $value, $match ) ) {
			return false;
		}
		return checkdate( (int) $match[2], (int) $match[3], (int) $match[1] );
	}

	/** @param mixed $value Candidate SHA-256. @return bool */
	private static function valid_digest( $value ) {
		return is_string( $value ) && 1 === preg_match( '/\A[0-9a-f]{64}\z/D', $value );
	}
}
