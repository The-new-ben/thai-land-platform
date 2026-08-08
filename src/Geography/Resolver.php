<?php
/**
 * Exact, ambiguity-safe geography identity resolver.
 *
 * @package Thailand_Platform
 */

namespace Thailand_Platform\Geography;

final class Resolver {
	const STATUS_RESOLVED  = 'resolved';
	const STATUS_AMBIGUOUS = 'ambiguous';
	const STATUS_RETIRED   = 'retired';
	const STATUS_NOT_FOUND = 'not_found';

	/**
	 * Resolve one reviewed identity without fuzzy guessing.
	 *
	 * Supported options are locale, context_id, type, and external_namespace.
	 *
	 * @param string $query Canonical ID, code, slug, or reviewed alias.
	 * @param array  $options Resolution context.
	 * @return array
	 */
	public static function resolve( $query, $options = array() ) {
		$query      = trim( (string) $query );
		$normalized = self::normalize( $query );
		$type       = isset( $options['type'] ) ? (string) $options['type'] : null;
		$locale     = isset( $options['locale'] ) ? (string) $options['locale'] : null;
		$context_id = isset( $options['context_id'] ) ? (string) $options['context_id'] : null;

		if ( '' === $query || '' === $normalized ) {
			return self::result( self::STATUS_NOT_FOUND, $query, $normalized );
		}

		$canonical = Repository::entity( $query );
		if ( null !== $canonical && self::matches_type( $canonical, $type ) ) {
			return self::entity_result( $canonical, $query, $normalized );
		}

		$namespace = isset( $options['external_namespace'] )
			? (string) $options['external_namespace']
			: ( 1 === preg_match( '/^[0-9]{2}$/', $query ) ? 'moi_province_code' : null );

		if ( null !== $namespace ) {
			$external_id = Repository::entity_id_by_external_id( $namespace, $query );
			$external    = null === $external_id ? null : Repository::entity( $external_id );

			if ( null !== $external && self::matches_type( $external, $type ) ) {
				return self::entity_result( $external, $query, $normalized );
			}
		}

		$slug_ids = array();
		$slug_types = null === $type
			? array( 'country', 'statistical_region', 'province' )
			: array( $type );

		foreach ( $slug_types as $slug_type ) {
			$slug_id = Repository::entity_id_by_slug( $slug_type, strtolower( $query ) );
			if ( null !== $slug_id ) {
				$slug_ids[ $slug_id ] = true;
			}
		}

		if ( 1 === count( $slug_ids ) ) {
			$slug_entity = Repository::entity( array_key_first( $slug_ids ) );
			if ( null !== $slug_entity ) {
				return self::entity_result( $slug_entity, $query, $normalized );
			}
		}

		if ( 1 < count( $slug_ids ) ) {
			return self::result( self::STATUS_AMBIGUOUS, $query, $normalized, null, array_keys( $slug_ids ) );
		}

		$locales    = null === $locale ? array( 'he', 'en', 'th' ) : array( $locale );
		$candidates = array();

		foreach ( $locales as $candidate_locale ) {
			foreach ( Repository::alias_candidates( $candidate_locale, $normalized ) as $candidate ) {
				if ( ! is_array( $candidate ) || ! isset( $candidate['entity_id'], $candidate['status'] ) ) {
					continue;
				}

				$entity = Repository::entity( $candidate['entity_id'] );
				if ( null === $entity || ! self::matches_type( $entity, $type ) ) {
					continue;
				}

				$candidate_context = isset( $candidate['context_id'] ) ? $candidate['context_id'] : null;
				if ( null !== $context_id && null !== $candidate_context && $context_id !== $candidate_context ) {
					continue;
				}

				$key = $candidate['entity_id'] . '|' . $candidate['status'];
				$candidates[ $key ] = $candidate;
			}
		}

		$active_ids  = array();
		$retired_ids = array();
		foreach ( $candidates as $candidate ) {
			if ( 'active' === $candidate['status'] ) {
				$active_ids[ $candidate['entity_id'] ] = true;
			} elseif ( 'retired' === $candidate['status'] ) {
				$retired_ids[ $candidate['entity_id'] ] = true;
			}
		}

		if ( 1 === count( $active_ids ) ) {
			$entity = Repository::entity( array_key_first( $active_ids ) );
			return self::result( self::STATUS_RESOLVED, $query, $normalized, $entity, array_keys( $active_ids ) );
		}

		if ( 1 < count( $active_ids ) ) {
			return self::result( self::STATUS_AMBIGUOUS, $query, $normalized, null, array_keys( $active_ids ) );
		}

		if ( array() !== $retired_ids ) {
			return self::result( self::STATUS_RETIRED, $query, $normalized, null, array_keys( $retired_ids ) );
		}

		return self::result( self::STATUS_NOT_FOUND, $query, $normalized );
	}

	/**
	 * Normalize only exact reviewed aliases. No fuzzy edits are made.
	 *
	 * @param string $value Raw identity.
	 * @return string
	 */
	public static function normalize( $value ) {
		$value = trim( (string) $value );

		if ( class_exists( '\\Normalizer' ) ) {
			$normalized = \Normalizer::normalize( $value, \Normalizer::FORM_KC );
			if ( false !== $normalized ) {
				$value = $normalized;
			}
		}

		$value = str_replace(
			array( "\xE2\x80\x98", "\xE2\x80\x99", "\xD7\xB3" ),
			"'",
			$value
		);
		$value = str_replace(
			array( "\xE2\x80\x9C", "\xE2\x80\x9D", "\xD7\xB4" ),
			'"',
			$value
		);
		$value = strtolower( $value );
		$value = preg_replace( '/\s+/u', ' ', $value );

		return null === $value ? '' : trim( $value );
	}

	/**
	 * @param array       $entity Entity to inspect.
	 * @param string|null $type Optional type filter.
	 * @return bool
	 */
	private static function matches_type( $entity, $type ) {
		return null === $type || ( isset( $entity['type'] ) && $type === $entity['type'] );
	}

	/**
	 * @param array  $entity Resolved entity.
	 * @param string $query Original query.
	 * @param string $normalized Normalized query.
	 * @return array
	 */
	private static function entity_result( $entity, $query, $normalized ) {
		$status = 'retired' === $entity['status'] ? self::STATUS_RETIRED : self::STATUS_RESOLVED;

		return self::result( $status, $query, $normalized, $entity, array( $entity['id'] ) );
	}

	/**
	 * Return one stable resolver result shape.
	 *
	 * @param string     $status Resolution status.
	 * @param string     $query Original query.
	 * @param string     $normalized Normalized query.
	 * @param array|null $entity Resolved entity.
	 * @param array      $candidates Candidate IDs.
	 * @return array
	 */
	private static function result( $status, $query, $normalized, $entity = null, $candidates = array() ) {
		return array(
			'status'     => $status,
			'query'      => $query,
			'normalized' => $normalized,
			'entity'     => $entity,
			'candidates' => array_values( array_unique( $candidates ) ),
		);
	}
}
