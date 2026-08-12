<?php
/**
 * Exact self-hosted renderer asset contract for Digital Islands.
 *
 * @package Thailand_Platform
 */

namespace Thailand_Platform\DigitalIslands;

use RuntimeException;

final class RendererAssets {
	const CONTRACT_ID  = 'thailand-digital-islands-renderer-v1';
	const MANIFEST_PATH = 'resources/digital-islands/renderer-manifest.json';
	const MANIFEST_SHA256 = 'bf24b0b134e8c6abd3e38d1f7c2b712f7057d636950accdf61f1fe9eed864bb3';
	const MANIFEST_MAX_BYTES = 65536;
	const ISLAND_ID = 'geo:th:island:ko-pha-ngan';
	const RELEASE_VERSION = '0.5.2';
	const MAPLIBRE_VERSION = '5.18.0';
	const PMTILES_VERSION = '4.5.0';
	const MAPLIBRE_SCRIPT_PATH = 'assets/digital-islands/vendor/maplibre-gl/5.18.0/maplibre-gl.js';
	const MAPLIBRE_STYLE_PATH = 'assets/digital-islands/vendor/maplibre-gl/5.18.0/maplibre-gl.css';
	const MAPLIBRE_LICENSE_PATH = 'assets/digital-islands/vendor/maplibre-gl/5.18.0/maplibre-gl.LICENSE.txt';
	const PMTILES_SCRIPT_PATH = 'assets/digital-islands/vendor/pmtiles/4.5.0/pmtiles.js';
	const PMTILES_LICENSE_PATH = 'assets/digital-islands/vendor/pmtiles/4.5.0/pmtiles.LICENSE.txt';
	const BASEMAP_PATH = 'assets/digital-islands/data/koh-phangan-basemap-20260811.pmtiles';
	const SATELLITE_PATH = 'assets/digital-islands/imagery/koh-phangan-sentinel2-20260326.webp';
	const SATELLITE_BYTES = 621958;
	const SATELLITE_SHA256 = '9ee99de2269a040c35be113bad44d444fc76c4dc136b36d4afe5cb57b5e3de2a';
	const SATELLITE_ATTRIBUTION = 'Contains modified Copernicus Sentinel data 2026';
	const SATELLITE_OBSERVED_AT = '2026-03-26T03:55:36.171000Z';
	const SATELLITE_SOURCE_ITEM_ID = 'S2B_47PPL_20260326_0_L2A';
	const SATELLITE_WIDTH = 2227;
	const SATELLITE_HEIGHT = 2372;
	const TERRAIN_BASE_PATH = 'assets/digital-islands/terrain/20260811';
	const TERRAIN_URL_TEMPLATE = 'assets/digital-islands/terrain/20260811/{z}/{x}/{y}.png';
	const TERRAIN_MIN_ZOOM = 8;
	const TERRAIN_MAX_ZOOM = 13;
	const TERRAIN_TILE_COUNT = 58;
	const VALIDATION_CACHE_TTL = 604800;

	const BOUNDS = array(
		'east'  => 100.12,
		'north' => 9.84,
		'south' => 9.63,
		'west'  => 99.92,
	);

	const TERRAIN_TILE_RANGES = array(
		'8'  => array( 'count' => 2, 'max_x' => 199, 'max_y' => 121, 'min_x' => 199, 'min_y' => 120 ),
		'9'  => array( 'count' => 2, 'max_x' => 398, 'max_y' => 242, 'min_x' => 398, 'min_y' => 241 ),
		'10' => array( 'count' => 2, 'max_x' => 796, 'max_y' => 484, 'min_x' => 796, 'min_y' => 483 ),
		'11' => array( 'count' => 4, 'max_x' => 1593, 'max_y' => 968, 'min_x' => 1592, 'min_y' => 967 ),
		'12' => array( 'count' => 12, 'max_x' => 3187, 'max_y' => 1937, 'min_x' => 3184, 'min_y' => 1935 ),
		'13' => array( 'count' => 36, 'max_x' => 6374, 'max_y' => 3875, 'min_x' => 6369, 'min_y' => 3870 ),
	);

	/** @var array|null */
	private static $manifest = null;

