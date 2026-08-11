<?php
/**
 * Small strict JSON reader for generated runtime manifests.
 *
 * @package Thailand_Platform
 */

namespace Thailand_Platform\DigitalIslands;

use RuntimeException;

final class StrictJson {
	const MAX_BYTES = 131072;
	const MAX_DEPTH = 64;

	/**
	 * Decode one bounded JSON object and reject duplicate object keys.
	 *
	 * @param string $json Raw JSON document.
	 * @return array
	 */
	public static function decode_object( $json ) {
		if ( ! is_string( $json ) || '' === $json || self::MAX_BYTES < strlen( $json ) ) {
			throw new RuntimeException( 'The Digital Islands manifest size is invalid.' );
		}

		$decoded = json_decode( $json, true, self::MAX_DEPTH, JSON_BIGINT_AS_STRING );
		if ( JSON_ERROR_NONE !== json_last_error() || ! is_array( $decoded ) || array() === $decoded ) {
			throw new RuntimeException( 'The Digital Islands manifest is not valid JSON.' );
		}

		self::assert_no_duplicate_object_keys( $json );

		return $decoded;
	}

	/**
	 * Scan only structural JSON tokens. Full syntax validation is handled above.
	 *
	 * @param string $json Valid JSON document.
	 * @return void
	 */
	private static function assert_no_duplicate_object_keys( $json ) {
		$length = strlen( $json );
		$stack  = array();

		for ( $index = 0; $index < $length; ++$index ) {
			$character = $json[ $index ];

			if ( '{' === $character ) {
				$stack[] = array(
					'type' => 'object',
					'keys' => array(),
				);
				continue;
			}

			if ( '[' === $character ) {
				$stack[] = array(
					'type' => 'array',
					'keys' => array(),
				);
				continue;
			}

			if ( '}' === $character || ']' === $character ) {
				array_pop( $stack );
				continue;
			}

			if ( '"' !== $character ) {
				continue;
			}

			$start = $index;
			for ( ++$index; $index < $length; ++$index ) {
				if ( '\\' === $json[ $index ] ) {
					++$index;
					continue;
				}
				if ( '"' === $json[ $index ] ) {
					break;
				}
			}

			$cursor = $index + 1;
			while ( $cursor < $length && false !== strpos( " \t\r\n", $json[ $cursor ] ) ) {
				++$cursor;
			}

			$frame_index = count( $stack ) - 1;
			if (
				0 > $frame_index
				|| ':' !== ( $json[ $cursor ] ?? '' )
				|| 'object' !== $stack[ $frame_index ]['type']
			) {
				continue;
			}

			$key_token = substr( $json, $start, $index - $start + 1 );
			$key       = json_decode( $key_token, true, self::MAX_DEPTH );
			if ( ! is_string( $key ) ) {
				throw new RuntimeException( 'The Digital Islands manifest contains an invalid object key.' );
			}

			if ( array_key_exists( $key, $stack[ $frame_index ]['keys'] ) ) {
				throw new RuntimeException( 'The Digital Islands manifest contains a duplicate object key.' );
			}
			$stack[ $frame_index ]['keys'][ $key ] = true;
		}
	}
}
