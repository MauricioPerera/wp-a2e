<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class A2E_Workflow_Storage {

	private const OPTION = 'a2e_workflows';

	public function get_all(): array {
		return get_option( self::OPTION, array() );
	}

	public function get( string $id ): ?array {
		return $this->get_all()[ $id ] ?? null;
	}

	public function save( string $id, array $data ): void {
		$all         = $this->get_all();
		$all[ $id ]  = $data;
		update_option( self::OPTION, $all, false );
	}

	public function delete( string $id ): void {
		$all = $this->get_all();
		unset( $all[ $id ] );
		update_option( self::OPTION, $all, false );
	}

	public function sanitize( array $input ): array|WP_Error {
		$id = sanitize_key( $input['id'] ?? '' );
		if ( '' === $id ) {
			return new WP_Error( 'missing_id', 'Workflow ID is required.' );
		}

		$name = sanitize_text_field( $input['name'] ?? '' );
		if ( '' === $name ) {
			return new WP_Error( 'missing_name', 'Workflow name is required.' );
		}

		$steps = $input['steps'] ?? array();
		if ( ! is_array( $steps ) || empty( $steps ) ) {
			return new WP_Error( 'missing_steps', 'At least one step is required.' );
		}

		$valid_types = array(
			'ExecuteAbility', 'ApiCall', 'FilterData', 'TransformData',
			'Conditional', 'Loop', 'StoreData', 'Wait', 'MergeData',
		);

		$sanitized_steps = array();
		foreach ( $steps as $i => $step ) {
			$step_id   = sanitize_key( $step['id'] ?? 'step_' . $i );
			$step_type = $step['type'] ?? '';

			if ( ! in_array( $step_type, $valid_types, true ) ) {
				return new WP_Error( 'invalid_type', "Step '{$step_id}' has invalid type '{$step_type}'." );
			}

			$sanitized_steps[] = array_merge( array( 'id' => $step_id, 'type' => $step_type ), $step );
		}

		$result = array(
			'id'          => $id,
			'name'        => $name,
			'description' => sanitize_text_field( $input['description'] ?? '' ),
			'steps'       => $sanitized_steps,
			'enabled'     => ! empty( $input['enabled'] ),
		);

		// Ability registration fields
		$result['register_as_ability'] = ! empty( $input['register_as_ability'] );
		if ( $result['register_as_ability'] ) {
			$ab_name = sanitize_text_field( $input['ability_name'] ?? '' );
			$result['ability_name']          = $ab_name ?: "a2e/{$id}";
			$result['ability_return_step']   = sanitize_key( $input['ability_return_step'] ?? '' );
			$result['ability_category']      = 'orchestration';
			$result['ability_permission']    = 'edit_posts';
			$result['ability_show_in_rest']  = true;
			$result['ability_annotations']   = array(
				'readonly'    => false,
				'destructive' => false,
				'idempotent'  => false,
			);
		}

		return $result;
	}
}
