<?php
/**
 * Path resolver for data store references.
 *
 * Values starting with "/" are references to the data store:
 *   /step_id          → full result of step
 *   /step_id.field    → nested field access
 *   /step_id.0.title  → array index + field
 *   /step_id.length   → count of array result
 *
 * All other values pass through unchanged.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class A2E_Path_Resolver {

	/**
	 * Resolve a value — if it's a store reference, look it up.
	 */
	public static function resolve( mixed $value, A2E_Data_Store $store ): mixed {
		if ( ! is_string( $value ) ) {
			// Recursively resolve arrays/objects
			if ( is_array( $value ) ) {
				return self::resolve_array( $value, $store );
			}
			return $value;
		}

		if ( ! str_starts_with( $value, '/' ) ) {
			return $value;
		}

		return self::resolve_path( substr( $value, 1 ), $store );
	}

	/**
	 * Resolve all values in an array recursively.
	 */
	public static function resolve_array( array $data, A2E_Data_Store $store ): array {
		$resolved = array();
		foreach ( $data as $key => $value ) {
			$resolved[ $key ] = self::resolve( $value, $store );
		}
		return $resolved;
	}

	/**
	 * Resolve a dot-notation path against the data store.
	 */
	private static function resolve_path( string $path, A2E_Data_Store $store ): mixed {
		$parts   = explode( '.', $path, 2 );
		$step_id = $parts[0];
		$rest    = $parts[1] ?? null;

		$data = $store->get( $step_id );
		if ( null === $data ) {
			return null;
		}

		if ( null === $rest ) {
			return $data;
		}

		// Special: .length
		if ( 'length' === $rest && is_array( $data ) ) {
			return count( $data );
		}

		return self::dot_get( $data, $rest );
	}

	/**
	 * Navigate into data using dot notation.
	 */
	private static function dot_get( mixed $data, string $path ): mixed {
		$keys = explode( '.', $path );

		foreach ( $keys as $key ) {
			if ( 'length' === $key && is_array( $data ) ) {
				return count( $data );
			}

			if ( is_array( $data ) ) {
				if ( array_key_exists( $key, $data ) ) {
					$data = $data[ $key ];
				} elseif ( is_numeric( $key ) && array_key_exists( (int) $key, $data ) ) {
					$data = $data[ (int) $key ];
				} else {
					return null;
				}
			} elseif ( is_object( $data ) && property_exists( $data, $key ) ) {
				$data = $data->$key;
			} else {
				return null;
			}
		}

		return $data;
	}
}
