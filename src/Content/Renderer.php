<?php
/**
 * Full-document content renderer.
 *
 * @package Thailand_Platform
 */

namespace Thailand_Platform\Content;

final class Renderer {
	/**
	 * @var array
	 */
	private static $bodies = array();

	/**
	 * @return void
	 */
	public function register() {
		add_filter( 'template_include', array( $this, 'template' ), 98 );
	}

	/**
	 * @param string $template Original theme template.
	 * @return string
	 */
	public function template( $template ) {
		$route = Context::route();
		if ( ! is_array( $route ) || ! self::ready( $route ) ) {
			return $template;
		}

		return THAILAND_PLATFORM_DIR . 'templates/content-document.php';
	}

	/**
	 * @param array $route Current route.
	 * @return bool
	 */
	public static function ready( $route ) {
		$required = array(
			THAILAND_PLATFORM_DIR . 'templates/content-document.php',
			THAILAND_PLATFORM_DIR . 'templates/partials/content-header.php',
			THAILAND_PLATFORM_DIR . 'templates/partials/content-footer.php',
			THAILAND_PLATFORM_DIR . 'assets/content/content.css',
			THAILAND_PLATFORM_DIR . 'assets/content/content.js',
		);
		$required = array_merge( $required, Assets::hero_paths( $route['route_id'] ?? '' ) );
		if ( 'bangkok-apartment-rental' === ( $route['route_id'] ?? '' ) ) {
			$required[] = THAILAND_PLATFORM_DIR . 'assets/content/bangkok-rental.css';
			$required[] = THAILAND_PLATFORM_DIR . 'assets/content/bangkok-rental.js';
			$required[] = THAILAND_PLATFORM_DIR . 'resources/content/bangkok-rental-areas.php';
		}

		foreach ( $required as $path ) {
			if ( ! is_readable( $path ) || 0 === filesize( $path ) ) {
				return false;
			}
		}
		if (
			'bangkok-apartment-rental' === ( $route['route_id'] ?? '' )
			&& ! BangkokRentalRepository::ready()
		) {
			return false;
		}

		$body = self::post_body( $route );
		return '' !== trim( $body ) && ! preg_match( '/<(?:h1|main)\b/i', $body );
	}

	/**
	 * Preserve the stored WordPress body and run normal content filters once.
	 *
	 * @param array $route Current route.
	 * @return string
	 */
	public static function post_body( $route ) {
		$route_id = $route['route_id'] ?? '';
		if ( isset( self::$bodies[ $route_id ] ) ) {
			return self::$bodies[ $route_id ];
		}

		$post_id = absint( $route['wordpress']['post_id'] ?? 0 );
		$body    = get_post_field( 'post_content', $post_id );
		if ( ! is_string( $body ) ) {
			self::$bodies[ $route_id ] = '';
			return '';
		}

		$filtered   = (string) apply_filters( 'the_content', $body );
		$normalized = str_ireplace(
			array(
				"\xE2\x80\x93",
				"\xE2\x80\x94",
				'&ndash;',
				'&mdash;',
				'&#8211;',
				'&#8212;',
				'&#x2013;',
				'&#x2014;',
			),
			'-',
			$filtered
		);
		if ( 'bangkok-apartment-rental' === $route_id ) {
			$normalized = self::upgrade_bangkok_rental_body( $normalized );
		}
		self::$bodies[ $route_id ] = $normalized;
		return self::$bodies[ $route_id ];
	}

	/**
	 * Replace the old district dump with the structured area explorer and remove
	 * one obsolete tenant-law claim from rendered output. Stored WordPress content
	 * remains unchanged and every other useful section continues through the
	 * standard content filter.
	 *
	 * @param string $body Filtered WordPress body.
	 * @return string
	 */
	private static function upgrade_bangkok_rental_body( $body ) {
		$patterns = array(
			'~<p\b[^>]*>\s*<strong>\s*דירות\s+להשכרה\s+בב(?:נגק|נק)וק\s+לפי\s+מחוזות\s*</strong>\s*</p>.*?<p\b[^>]*>\s*<strong>\s*דירות\s+מומלצות\s*</strong>\s*</p>~isu',
			'~<p\b[^>]*>(?:(?!</p>).)*?בתאילנד\s+אין\s+חוק\s+שוכרי\s+בית(?:(?!</p>).)*?</p>~isu',
			'~<p\b[^>]*>\s*122\s*דירות\s+מומלצות\s*</p>~isu',
		);
		$upgraded = preg_replace( $patterns, '', $body );

		return is_string( $upgraded ) ? $upgraded : $body;
	}

	/**
	 * Reset only for dependency-free tests.
	 *
	 * @return void
	 */
	public static function reset_for_tests() {
		self::$bodies = array();
	}
}
