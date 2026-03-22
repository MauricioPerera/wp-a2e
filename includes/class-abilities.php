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
				return $executor
					->set_context( $wf_id, $wf['name'] ?? $wf_id, 'ability' )
					->run( $wf['steps'], $initial );
			},
			'permission_callback' => fn() => current_user_can( 'edit_posts' ),
			'meta' => array(
				'annotations'  => array( 'readonly' => false, 'destructive' => false, 'idempotent' => false ),
				'show_in_rest' => true,
			),
		));

		// Register individual workflows as their own abilities
		$this->register_workflow_abilities( $executor, $workflows );

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

	/**
	 * Register workflows that have 'register_as_ability' enabled
	 * as their own named abilities.
	 */
	private function register_workflow_abilities( A2E_Executor $executor, A2E_Workflow_Storage $workflows ): void {
		foreach ( $workflows->get_all() as $id => $wf ) {
			if ( empty( $wf['register_as_ability'] ) ) {
				continue;
			}

			$ability_name = $wf['ability_name'] ?? "a2e/{$id}";
			$category     = $wf['ability_category'] ?? 'orchestration';

			// Build input schema from workflow config
			$input_schema = array();
			if ( ! empty( $wf['ability_input_schema'] ) ) {
				$input_schema = $wf['ability_input_schema'];
			}

			// Build output schema
			$output_schema = array();
			if ( ! empty( $wf['ability_output_schema'] ) ) {
				$output_schema = $wf['ability_output_schema'];
			}

			// Determine which step's result to return (last step by default)
			$return_step = $wf['ability_return_step'] ?? '';

			wp_register_ability( $ability_name, array(
				'label'               => $wf['name'] ?? $id,
				'description'         => $wf['description'] ?? "Executes the {$id} workflow.",
				'category'            => $category,
				'input_schema'        => $input_schema ?: array(),
				'output_schema'       => $output_schema ?: array(),
				'execute_callback'    => function ( $input = null ) use ( $executor, $wf, $return_step, $id ) {
					$result = $executor
						->set_context( $id, $wf['name'] ?? $id, 'ability' )
						->run( $wf['steps'], is_array( $input ) ? $input : null );

					if ( ! $result['success'] ) {
						$err = $result['errors'][0] ?? array();
						return new WP_Error(
							$err['code'] ?? 'workflow_failed',
							$err['message'] ?? 'Workflow execution failed.'
						);
					}

					// Return specific step's result or last_result
					if ( $return_step && isset( $result['store'][ $return_step ] ) ) {
						return $result['store'][ $return_step ];
					}

					return $result['last_result'];
				},
				'permission_callback' => fn() => current_user_can( $wf['ability_permission'] ?? 'edit_posts' ),
				'meta'                => array(
					'annotations'  => $wf['ability_annotations'] ?? array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					),
					'show_in_rest' => $wf['ability_show_in_rest'] ?? true,
				),
			));
		}
	}
}
