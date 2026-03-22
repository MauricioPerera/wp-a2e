<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class A2E_REST_API {

	private const NS = 'wp-a2e/v1';
	private A2E_Workflow_Storage $workflows;
	private A2E_Executor         $executor;

	public function __construct( A2E_Workflow_Storage $workflows, A2E_Executor $executor ) {
		$this->workflows = $workflows;
		$this->executor  = $executor;
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes(): void {
		register_rest_route( self::NS, '/health', array(
			'methods'             => 'GET',
			'callback'            => fn() => new WP_REST_Response( array( 'status' => 'ok', 'version' => A2E_VERSION ) ),
			'permission_callback' => '__return_true',
		));

		register_rest_route( self::NS, '/workflows', array(
			array(
				'methods'             => 'GET',
				'callback'            => fn() => new WP_REST_Response( $this->workflows->get_all() ),
				'permission_callback' => fn() => current_user_can( 'manage_options' ),
			),
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'save_workflow' ),
				'permission_callback' => fn() => current_user_can( 'manage_options' ),
			),
		));

		register_rest_route( self::NS, '/workflows/(?P<id>[a-z0-9_-]+)', array(
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'get_workflow' ),
				'permission_callback' => fn() => current_user_can( 'manage_options' ),
			),
			array(
				'methods'             => 'DELETE',
				'callback'            => array( $this, 'delete_workflow' ),
				'permission_callback' => fn() => current_user_can( 'manage_options' ),
			),
		));

		register_rest_route( self::NS, '/workflows/(?P<id>[a-z0-9_-]+)/execute', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'execute_workflow' ),
			'permission_callback' => fn() => current_user_can( 'edit_posts' ),
		));

		register_rest_route( self::NS, '/executions', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'list_executions' ),
			'permission_callback' => fn() => current_user_can( 'manage_options' ),
		));

		register_rest_route( self::NS, '/executions/(?P<log_id>\d+)', array(
			'methods'             => 'GET',
			'callback'            => array( $this, 'get_execution' ),
			'permission_callback' => fn() => current_user_can( 'manage_options' ),
		));

		register_rest_route( self::NS, '/execute', array(
			'methods'             => 'POST',
			'callback'            => array( $this, 'execute_inline' ),
			'permission_callback' => fn() => current_user_can( 'edit_posts' ),
		));
	}

	public function save_workflow( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$input     = $request->get_json_params() ?: $request->get_body_params();
		$sanitized = $this->workflows->sanitize( $input );

		if ( is_wp_error( $sanitized ) ) {
			$sanitized->add_data( array( 'status' => 400 ) );
			return $sanitized;
		}

		$id = $sanitized['id'];
		unset( $sanitized['id'] );
		$this->workflows->save( $id, $sanitized );

		return new WP_REST_Response( array( 'id' => $id, 'saved' => true ) );
	}

	public function get_workflow( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$wf = $this->workflows->get( $request['id'] );
		if ( ! $wf ) {
			return new WP_Error( 'not_found', 'Workflow not found.', array( 'status' => 404 ) );
		}
		$wf['id'] = $request['id'];
		return new WP_REST_Response( $wf );
	}

	public function delete_workflow( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		if ( ! $this->workflows->get( $request['id'] ) ) {
			return new WP_Error( 'not_found', 'Workflow not found.', array( 'status' => 404 ) );
		}
		$this->workflows->delete( $request['id'] );
		return new WP_REST_Response( array( 'deleted' => true ) );
	}

	public function execute_workflow( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$wf = $this->workflows->get( $request['id'] );
		if ( ! $wf ) {
			return new WP_Error( 'not_found', 'Workflow not found.', array( 'status' => 404 ) );
		}

		$input  = $request->get_json_params()['input'] ?? null;
		$result = $this->executor
			->set_context( $request['id'], $wf['name'] ?? $request['id'], 'rest' )
			->run( $wf['steps'], $input );

		return new WP_REST_Response( $result );
	}

	public function execute_inline( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$body  = $request->get_json_params();
		$steps = $body['steps'] ?? array();
		$input = $body['input'] ?? null;

		if ( empty( $steps ) ) {
			return new WP_Error( 'missing_steps', 'No steps provided.', array( 'status' => 400 ) );
		}

		$result = $this->executor
			->set_context( 'inline', 'Inline Execution', 'rest' )
			->run( $steps, $input );
		return new WP_REST_Response( $result );
	}

	public function list_executions( WP_REST_Request $request ): WP_REST_Response {
		$workflow_id = $request->get_param( 'workflow' ) ?? '';
		$limit       = min( (int) ( $request->get_param( 'limit' ) ?? 50 ), 200 );

		$rows  = A2E_Execution_Log::get_recent( $limit, $workflow_id );
		$stats = A2E_Execution_Log::get_stats();

		return new WP_REST_Response( array(
			'executions' => $rows,
			'stats'      => $stats,
		));
	}

	public function get_execution( WP_REST_Request $request ): WP_REST_Response|WP_Error {
		$entry = A2E_Execution_Log::get( (int) $request['log_id'] );
		if ( ! $entry ) {
			return new WP_Error( 'not_found', 'Execution not found.', array( 'status' => 404 ) );
		}
		return new WP_REST_Response( $entry );
	}
}