	/**
	 * Verify the strict manifest and every immutable local file receipt.
	 *
	 * @return array
	 */
	public static function verify() {
		if ( null !== self::$manifest ) {
			return self::$manifest;
		}

		if ( ! defined( 'THAILAND_PLATFORM_DIR' ) ) {
			throw new RuntimeException( 'The Thailand Platform root is unavailable.' );
		}

		self::$manifest = self::verify_at_root( THAILAND_PLATFORM_DIR, true );
		return self::$manifest;
	}

	/** @return bool */
	public static function ready() {
		try {
			self::verify();
			return true;
		} catch ( \Throwable $exception ) {
			unset( $exception );
			return false;
		}
	}

	/**
	 * Return only reviewed data and same-origin local asset URLs.
	 *
	 * @return array
	 */
	public static function runtime_config() {
		$manifest = self::verify();
		$basemap  = $manifest['basemap'];
		$satellite = $manifest['satellite'];
		$terrain  = $manifest['terrain'];

		return array(
			'reviewed'    => true,
			'contractId'  => self::CONTRACT_ID,
			'islandGeoId' => self::ISLAND_ID,
			'maplibre'    => array(
				'basemapAttribution'     => $manifest['attribution']['basemap'],
				'basemapSha256'          => $manifest['inventory'][ $basemap['path'] ]['sha256'],
				'bounds'                  => $basemap['bounds'],
				'satelliteAttribution'    => $satellite['attribution'],
				'satelliteBounds'         => $satellite['bounds'],
				'satelliteObservedAt'     => $satellite['observed_at'],
				'satelliteUrl'            => self::same_origin_asset_url( $satellite['path'] ),
				'terrainAttribution'      => $manifest['attribution']['terrain'],
				'terrainManifestSha256'   => $terrain['inventory_sha256'],
				'terrainMaxZoom'          => $terrain['max_zoom'],
				'terrainMinZoom'          => $terrain['min_zoom'],
				'terrainUrlTemplate'      => self::same_origin_asset_url( $terrain['url_template'] ),
				'vectorPmtilesUrl'        => self::same_origin_asset_url( $basemap['path'] ),
			),
		);
	}

	/** @return void */
	public static function reset_for_tests() {
		self::$manifest = null;
	}

	/**
	 * @param string $root      Candidate plugin root.
	 * @param bool   $use_cache Whether a persistent receipt may avoid content hashing.
	 * @return array
	 */
	private static function verify_at_root( $root, $use_cache ) {
		$root          = self::normalized_root( $root );
		$manifest_path = self::absolute_file_path( $root, self::MANIFEST_PATH );
		self::assert_regular_file( $root, self::MANIFEST_PATH, self::MANIFEST_MAX_BYTES );

		$raw = file_get_contents( $manifest_path );
		if ( false === $raw ) {
			throw new RuntimeException( 'The renderer asset manifest cannot be read.' );
		}
		$manifest_sha256 = hash( 'sha256', $raw );
		if ( $use_cache && ! hash_equals( self::MANIFEST_SHA256, $manifest_sha256 ) ) {
			throw new RuntimeException( 'The renderer asset manifest is not the reviewed release object.' );
		}

		$manifest = StrictJson::decode_object( $raw );
		self::validate_manifest( $manifest );
		self::assert_filesystem_inventory( $root, array_keys( $manifest['inventory'] ) );

		$stats           = array();
		foreach ( $manifest['inventory'] as $relative_path => $receipt ) {
			$stats[ $relative_path ] = self::asset_stat( $root, $relative_path, $receipt );
		}

		$stat_sha256 = hash( 'sha256', self::canonical_stats( $stats ) );
		if ( $use_cache && self::cached_receipt_is_valid( $manifest_sha256, $stat_sha256 ) ) {
			return $manifest;
		}

		foreach ( $manifest['inventory'] as $relative_path => $receipt ) {
			self::assert_asset_receipt( $root, $relative_path, $receipt );
		}

		if ( $use_cache ) {
			self::cache_valid_receipt( $manifest_sha256, $stat_sha256 );
		}

		return $manifest;
	}

