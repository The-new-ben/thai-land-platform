<?php
/**
 * Immutable content registry access.
 *
 * @package Thailand_Platform
 */

namespace Thailand_Platform\Content;

final class Repository {
	/**
	 * @var array|null
	 */
	private static $registry = null;

	/**
	 * Load the generated runtime registry once.
	 *
	 * @return array
	 */
	public static function all() {
		if ( null !== self::$registry ) {
			return self::$registry;
		}

		$path = THAILAND_PLATFORM_DIR . 'resources/content/real-estate.php';
		if ( ! is_readable( $path ) || 0 === filesize( $path ) ) {
			self::$registry = array();
			return self::$registry;
		}

		$registry = require $path;
		if (
			! is_array( $registry )
			|| 'thailand-real-estate-v1' !== ( $registry['contract_id'] ?? '' )
			|| 8 !== count( $registry['routes_by_id'] ?? array() )
		) {
			self::$registry = array();
			return self::$registry;
		}

		self::$registry = $registry;
		return self::$registry;
	}

	/**
	 * @return bool
	 */
	public static function ready() {
		return array() !== self::all();
	}

	/**
	 * @param int $post_id WordPress post ID.
	 * @return array|null
	 */
	public static function route_by_post_id( $post_id ) {
		$registry = self::all();
		$route_id = $registry['route_id_by_post_id'][ (string) absint( $post_id ) ] ?? '';

		return '' !== $route_id ? ( $registry['routes_by_id'][ $route_id ] ?? null ) : null;
	}

	/**
	 * @param string $route_id Stable route identifier.
	 * @return array|null
	 */
	public static function route_by_id( $route_id ) {
		$registry = self::all();
		return $registry['routes_by_id'][ (string) $route_id ] ?? null;
	}

	/**
	 * @param string $seo_owner_id Canonical SEO owner identifier.
	 * @return array|null
	 */
	public static function route_by_seo_owner_id( $seo_owner_id ) {
		$registry = self::all();
		$route_id = $registry['route_id_by_seo_owner_id'][ (string) $seo_owner_id ] ?? '';

		return '' !== $route_id ? ( $registry['routes_by_id'][ $route_id ] ?? null ) : null;
	}

	/**
	 * @return array
	 */
	public static function labels() {
		$registry = self::all();
		return $registry['public_labels'] ?? array();
	}

	/**
	 * @param string $freshness_id Freshness catalog identifier.
	 * @return array|null
	 */
	public static function freshness( $freshness_id ) {
		$registry = self::all();
		return $registry['freshness_by_id'][ (string) $freshness_id ] ?? null;
	}

	/**
	 * @param string $source_id Source catalog identifier.
	 * @return array|null
	 */
	public static function source( $source_id ) {
		$registry = self::all();
		return $registry['sources_by_id'][ (string) $source_id ] ?? null;
	}

	/**
	 * @return array
	 */
	public static function hub_experience() {
		$registry = self::all();
		return $registry['hub_experience'] ?? array();
	}

	/**
	 * Reset only for dependency-free tests.
	 *
	 * @return void
	 */
	public static function reset_for_tests() {
		self::$registry = null;
	}
}
