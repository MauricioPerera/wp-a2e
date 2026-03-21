<?php
/**
 * Registers A2E workflow abilities in the WP Abilities API.
 * Makes workflows discoverable and executable via MCP.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class A2E_Abilities {

	private A2E_Workflow_Storage $workflows;
	private A2E_Executor         $executor;

	public function __construct( A2E_Workflow_Storage $workflows, A2E_Executor $executor ) {
		$this->workflows = $workflows;
		$this->executor  = $executor;
		add_action( 'wp_abilities_api_categories_init', array( $this, 'register_categories' ) );
		add_action( 'wp_abilities_api_init', array( $this, 'register_abilities' ) );
	}

	public function register_categories(): void {
		if ( ! wp_has_ability_category( 'orchestration' ) ) {
			wp_register_ability_category( 'orchestration', array(
				'label'       => 'Orchestration',
				'description' => 'Workflow execution and orchestration abilities.',
			));
		}
	}

	public function register_abilities(): void {
		$executor  = $this->executor;
		$workflows = $this->workflows;

		wp_register_ability( 'a2e/execute-workflow', array(
			'label'       => 'Execute Workflow',
			'description' => 'Execute a saved A2E workflow by ID. Returns the complete data store with all step results.',
			'category'    => 'orchestration',
			'input_schema' => array(
				'type'       => 'object',
				'required'   => array( 'workflow_id' ),
				'properties' => array(
					'workflow_id' => array( 'type' => 'string', 'description' => 'The workflow ID to execute.' ),
					'input'       => array( 'type' => 'object', 'description' => 'Optional initial data for the workflow.' ),
				),
			),
			'output_schema' => array(
				'type'       => 'object',
				'properties' => array(
					'success'     => array( 'type' => 'boolean' ),
					'store'       => array( 'type' => 'object' ),
					'steps_run'   => array( 'type' => 'integer' ),
					'duration_ms' => array( 'type' => 'number' ),
				),
			),
			'execute_callback' => function ( $input = null ) use ( $executor, $workflows ) {
				$wf_id = is_array( $input ) ? ( $input['workflow_id'] ?? '' ) : '';
				if ( '' === $wf_id ) {
					return new WP_Error( 'missing_id', 'workflow_id is required.' );
				}
				$wf = $workflows->get( $wf_id );
				if ( ! $wf ) {
					return new WP_Error( 'not_found', "Workflow '{$wf_id}' not found." );
				}
				$initial = is_array( $input ) ? ( $input['input'] ?? null ) : null;
				return $executor->run( $wf['steps'], $initial );
			},
			'permission_callback' => fn() => current_user_can( 'edit_posts' ),
			'meta' => array(
				'annotations'  => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ),
				'show_in_rest' => true,
			),
		));

		wp_register_ability( 'a2e/list-workflows', array(
			'label'       => 'List Workflows',
			'description' => 'List all available A2E workflows with their names and step counts.',
			'category'    => 'orchestration',
			'output_schema' => array(
				'type'  => 'array',
				'items' => array(
					'type'       => 'object',
					'properties' => array(
						'id'          => array( 'type' => 'string' ),
						'name'        => array( 'type' => 'string' ),
						'description' => array( 'type' => 'string' ),
						'steps_count' => array( 'type' => 'integer' ),
					),
				),
			),
			'execute_callback' => function () use ( $workflows ) {
				$result = array();
				foreach ( $workflows->get_all() as $id => $wf ) {
					$result[] = array(
						'id'          => $id,
						'name'        => $wf['name'] ?? $id,
						'description' => $wf['description'] ?? '',
						'steps_count' => count( $wf['steps'] ?? array() ),
					);
				}
				return $result;
			},
			'permission_callback' => fn() => current_user_can( 'read' ),
			'meta' => array(
				'annotations'  => array( 'readonly' => true, 'destructive' => false, 'idempotent' => true ),
				'show_in_rest' => true,
			),
		));
	}
}