	/**
	 * @param array $manifest Candidate renderer manifest.
	 * @return void
	 */
	private static function validate_manifest( $manifest ) {
		self::assert_exact_keys(
			$manifest,
			array( 'attribution', 'basemap', 'contract_id', 'dependencies', 'inventory', 'island_id', 'release_version', 'satellite', 'schema_version', 'terrain' ),
			'manifest'
		);

		if (
			self::CONTRACT_ID !== ( $manifest['contract_id'] ?? null )
			|| 1 !== ( $manifest['schema_version'] ?? null )
			|| self::ISLAND_ID !== ( $manifest['island_id'] ?? null )
			|| self::RELEASE_VERSION !== ( $manifest['release_version'] ?? null )
			|| ( defined( 'THAILAND_PLATFORM_VERSION' ) && THAILAND_PLATFORM_VERSION !== $manifest['release_version'] )
		) {
			throw new RuntimeException( 'The renderer asset manifest identity is invalid.' );
		}

		self::assert_attribution( $manifest['attribution'] ?? null );
		self::assert_dependencies( $manifest['dependencies'] ?? null );
		self::assert_basemap( $manifest['basemap'] ?? null );
		self::assert_satellite( $manifest['satellite'] ?? null );
		self::assert_terrain( $manifest['terrain'] ?? null );
		self::assert_inventory( $manifest['inventory'] ?? null, $manifest );
	}

	/** @param mixed $attribution Candidate attribution. @return void */
	private static function assert_attribution( $attribution ) {
		self::assert_exact_keys( $attribution, array( 'basemap', 'terrain' ), 'attribution' );
		if (
			'Protomaps © OpenStreetMap contributors' !== ( $attribution['basemap'] ?? null )
			|| 'Mapzen Terrain Tiles; SRTM and GMTED2010 data courtesy of the U.S. Geological Survey; ETOPO1 courtesy of NOAA/NCEI. Not for navigation.' !== ( $attribution['terrain'] ?? null )
		) {
			throw new RuntimeException( 'The renderer attribution is invalid.' );
		}
	}

	/** @param mixed $dependencies Candidate dependency inventory. @return void */
	private static function assert_dependencies( $dependencies ) {
		self::assert_exact_keys( $dependencies, array( 'maplibre', 'pmtiles' ), 'dependencies' );
		$maplibre = $dependencies['maplibre'] ?? null;
		$pmtiles  = $dependencies['pmtiles'] ?? null;
		self::assert_exact_keys( $maplibre, array( 'license_path', 'script_path', 'style_path', 'version' ), 'MapLibre dependency' );
		self::assert_exact_keys( $pmtiles, array( 'license_path', 'script_path', 'version' ), 'PMTiles dependency' );

		if (
			self::MAPLIBRE_VERSION !== ( $maplibre['version'] ?? null )
			|| self::MAPLIBRE_SCRIPT_PATH !== ( $maplibre['script_path'] ?? null )
			|| self::MAPLIBRE_STYLE_PATH !== ( $maplibre['style_path'] ?? null )
			|| self::MAPLIBRE_LICENSE_PATH !== ( $maplibre['license_path'] ?? null )
			|| self::PMTILES_VERSION !== ( $pmtiles['version'] ?? null )
			|| self::PMTILES_SCRIPT_PATH !== ( $pmtiles['script_path'] ?? null )
			|| self::PMTILES_LICENSE_PATH !== ( $pmtiles['license_path'] ?? null )
		) {
			throw new RuntimeException( 'A renderer dependency identity is invalid.' );
		}
	}

	/** @param mixed $basemap Candidate basemap contract. @return void */
	private static function assert_basemap( $basemap ) {
		self::assert_exact_keys( $basemap, array( 'bounds', 'format', 'path' ), 'basemap' );
		if (
			'pmtiles' !== ( $basemap['format'] ?? null )
			|| self::BASEMAP_PATH !== ( $basemap['path'] ?? null )
			|| self::BOUNDS !== ( $basemap['bounds'] ?? null )
		) {
			throw new RuntimeException( 'The renderer basemap contract is invalid.' );
		}
	}

