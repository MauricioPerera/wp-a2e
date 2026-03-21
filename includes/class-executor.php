<?php
/**
 * A2E Workflow Executor — runs steps sequentially, feeding output into data store.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class A2E_Executor {

	/**
	 * Execute a complete workflow.
	 *
	 * @param array      $steps   Array of step definitions.
	 * @param array|null $initial Optional initial data to seed the store.
	 * @return array Execution result with store contents and metadata.
	 */
	public function run( array $steps, ?array $initial = null ): array {
		$store    = new A2E_Data_Store();
		$start    = microtime( true );
		$errors   = array();
		$executed = 0;

		// Seed store with initial data
		if ( $initial ) {
			foreach ( $initial as $k => $v ) {
				$store->set( $k, $v );
			}
		}

		$result = $this->execute_steps( $steps, $store, $errors, $executed );

		return array(
			'success'     => empty( $errors ),
			'store'       => $store->all(),
			'steps_total' => count( $steps ),
			'steps_run'   => $executed,
			'errors'      => $errors,
			'duration_ms' => round( ( microtime( true ) - $start ) * 1000, 2 ),
			'last_result' => $result,
		);
	}

	/**
	 * Execute a list of steps against a data store.
	 * Used by run() and recursively by Conditional/Loop.
	 */
	public function execute_steps( array $steps, A2E_Data_Store $store, array &$errors = array(), int &$executed = 0 ): mixed {
		$last_result = null;

		foreach ( $steps as $step ) {
			$step_id   = $step['id'] ?? 'step_' . $executed;
			$step_type = $step['type'] ?? '';

			$result = $this->execute_step( $step, $store );
			$executed++;

			if ( is_wp_error( $result ) ) {
				$errors[] = array(
					'step'    => $step_id,
					'type'    => $step_type,
					'code'    => $result->get_error_code(),
					'message' => $result->get_error_message(),
				);

				// Stop on error unless step says continue
				if ( empty( $step['continue_on_error'] ) ) {
					$store->set( $step_id, array( 'error' => $result->get_error_message() ) );
					return $result;
				}

				$store->set( $step_id, array( 'error' => $result->get_error_message() ) );
				continue;
			}

			$store->set( $step_id, $result );
			$last_result = $result;
		}

		return $last_result;
	}

	/**
	 * Execute a single step.
	 */
	private function execute_step( array $step, A2E_Data_Store $store ): mixed {
		$type = $step['type'] ?? '';

		return match ( $type ) {
			'ExecuteAbility' => A2E_Op_ExecuteAbility::execute( $step, $store ),
			'ApiCall'        => A2E_Op_ApiCall::execute( $step, $store ),
			'FilterData'     => A2E_Op_FilterData::execute( $step, $store ),
			'TransformData'  => A2E_Op_TransformData::execute( $step, $store ),
			'Conditional'    => A2E_Op_Conditional::execute( $step, $store, $this ),
			'Loop'           => A2E_Op_Loop::execute( $step, $store, $this ),
			'StoreData'      => A2E_Op_StoreData::execute( $step, $store ),
			'Wait'           => A2E_Op_Wait::execute( $step, $store ),
			'MergeData'      => A2E_Op_MergeData::execute( $step, $store ),
			default          => new WP_Error( 'unknown_type', "Unknown operation type: '{$type}'." ),
		};
	}
}
