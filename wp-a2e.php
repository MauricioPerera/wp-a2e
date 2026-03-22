<?php
/**
 * Plugin Name: WP A2E
 * Description: Agent-to-Execution workflow engine for WordPress 7.0 — orchestrate abilities into multi-step workflows.
 * Version: 0.1.0
 * Requires at least: 7.0
 * Requires PHP: 8.1
 * License: GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'A2E_VERSION', '0.1.0' );
define( 'A2E_DIR', plugin_dir_path( __FILE__ ) );
define( 'A2E_URL', plugin_dir_url( __FILE__ ) );

// Foundation
require_once A2E_DIR . 'includes/class-data-store.php';
require_once A2E_DIR . 'includes/class-path-resolver.php';
require_once A2E_DIR . 'includes/class-workflow-storage.php';

// Operations
require_once A2E_DIR . 'includes/operations/class-execute-ability.php';
require_once A2E_DIR . 'includes/operations/class-api-call.php';
require_once A2E_DIR . 'includes/operations/class-filter-data.php';
require_once A2E_DIR . 'includes/operations/class-transform-data.php';
require_once A2E_DIR . 'includes/operations/class-conditional.php';
require_once A2E_DIR . 'includes/operations/class-loop.php';
require_once A2E_DIR . 'includes/operations/class-store-data.php';
require_once A2E_DIR . 'includes/operations/class-wait.php';
require_once A2E_DIR . 'includes/operations/class-merge-data.php';

// Engine
require_once A2E_DIR . 'includes/class-execution-log.php';
require_once A2E_DIR . 'includes/class-executor.php';
require_once A2E_DIR . 'includes/class-rest-api.php';
require_once A2E_DIR . 'includes/class-abilities.php';
require_once A2E_DIR . 'includes/class-admin-page.php';

final class WP_A2E {

	private static ?self $instance = null;

	public A2E_Workflow_Storage $workflows;
	public A2E_Executor         $executor;
	public A2E_REST_API         $rest;
	public A2E_Admin_Page       $admin;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {
		$this->workflows = new A2E_Workflow_Storage();
		$this->executor  = new A2E_Executor();
		$this->rest      = new A2E_REST_API( $this->workflows, $this->executor );
		$this->admin     = new A2E_Admin_Page( $this->workflows );

		new A2E_Abilities( $this->workflows, $this->executor );
	}
}

add_action( 'plugins_loaded', function () {
	if ( ! function_exists( 'wp_register_ability' ) ) {
		return;
	}
	WP_A2E::instance();
});

function a2e(): WP_A2E {
	return WP_A2E::instance();
}

register_activation_hook( __FILE__, function () {
	A2E_Execution_Log::create_table();
});
