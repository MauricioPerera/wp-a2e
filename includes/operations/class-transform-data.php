<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class A2E_Op_TransformData {

	public static function execute( array $step, A2E_Data_Store $store ): mixed {
		$data      = A2E_Path_Resolver::resolve( $step['data'] ?? '', $store );
		$operation = $step['operation'] ?? '';

		if ( ! is_array( $data ) ) {
			return new WP_Error( 'invalid_data', 'TransformData requires array data.' );
		}

		return match ( $operation ) {
			'select'    => self::select( $data, $step['fields'] ?? array() ),
			'sort'      => self::sort( $data, $step['field'] ?? '', $step['order'] ?? 'asc' ),
			'group'     => self::group( $data, $step['field'] ?? '' ),
			'aggregate' => self::aggregate( $data, $step['field'] ?? '', $step['function'] ?? 'count' ),
			'map'       => self::map( $data, $step['field'] ?? '', $step['as'] ?? '' ),
			'flatten'   => self::flatten( $data ),
			'unique'    => self::unique( $data, $step['field'] ?? '' ),
			'reverse'   => array_reverse( $data ),
			'slice'     => array_slice( $data, (int) ( $step['offset'] ?? 0 ), (int) ( $step['limit'] ?? 10 ) ),
			'count'     => count( $data ),
			default     => new WP_Error( 'unknown_operation', "Unknown transform: '{$operation}'." ),
		};
	}

	private static function select( array $data, array $fields ): array {
		return array_map( function ( $item ) use ( $fields ) {
			if ( ! is_array( $item ) ) return $item;
			return array_intersect_key( $item, array_flip( $fields ) );
		}, $data );
	}

	private static function sort( array $data, string $field, string $order ): array {
		usort( $data, function ( $a, $b ) use ( $field, $order ) {
			$va = is_array( $a ) ? ( $a[ $field ] ?? 0 ) : 0;
			$vb = is_array( $b ) ? ( $b[ $field ] ?? 0 ) : 0;
			$cmp = $va <=> $vb;
			return 'desc' === strtolower( $order ) ? -$cmp : $cmp;
		});
		return $data;
	}

	private static function group( array $data, string $field ): array {
		$groups = array();
		foreach ( $data as $item ) {
			$key = is_array( $item ) ? (string) ( $item[ $field ] ?? '_none' ) : '_none';
			$groups[ $key ][] = $item;
		}
		return $groups;
	}

	private static function aggregate( array $data, string $field, string $fn ): mixed {
		$values = array_map( fn( $item ) => is_array( $item ) ? ( $item[ $field ] ?? 0 ) : 0, $data );
		return match ( $fn ) {
			'count' => count( $values ),
			'sum'   => array_sum( $values ),
			'avg'   => count( $values ) > 0 ? array_sum( $values ) / count( $values ) : 0,
			'min'   => ! empty( $values ) ? min( $values ) : 0,
			'max'   => ! empty( $values ) ? max( $values ) : 0,
			default => count( $values ),
		};
	}

	private static function map( array $data, string $field, string $as ): array {
		$key = $as ?: $field;
		return array_map( function ( $item ) use ( $field, $key ) {
			return is_array( $item ) ? ( $item[ $field ] ?? null ) : $item;
		}, $data );
	}

	private static function flatten( array $data ): array {
		$result = array();
		array_walk_recursive( $data, function ( $val ) use ( &$result ) {
			$result[] = $val;
		});
		return $result;
	}

	private static function unique( array $data, string $field ): array {
		if ( '' === $field ) {
			return array_values( array_unique( $data ) );
		}
		$seen = array();
		return array_values( array_filter( $data, function ( $item ) use ( $field, &$seen ) {
			$val = is_array( $item ) ? ( $item[ $field ] ?? null ) : $item;
			if ( in_array( $val, $seen, true ) ) return false;
			$seen[] = $val;
			return true;
		}));
	}
}
