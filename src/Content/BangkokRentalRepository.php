<?php
/**
 * Immutable Bangkok rental area registry access.
 *
 * @package Thailand_Platform
 */

namespace Thailand_Platform\Content;

final class BangkokRentalRepository {
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

		$path = THAILAND_PLATFORM_DIR . 'resources/content/bangkok-rental-areas.php';
		if ( ! is_readable( $path ) || 0 === filesize( $path ) ) {
			self::$registry = array();
			return self::$registry;
		}

		$registry = require $path;
		if (
			! is_array( $registry )
			|| 'bangkok-rental-areas-v1' !== ( $registry['contract_id'] ?? '' )
			|| 50 !== count( $registry['districts_by_id'] ?? array() )
			|| 5 !== count( $registry['corridors_by_id'] ?? array() )
			|| 10 !== count( $registry['areas_by_id'] ?? array() )
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
	 * @return array
	 */
	public static function areas() {
		return self::ordered( 'area_order', 'areas_by_id' );
	}

	/**
	 * @return array
	 */
	public static function districts() {
		return self::ordered( 'district_order', 'districts_by_id' );
	}

	/**
	 * @return array
	 */
	public static function stations() {
		return self::ordered( 'station_order', 'stations_by_id' );
	}

	/**
	 * @return array
	 */
	public static function facts() {
		return self::ordered( 'fact_order', 'facts_by_id' );
	}

	/**
	 * @return array
	 */
	public static function sources() {
		return self::ordered( 'source_order', 'sources_by_id' );
	}

	/**
	 * @param string $district_id Stable official district identifier.
	 * @return array|null
	 */
	public static function district( $district_id ) {
		$registry = self::all();
		return $registry['districts_by_id'][ (string) $district_id ] ?? null;
	}

	/**
	 * @param string $station_id Stable station identifier.
	 * @return array|null
	 */
	public static function station( $station_id ) {
		$registry = self::all();
		return $registry['stations_by_id'][ (string) $station_id ] ?? null;
	}

	/**
	 * @param string $corridor_id Stable editorial corridor identifier.
	 * @return array|null
	 */
	public static function corridor( $corridor_id ) {
		$registry = self::all();
		return $registry['corridors_by_id'][ (string) $corridor_id ] ?? null;
	}

	/**
	 * @param string $ids_key Ordered identifier list key.
	 * @param string $items_key Indexed item map key.
	 * @return array
	 */
	private static function ordered( $ids_key, $items_key ) {
		$registry = self::all();
		$items    = array();
		foreach ( $registry[ $ids_key ] ?? array() as $item_id ) {
			if ( isset( $registry[ $items_key ][ $item_id ] ) ) {
				$items[] = $registry[ $items_key ][ $item_id ];
			}
		}
		return $items;
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
