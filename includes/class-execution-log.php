<?php
/**
 * Execution log — records every workflow run with steps, results, and timing.
 * Uses a custom DB table for high-volume transactional data.
 */

if ( ! defined( 'ABSPATH' ) ) exit;

class A2E_Execution_Log {

	/**
	 * Create the log table on plugin activation.
	 */
	public static function create_table(): void {
		global $wpdb;
		$table   = $wpdb->prefix . 'a2e_executions';
		$charset = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE IF NOT EXISTS $table (
			id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
			workflow_id VARCHAR(100) NOT NULL DEFAULT '',
			workflow_name VARCHAR(200) NOT NULL DEFAULT '',
			trigger_source VARCHAR(50) NOT NULL DEFAULT 'manual',
			user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			status ENUM('running','completed','failed') NOT NULL DEFAULT 'running',
			steps_total INT UNSIGNED NOT NULL DEFAULT 0,
			steps_run INT UNSIGNED NOT NULL DEFAULT 0,
			duration_ms DOUBLE NOT NULL DEFAULT 0,
			data_store LONGTEXT,
			errors LONGTEXT,
			started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
			finished_at DATETIME DEFAULT NULL,
			INDEX idx_workflow (workflow_id),
			INDEX idx_status (status),
			INDEX idx_started (started_at)
		) $charset;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Start a log entry (status = running).
	 */
	public static function start( string $workflow_id, string $workflow_name, string $trigger, int $steps_total ): int {
		global $wpdb;
		$wpdb->insert( $wpdb->prefix . 'a2e_executions', array(
			'workflow_id'   => $workflow_id,
			'workflow_name' => $workflow_name,
			'trigger_source' => $trigger,
			'user_id'       => get_current_user_id(),
			'status'        => 'running',
			'steps_total'   => $steps_total,
			'started_at'    => current_time( 'mysql' ),
		));
		return (int) $wpdb->insert_id;
	}

	/**
	 * Finish a log entry with results.
	 */
	public static function finish( int $log_id, array $result ): void {
		global $wpdb;
		$wpdb->update(
			$wpdb->prefix . 'a2e_executions',
			array(
				'status'      => empty( $result['errors'] ) ? 'completed' : 'failed',
				'steps_run'   => $result['steps_run'] ?? 0,
				'duration_ms' => $result['duration_ms'] ?? 0,
				'data_store'  => wp_json_encode( $result['store'] ?? array() ),
				'errors'      => wp_json_encode( $result['errors'] ?? array() ),
				'finished_at' => current_time( 'mysql' ),
			),
			array( 'id' => $log_id )
		);
	}

	/**
	 * Get recent executions.
	 */
	public static function get_recent( int $limit = 50, string $workflow_id = '' ): array {
		global $wpdb;
		$table = $wpdb->prefix . 'a2e_executions';

		if ( $workflow_id ) {
			return $wpdb->get_results( $wpdb->prepare(
				"SELECT * FROM $table WHERE workflow_id = %s ORDER BY started_at DESC LIMIT %d",
				$workflow_id, $limit
			), ARRAY_A );
		}

		return $wpdb->get_results( $wpdb->prepare(
			"SELECT * FROM $table ORDER BY started_at DESC LIMIT %d",
			$limit
		), ARRAY_A );
	}

	/**
	 * Get a single execution by ID.
	 */
	public static function get( int $id ): ?array {
		global $wpdb;
		$row = $wpdb->get_row( $wpdb->prepare(
			"SELECT * FROM {$wpdb->prefix}a2e_executions WHERE id = %d",
			$id
		), ARRAY_A );

		if ( ! $row ) return null;

		$row['data_store'] = json_decode( $row['data_store'] ?? '{}', true );
		$row['errors']     = json_decode( $row['errors'] ?? '[]', true );
		return $row;
	}

	/**
	 * Get execution stats.
	 */
	public static function get_stats(): array {
		global $wpdb;
		$table = $wpdb->prefix . 'a2e_executions';

		return array(
			'total'     => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table" ),
			'completed' => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status = 'completed'" ),
			'failed'    => (int) $wpdb->get_var( "SELECT COUNT(*) FROM $table WHERE status = 'failed'" ),
			'avg_ms'    => round( (float) $wpdb->get_var( "SELECT AVG(duration_ms) FROM $table WHERE status = 'completed'" ), 2 ),
			'today'     => (int) $wpdb->get_var( $wpdb->prepare(
				"SELECT COUNT(*) FROM $table WHERE started_at >= %s",
				current_time( 'Y-m-d' ) . ' 00:00:00'
			)),
		);
	}

	/**
	 * Purge old entries.
	 */
	public static function purge( int $days = 30 ): int {
		global $wpdb;
		return (int) $wpdb->query( $wpdb->prepare(
			"DELETE FROM {$wpdb->prefix}a2e_executions WHERE started_at < %s",
			gmdate( 'Y-m-d H:i:s', time() - ( $days * DAY_IN_SECONDS ) )
		));
	}
}
