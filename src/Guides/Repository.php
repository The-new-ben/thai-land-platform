<?php
/**
 * Immutable priority guides registry access.
 *
 * @package Thailand_Platform
 */

namespace Thailand_Platform\Guides;

final class Repository {
	/**
	 * @var array|null
	 */
	private static $registry = null;

	/**
	 * @return array
	 */
	public static function all() {
		if ( null !== self::$registry ) {
			return self::$registry;
		}

		$path = THAILAND_PLATFORM_DIR . 'resources/content/priority-guides.php';
		if ( ! is_readable( $path ) || 0 === filesize( $path ) ) {
			self::$registry = array();
			return self::$registry;
		}

		$registry = require $path;
		$assets   = is_array( $registry ) ? ( $registry['asset_contract'] ?? array() ) : array();
		if (
			! is_array( $registry )
			|| 'thailand-priority-guides-v1' !== ( $registry['contract_id'] ?? '' )
			|| 7 !== count( $registry['routes_by_id'] ?? array() )
			|| array( 720, 1200, 1717 ) !== ( $assets['widths'] ?? array() )
			|| array( 'visas-entry-thailand-v1', 'cannabis-law-thailand-v1' ) !== ( $assets['allowed_asset_keys'] ?? array() )
			|| 'all_variants_required' !== ( $assets['readiness'] ?? '' )
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
	 * @param string $path Exact site-relative path.
	 * @return array|null
	 */
	public static function route_by_path( $path ) {
		$registry = self::all();
		$route_id = $registry['route_id_by_path'][ (string) $path ] ?? '';
		return '' !== $route_id ? ( $registry['routes_by_id'][ $route_id ] ?? null ) : null;
	}

	/**
	 * @param string $parent_id Parent owner identifier.
	 * @return array
	 */
	public static function children( $parent_id ) {
		$registry = self::all();
		return $registry['children_by_parent'][ (string) $parent_id ] ?? array();
	}

	/**
	 * @param string $source_id Official source identifier.
	 * @return array|null
	 */
	public static function source( $source_id ) {
		$registry = self::all();
		return $registry['sources_by_id'][ (string) $source_id ] ?? null;
	}

	/**
	 * @return array
	 */
	public static function asset_contract() {
		$registry = self::all();
		return $registry['asset_contract'] ?? array();
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
