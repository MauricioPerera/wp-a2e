<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class A2E_Op_Loop {

	private const MAX_ITERATIONS = 1000;

	public static function execute( array $step, A2E_Data_Store $store, A2E_Executor $executor ): mixed {
		$data = A2E_Path_Resolver::resolve( $step['data'] ?? '', $store );

		if ( ! is_array( $data ) ) {
			return new WP_Error( 'invalid_data', 'Loop requires array data.' );
		}

		$body      = $step['steps'] ?? array();
		$item_var  = $step['as'] ?? '_item';
		$index_var = $step['index_as'] ?? '_index';

		if ( empty( $body ) || ! is_array( $body ) ) {
			return new WP_Error( 'missing_steps', 'Loop requires a "steps" array.' );
		}

		$results    = array();
		$iterations = min( count( $data ), self::MAX_ITERATIONS );

		for ( $i = 0; $i < $iterations; $i++ ) {
			// Inject current item and index into store
			$store->set( $item_var, $data[ $i ] );
			$store->set( $index_var, $i );

			// Execute body steps
			$result = $executor->execute_steps( $body, $store );

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			$results[] = $result;
		}

		// Clean up loop variables
		$store->set( $item_var, null );
		$store->set( $index_var, null );

		return $results;
	}
}
