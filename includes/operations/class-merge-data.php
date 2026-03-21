<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class A2E_Op_MergeData {

	public static function execute( array $step, A2E_Data_Store $store ): mixed {
		$sources = $step['sources'] ?? array();
		$mode    = $step['mode'] ?? 'concat';

		if ( ! is_array( $sources ) || count( $sources ) < 2 ) {
			return new WP_Error( 'invalid_sources', 'MergeData requires at least 2 sources.' );
		}

		$resolved = array();
		foreach ( $sources as $src ) {
			$resolved[] = A2E_Path_Resolver::resolve( $src, $store );
		}

		return match ( $mode ) {
			'concat'    => self::concat( $resolved ),
			'union'     => self::union( $resolved ),
			'intersect' => self::intersect( $resolved ),
			'deepMerge' => self::deep_merge( $resolved ),
			'zip'       => self::zip( $resolved ),
			default     => new WP_Error( 'unknown_mode', "Unknown merge mode: '{$mode}'." ),
		};
	}

	private static function concat( array $arrays ): array {
		$result = array();
		foreach ( $arrays as $a ) {
			if ( is_array( $a ) ) {
				$result = array_merge( $result, $a );
			}
		}
		return $result;
	}

	private static function union( array $arrays ): array {
		$result = array();
		foreach ( $arrays as $a ) {
			if ( is_array( $a ) ) {
				$result = $result + $a;
			}
		}
		return $result;
	}

	private static function intersect( array $arrays ): array {
		$first = array_shift( $arrays );
		if ( ! is_array( $first ) ) return array();
		foreach ( $arrays as $a ) {
			if ( is_array( $a ) ) {
				$first = array_intersect( $first, $a );
			}
		}
		return array_values( $first );
	}

	private static function deep_merge( array $arrays ): array {
		$result = array();
		foreach ( $arrays as $a ) {
			if ( is_array( $a ) ) {
				$result = self::merge_recursive( $result, $a );
			}
		}
		return $result;
	}

	private static function merge_recursive( array $base, array $override ): array {
		foreach ( $override as $key => $value ) {
			if ( isset( $base[ $key ] ) && is_array( $base[ $key ] ) && is_array( $value ) ) {
				$base[ $key ] = self::merge_recursive( $base[ $key ], $value );
			} else {
				$base[ $key ] = $value;
			}
		}
		return $base;
	}

	private static function zip( array $arrays ): array {
		$max    = max( array_map( fn( $a ) => is_array( $a ) ? count( $a ) : 0, $arrays ) );
		$result = array();
		for ( $i = 0; $i < $max; $i++ ) {
			$row = array();
			foreach ( $arrays as $j => $a ) {
				$row[] = is_array( $a ) ? ( $a[ $i ] ?? null ) : null;
			}
			$result[] = $row;
		}
		return $result;
	}
}