	/** @param mixed $satellite Candidate satellite-image contract. @return void */
	private static function assert_satellite( $satellite ) {
		self::assert_exact_keys(
			$satellite,
			array( 'attribution', 'bounds', 'format', 'height', 'observed_at', 'path', 'projection', 'source_item_id', 'width' ),
			'satellite image'
		);
		if (
			self::SATELLITE_PATH !== ( $satellite['path'] ?? null )
			|| 'webp' !== ( $satellite['format'] ?? null )
			|| 'EPSG:3857' !== ( $satellite['projection'] ?? null )
			|| self::SATELLITE_SOURCE_ITEM_ID !== ( $satellite['source_item_id'] ?? null )
			|| self::SATELLITE_OBSERVED_AT !== ( $satellite['observed_at'] ?? null )
			|| self::SATELLITE_ATTRIBUTION !== ( $satellite['attribution'] ?? null )
			|| self::SATELLITE_WIDTH !== ( $satellite['width'] ?? null )
			|| self::SATELLITE_HEIGHT !== ( $satellite['height'] ?? null )
			|| self::BOUNDS !== ( $satellite['bounds'] ?? null )
		) {
			throw new RuntimeException( 'The renderer satellite-image contract is invalid.' );
		}
	}

	/** @param mixed $terrain Candidate terrain contract. @return void */
	private static function assert_terrain( $terrain ) {
		self::assert_exact_keys(
			$terrain,
			array( 'base_path', 'bounds', 'format', 'inventory_sha256', 'max_zoom', 'min_zoom', 'tile_count', 'tile_ranges', 'tiles', 'total_bytes', 'url_template' ),
			'terrain'
		);

		if (
			self::TERRAIN_BASE_PATH !== ( $terrain['base_path'] ?? null )
			|| self::TERRAIN_URL_TEMPLATE !== ( $terrain['url_template'] ?? null )
			|| 'terrarium_png' !== ( $terrain['format'] ?? null )
			|| self::TERRAIN_MIN_ZOOM !== ( $terrain['min_zoom'] ?? null )
			|| self::TERRAIN_MAX_ZOOM !== ( $terrain['max_zoom'] ?? null )
			|| self::TERRAIN_TILE_COUNT !== ( $terrain['tile_count'] ?? null )
			|| self::BOUNDS !== ( $terrain['bounds'] ?? null )
			|| self::TERRAIN_TILE_RANGES !== ( $terrain['tile_ranges'] ?? null )
			|| ! self::valid_digest( $terrain['inventory_sha256'] ?? null )
			|| ! is_int( $terrain['total_bytes'] ?? null )
			|| 0 >= $terrain['total_bytes']
		) {
			throw new RuntimeException( 'The renderer terrain contract is invalid.' );
		}

		$expected_tiles = self::expected_terrain_tiles();
		if ( $expected_tiles !== ( $terrain['tiles'] ?? null ) ) {
			throw new RuntimeException( 'The renderer terrain tile boundary is invalid.' );
		}
	}

	/**
	 * @param mixed $inventory Candidate exact file receipts.
	 * @param array $manifest  Validated manifest contract.
	 * @return void
	 */
	private static function assert_inventory( $inventory, $manifest ) {
		if ( ! is_array( $inventory ) ) {
			throw new RuntimeException( 'The renderer asset inventory is invalid.' );
		}

		$expected_paths = array_merge(
			array(
				self::MAPLIBRE_SCRIPT_PATH,
				self::MAPLIBRE_STYLE_PATH,
				self::MAPLIBRE_LICENSE_PATH,
				self::PMTILES_SCRIPT_PATH,
				self::PMTILES_LICENSE_PATH,
				self::BASEMAP_PATH,
				self::SATELLITE_PATH,
			),
			self::expected_terrain_tiles()
		);
		sort( $expected_paths, SORT_STRING );
		$actual_paths = array_keys( $inventory );
		sort( $actual_paths, SORT_STRING );
		if ( $expected_paths !== $actual_paths ) {
			throw new RuntimeException( 'The renderer asset inventory boundary is invalid.' );
		}

		foreach ( $inventory as $relative_path => $receipt ) {
			self::assert_safe_relative_path( $relative_path );
			self::assert_exact_keys( $receipt, array( 'bytes', 'sha256' ), 'file receipt' );
			if (
				! is_int( $receipt['bytes'] ?? null )
				|| 0 >= $receipt['bytes']
				|| ! self::valid_digest( $receipt['sha256'] ?? null )
			) {
				throw new RuntimeException( 'A renderer file receipt is invalid.' );
			}
		}
		if (
			self::SATELLITE_BYTES !== $inventory[ self::SATELLITE_PATH ]['bytes']
			|| self::SATELLITE_SHA256 !== $inventory[ self::SATELLITE_PATH ]['sha256']
		) {
			throw new RuntimeException( 'The renderer satellite-image receipt is invalid.' );
		}

		$terrain_receipts = array();
		$total_bytes      = 0;
		foreach ( $manifest['terrain']['tiles'] as $tile_path ) {
			$terrain_receipts[ $tile_path ] = $inventory[ $tile_path ];
			$total_bytes += $inventory[ $tile_path ]['bytes'];
		}

		if (
			$manifest['terrain']['total_bytes'] !== $total_bytes
			|| $manifest['terrain']['inventory_sha256'] !== self::terrain_inventory_digest( $terrain_receipts )
		) {
			throw new RuntimeException( 'The renderer terrain receipt is inconsistent.' );
		}
	}

