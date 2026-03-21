<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class A2E_Op_Conditional {

	public static function execute( array $step, A2E_Data_Store $store, A2E_Executor $executor ): mixed {
		$left     = A2E_Path_Resolver::resolve( $step['left'] ?? '', $store );
		$operator = $step['operator'] ?? 'eq';
		$right    = isset( $step['right'] ) ? A2E_Path_Resolver::resolve( $step['right'], $store ) : null;

		$result = self::evaluate( $left, $operator, $right );

		$branch = $result ? ( $step['then'] ?? array() ) : ( $step['else'] ?? array() );

		if ( empty( $branch ) || ! is_array( $branch ) ) {
			return array( 'condition' => $result, 'branch' => $result ? 'then' : 'else', 'executed' => false );
		}

		// Execute the branch steps
		$branch_result = $executor->execute_steps( $branch, $store );

		return array(
			'condition' => $result,
			'branch'    => $result ? 'then' : 'else',
			'executed'  => true,
			'result'    => $branch_result,
		);
	}

	private static function evaluate( mixed $left, string $op, mixed $right ): bool {
		return match ( $op ) {
			'eq'       => $left == $right,
			'neq'      => $left != $right,
			'gt'       => $left > $right,
			'gte'      => $left >= $right,
			'lt'       => $left < $right,
			'lte'      => $left <= $right,
			'truthy'   => ! empty( $left ),
			'falsy'    => empty( $left ),
			'exists'   => null !== $left,
			'empty'    => empty( $left ),
			'contains' => is_string( $left ) && str_contains( $left, (string) $right ),
			default    => false,
		};
	}
}
