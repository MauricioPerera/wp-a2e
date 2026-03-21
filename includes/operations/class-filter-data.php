<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class A2E_Op_FilterData {

	public static function execute( array $step, A2E_Data_Store $store ): mixed {
		$data = A2E_Path_Resolver::resolve( $step['data'] ?? '', $store );

		if ( ! is_array( $data ) ) {
			return new WP_Error( 'invalid_data', 'FilterData requires array data.' );
		}

		$field    = $step['field'] ?? '';
		$operator = $step['operator'] ?? 'eq';
		$value    = isset( $step['value'] ) ? A2E_Path_Resolver::resolve( $step['value'], $store ) : null;

		return array_values( array_filter( $data, function ( $item ) use ( $field, $operator, $value ) {
			$item_val = is_array( $item ) ? ( $item[ $field ] ?? null ) : null;
			return self::compare( $item_val, $operator, $value );
		}));
	}

	private static function compare( mixed $a, string $op, mixed $b ): bool {
		return match ( $op ) {
			'eq'         => $a == $b,
			'neq'        => $a != $b,
			'gt'         => $a > $b,
			'gte'        => $a >= $b,
			'lt'         => $a < $b,
			'lte'        => $a <= $b,
			'contains'   => is_string( $a ) && str_contains( $a, (string) $b ),
			'startsWith' => is_string( $a ) && str_starts_with( $a, (string) $b ),
			'endsWith'   => is_string( $a ) && str_ends_with( $a, (string) $b ),
			'in'         => is_array( $b ) && in_array( $a, $b, false ),
			'exists'     => null !== $a,
			'empty'      => empty( $a ),
			default      => false,
		};
	}
}