	/** @return string[] */
	private static function expected_terrain_tiles() {
		$tiles = array();
		foreach ( self::TERRAIN_TILE_RANGES as $zoom => $range ) {
			for ( $x = $range['min_x']; $x <= $range['max_x']; ++$x ) {
				for ( $y = $range['min_y']; $y <= $range['max_y']; ++$y ) {
					$tiles[] = self::TERRAIN_BASE_PATH . '/' . $zoom . '/' . $x . '/' . $y . '.png';
				}
			}
		}
		return $tiles;
	}

	/** @param array $receipts Sorted terrain receipts. @return string */
	private static function terrain_inventory_digest( $receipts ) {
		ksort( $receipts, SORT_STRING );
		$canonical = '';
		foreach ( $receipts as $path => $receipt ) {
			$canonical .= $path . "\0" . $receipt['bytes'] . "\0" . $receipt['sha256'] . "\n";
		}
		return hash( 'sha256', $canonical );
	}

	/**
	 * @param string $root          Normalized root.
	 * @param string $relative_path Allowlisted relative path.
	 * @param array  $receipt       Expected byte receipt.
	 * @return array
	 */
	private static function asset_stat( $root, $relative_path, $receipt ) {
		$path = self::assert_regular_file( $root, $relative_path, null );
		clearstatcache( true, $path );
		$bytes = filesize( $path );
		$mtime = filemtime( $path );
		$ctime = filectime( $path );
		if ( $receipt['bytes'] !== $bytes || false === $mtime || false === $ctime ) {
			throw new RuntimeException( 'A renderer asset byte receipt is invalid.' );
		}
		return array( 'bytes' => $bytes, 'ctime' => $ctime, 'mtime' => $mtime );
	}

	/**
	 * @param string $root          Normalized root.
	 * @param string $relative_path Allowlisted relative path.
	 * @param array  $receipt       Expected content receipt.
	 * @return void
	 */
	private static function assert_asset_receipt( $root, $relative_path, $receipt ) {
		$path   = self::assert_regular_file( $root, $relative_path, null );
		$digest = hash_file( 'sha256', $path );
		if ( ! is_string( $digest ) || ! hash_equals( $receipt['sha256'], strtolower( $digest ) ) ) {
			throw new RuntimeException( 'A renderer asset does not match its manifest.' );
		}
	}

	/**
	 * @param string   $root          Normalized root.
	 * @param string   $relative_path Safe plugin-relative path.
	 * @param int|null $max_bytes     Optional upper size bound.
	 * @return string
	 */
	private static function assert_regular_file( $root, $relative_path, $max_bytes ) {
		self::assert_safe_relative_path( $relative_path );
		$cursor = $root;
		foreach ( explode( '/', $relative_path ) as $component ) {
			$cursor .= '/' . $component;
			if ( is_link( $cursor ) ) {
				throw new RuntimeException( 'A renderer asset path contains a symbolic link.' );
			}
		}

		$path = self::absolute_file_path( $root, $relative_path );
		clearstatcache( true, $path );
		$size = is_file( $path ) && is_readable( $path ) && ! is_link( $path ) ? filesize( $path ) : false;
		if ( false === $size || 0 >= $size || ( null !== $max_bytes && $max_bytes < $size ) ) {
			throw new RuntimeException( 'A required renderer asset is unavailable.' );
		}
		return $path;
	}

	/**
	 * Reject unmanifested files and symbolic links in the bounded renderer trees.
	 *
	 * @param string   $root           Normalized plugin root.
	 * @param string[] $expected_files Exact manifest inventory.
	 * @return void
	 */
	private static function assert_filesystem_inventory( $root, $expected_files ) {
		$actual_files = array();
		foreach (
			array(
				'assets/digital-islands/data',
				'assets/digital-islands/imagery',
				'assets/digital-islands/terrain/20260811',
				'assets/digital-islands/vendor/maplibre-gl/5.18.0',
				'assets/digital-islands/vendor/pmtiles/4.5.0',
			) as $relative_directory
		) {
			self::collect_inventory_files( $root, $relative_directory, $actual_files );
		}

		sort( $actual_files, SORT_STRING );
		sort( $expected_files, SORT_STRING );
		if ( $actual_files !== $expected_files ) {
			throw new RuntimeException( 'The renderer filesystem inventory disagrees with its manifest.' );
		}
	}

	/**
	 * @param string   $root               Normalized plugin root.
	 * @param string   $relative_directory Reviewed directory.
	 * @param string[] $files              Collected relative files.
	 * @return void
	 */
	private static function collect_inventory_files( $root, $relative_directory, &$files ) {
		self::assert_safe_relative_path( $relative_directory );
		$directory = $root . '/' . $relative_directory;
		if ( ! is_dir( $directory ) || ! is_readable( $directory ) || is_link( $directory ) ) {
			throw new RuntimeException( 'A renderer inventory directory is unavailable.' );
		}

		$entries = scandir( $directory );
		if ( false === $entries ) {
			throw new RuntimeException( 'A renderer inventory directory cannot be read.' );
		}
		foreach ( $entries as $entry ) {
			if ( '.' === $entry || '..' === $entry ) {
				continue;
			}
			$relative_path = $relative_directory . '/' . $entry;
			self::assert_safe_relative_path( $relative_path );
			$path = $root . '/' . $relative_path;
			if ( is_link( $path ) ) {
				throw new RuntimeException( 'A renderer inventory path contains a symbolic link.' );
			}
			if ( is_dir( $path ) ) {
				self::collect_inventory_files( $root, $relative_path, $files );
			} elseif ( is_file( $path ) && is_readable( $path ) ) {
				$files[] = $relative_path;
			} else {
				throw new RuntimeException( 'A renderer inventory entry is invalid.' );
			}
		}
	}

	/** @param string $path Candidate relative path. @return void */
	private static function assert_safe_relative_path( $path ) {
		if (
			! is_string( $path )
			|| 1 !== preg_match( '/\A[a-zA-Z0-9][a-zA-Z0-9._{}-]*(?:\/[a-zA-Z0-9][a-zA-Z0-9._{}-]*)+\z/D', $path )
			|| false !== strpos( $path, '\\' )
			|| false !== strpos( $path, '/./' )
			|| false !== strpos( $path, '../' )
			|| false !== strpos( $path, '/..' )
		) {
			throw new RuntimeException( 'A renderer asset path is unsafe.' );
		}
	}

	/** @param string $root Candidate root. @return string */
	private static function normalized_root( $root ) {
		$root = is_string( $root ) ? realpath( $root ) : false;
		if ( false === $root || ! is_dir( $root ) || is_link( $root ) ) {
			throw new RuntimeException( 'The renderer asset root is invalid.' );
		}
		return rtrim( str_replace( '\\', '/', $root ), '/' );
	}

	/** @param string $root Normalized root. @param string $relative_path Safe path. @return string */
	private static function absolute_file_path( $root, $relative_path ) {
		$path     = $root . '/' . $relative_path;
		$realpath = realpath( $path );
		$realpath = false === $realpath ? false : str_replace( '\\', '/', $realpath );
		if ( false === $realpath || 0 !== strpos( $realpath, $root . '/' ) ) {
			throw new RuntimeException( 'A renderer asset escaped the plugin root.' );
		}
		return $realpath;
	}

	/** @param array $stats Asset stat inventory. @return string */
	private static function canonical_stats( $stats ) {
		ksort( $stats, SORT_STRING );
		$canonical = '';
		foreach ( $stats as $path => $stat ) {
			$canonical .= $path . "\0" . $stat['bytes'] . "\0" . $stat['ctime'] . "\0" . $stat['mtime'] . "\n";
		}
		return $canonical;
	}

	/** @param string $manifest_sha256 Manifest digest. @param string $stat_sha256 Stat digest. @return bool */
	private static function cached_receipt_is_valid( $manifest_sha256, $stat_sha256 ) {
		if ( ! function_exists( 'get_transient' ) ) {
			return false;
		}
		$receipt = get_transient( self::cache_key( $manifest_sha256, $stat_sha256 ) );
		return is_array( $receipt )
			&& array( 'contract_id', 'manifest_sha256', 'stat_sha256', 'valid' ) === array_keys( $receipt )
			&& self::CONTRACT_ID === $receipt['contract_id']
			&& $manifest_sha256 === $receipt['manifest_sha256']
			&& $stat_sha256 === $receipt['stat_sha256']
			&& true === $receipt['valid'];
	}

	/** @param string $manifest_sha256 Manifest digest. @param string $stat_sha256 Stat digest. @return void */
	private static function cache_valid_receipt( $manifest_sha256, $stat_sha256 ) {
		if ( ! function_exists( 'set_transient' ) ) {
			return;
		}
		set_transient(
			self::cache_key( $manifest_sha256, $stat_sha256 ),
			array(
				'contract_id'      => self::CONTRACT_ID,
				'manifest_sha256'  => $manifest_sha256,
				'stat_sha256'      => $stat_sha256,
				'valid'            => true,
			),
			self::VALIDATION_CACHE_TTL
		);
	}

	/** @param string $manifest_sha256 Manifest digest. @param string $stat_sha256 Stat digest. @return string */
	private static function cache_key( $manifest_sha256, $stat_sha256 ) {
		return 'thp_di_renderer_' . substr( hash( 'sha256', $manifest_sha256 . "\0" . $stat_sha256 ), 0, 40 );
	}

	/** @param string $relative_path Reviewed local path. @return string */
	private static function same_origin_asset_url( $relative_path ) {
		if ( ! function_exists( 'plugins_url' ) || ! function_exists( 'home_url' ) || ! defined( 'THAILAND_PLATFORM_FILE' ) ) {
			throw new RuntimeException( 'The renderer asset URL boundary is unavailable.' );
		}

		$url  = plugins_url( $relative_path, THAILAND_PLATFORM_FILE );
		$home = home_url( '/' );
		if ( ! self::same_origin( $url, $home ) ) {
			throw new RuntimeException( 'A renderer runtime URL is not same-origin.' );
		}
		return $url;
	}

	/** @param mixed $url Candidate asset URL. @param mixed $home Site URL. @return bool */
	private static function same_origin( $url, $home ) {
		$url_parts  = is_string( $url ) ? parse_url( $url ) : false;
		$home_parts = is_string( $home ) ? parse_url( $home ) : false;
		if (
			! is_array( $url_parts )
			|| ! is_array( $home_parts )
			|| isset( $url_parts['user'] )
			|| isset( $url_parts['pass'] )
			|| isset( $url_parts['query'] )
			|| isset( $url_parts['fragment'] )
		) {
			return false;
		}
		return strtolower( (string) ( $url_parts['scheme'] ?? '' ) ) === strtolower( (string) ( $home_parts['scheme'] ?? '' ) )
			&& strtolower( (string) ( $url_parts['host'] ?? '' ) ) === strtolower( (string) ( $home_parts['host'] ?? '' ) )
			&& self::effective_port( $url_parts ) === self::effective_port( $home_parts );
	}

	/** @param array $parts Parsed URL. @return int */
	private static function effective_port( $parts ) {
		if ( isset( $parts['port'] ) ) {
			return (int) $parts['port'];
		}
		return 'https' === strtolower( (string) ( $parts['scheme'] ?? '' ) ) ? 443 : 80;
	}

	/** @param mixed $value Candidate SHA-256. @return bool */
	private static function valid_digest( $value ) {
		return is_string( $value ) && 1 === preg_match( '/\A[0-9a-f]{64}\z/D', $value );
	}

	/** @param mixed $value Candidate object. @param string[] $expected Exact keys. @param string $label Error label. @return void */
	private static function assert_exact_keys( $value, $expected, $label ) {
		if ( ! is_array( $value ) ) {
			throw new RuntimeException( 'The renderer ' . $label . ' is invalid.' );
		}
		$actual = array_keys( $value );
		sort( $actual, SORT_STRING );
		sort( $expected, SORT_STRING );
		if ( $actual !== $expected ) {
			throw new RuntimeException( 'The renderer ' . $label . ' shape is invalid.' );
		}
	}
}
