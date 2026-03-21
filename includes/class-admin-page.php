<?php
if ( ! defined( 'ABSPATH' ) ) exit;

class A2E_Admin_Page {

	private A2E_Workflow_Storage $workflows;

	public function __construct( A2E_Workflow_Storage $workflows ) {
		$this->workflows = $workflows;
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_post_a2e_save', array( $this, 'handle_save' ) );
		add_action( 'admin_post_a2e_delete', array( $this, 'handle_delete' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue' ) );
	}

	public function add_menu(): void {
		add_menu_page( 'A2E Workflows', 'A2E Workflows', 'manage_options', 'a2e', array( $this, 'render' ), 'dashicons-networking', 83 );
	}

	public function enqueue( string $hook ): void {
		if ( 'toplevel_page_a2e' !== $hook ) return;
		wp_enqueue_style( 'a2e-admin', A2E_URL . 'css/admin.css', array(), A2E_VERSION );
		wp_enqueue_script( 'a2e-admin', A2E_URL . 'js/admin.js', array(), A2E_VERSION, true );
	}

	public function render(): void {
		$action = $_GET['action'] ?? 'list';
		$id     = sanitize_key( $_GET['workflow'] ?? '' );
		echo '<div class="wrap">';
		match ( $action ) {
			'new', 'edit' => $this->render_form( $id ),
			default       => $this->render_list(),
		};
		echo '</div>';
	}

	private function render_list(): void {
		$wfs = $this->workflows->get_all();
		$msg = $_GET['message'] ?? '';
		?>
		<h1 class="wp-heading-inline">A2E Workflows</h1>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=a2e&action=new' ) ); ?>" class="page-title-action">Add New Workflow</a>
		<hr class="wp-header-end">

		<?php if ( 'saved' === $msg ) : ?>
			<div class="notice notice-success is-dismissible"><p>Workflow saved.</p></div>
		<?php elseif ( 'deleted' === $msg ) : ?>
			<div class="notice notice-success is-dismissible"><p>Workflow deleted.</p></div>
		<?php endif; ?>

		<?php if ( empty( $wfs ) ) : ?>
			<div class="a2e-empty"><p>No workflows yet. <a href="<?php echo esc_url( admin_url( 'admin.php?page=a2e&action=new' ) ); ?>">Create your first workflow</a>.</p></div>
		<?php else : ?>
			<table class="wp-list-table widefat fixed striped">
				<thead><tr><th>Name</th><th>ID</th><th>Steps</th><th>Actions</th></tr></thead>
				<tbody>
				<?php foreach ( $wfs as $wid => $wf ) : ?>
					<tr>
						<td><strong><?php echo esc_html( $wf['name'] ?? $wid ); ?></strong></td>
						<td><code><?php echo esc_html( $wid ); ?></code></td>
						<td><?php echo count( $wf['steps'] ?? array() ); ?></td>
						<td>
							<a href="<?php echo esc_url( admin_url( "admin.php?page=a2e&action=edit&workflow={$wid}" ) ); ?>">Edit</a> |
							<a href="<?php echo esc_url( wp_nonce_url( admin_url( "admin-post.php?action=a2e_delete&workflow={$wid}" ), 'a2e_delete' ) ); ?>" onclick="return confirm('Delete?');" style="color:#d63638;">Delete</a>
						</td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif;
	}

	private function render_form( string $edit_id = '' ): void {
		$wf      = $edit_id ? $this->workflows->get( $edit_id ) : null;
		$is_edit = null !== $wf;

		$name        = $wf['name'] ?? '';
		$description = $wf['description'] ?? '';
		$steps       = $wf['steps'] ?? array();

		if ( empty( $steps ) ) {
			$steps = array( array( 'id' => 'step_0', 'type' => 'ExecuteAbility', 'ability' => '', 'input' => array() ) );
		}

		$op_types = array( 'ExecuteAbility', 'ApiCall', 'FilterData', 'TransformData', 'Conditional', 'Loop', 'StoreData', 'Wait', 'MergeData' );
		?>
		<h1><?php echo $is_edit ? 'Edit Workflow' : 'Add New Workflow'; ?></h1>
		<a href="<?php echo esc_url( admin_url( 'admin.php?page=a2e' ) ); ?>">&larr; Back to list</a>

		<?php if ( ! empty( $_GET['error'] ) ) : ?>
			<div class="notice notice-error"><p><?php echo esc_html( urldecode( $_GET['error'] ) ); ?></p></div>
		<?php endif; ?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="a2e-form">
			<?php wp_nonce_field( 'a2e_save' ); ?>
			<input type="hidden" name="action" value="a2e_save">
			<?php if ( $is_edit ) : ?>
				<input type="hidden" name="original_id" value="<?php echo esc_attr( $edit_id ); ?>">
			<?php endif; ?>

			<table class="form-table">
				<tr>
					<th><label for="a2e_id">Workflow ID</label></th>
					<td><input type="text" id="a2e_id" name="id" value="<?php echo esc_attr( $edit_id ); ?>" class="regular-text" required pattern="[a-z0-9_-]+" <?php echo $is_edit ? 'readonly' : ''; ?>></td>
				</tr>
				<tr>
					<th><label for="a2e_name">Name</label></th>
					<td><input type="text" id="a2e_name" name="name" value="<?php echo esc_attr( $name ); ?>" class="regular-text" required></td>
				</tr>
				<tr>
					<th><label for="a2e_desc">Description</label></th>
					<td><input type="text" id="a2e_desc" name="description" value="<?php echo esc_attr( $description ); ?>" class="large-text"></td>
				</tr>
			</table>

			<h2>Steps</h2>
			<p class="description">Steps execute in order. Use <code>/step_id</code> to reference a previous step's result. Use <code>/step_id.field</code> for nested access.</p>

			<div id="a2e-steps">
			<?php foreach ( $steps as $i => $step ) : ?>
				<div class="a2e-step" data-index="<?php echo $i; ?>">
					<div class="a2e-step-header">
						<strong>Step <?php echo $i + 1; ?></strong>
						<button type="button" class="button a2e-remove-step" title="Remove">&times;</button>
					</div>
					<table class="form-table a2e-step-fields">
						<tr>
							<th>ID</th>
							<td><input type="text" name="steps[<?php echo $i; ?>][id]" value="<?php echo esc_attr( $step['id'] ?? "step_{$i}" ); ?>" class="regular-text" required pattern="[a-z0-9_]+"></td>
						</tr>
						<tr>
							<th>Type</th>
							<td>
								<select name="steps[<?php echo $i; ?>][type]" class="a2e-step-type">
									<?php foreach ( $op_types as $t ) : ?>
										<option value="<?php echo $t; ?>" <?php selected( $step['type'] ?? '', $t ); ?>><?php echo $t; ?></option>
									<?php endforeach; ?>
								</select>
							</td>
						</tr>
						<tr>
							<th>Config (JSON)</th>
							<td>
								<textarea name="steps[<?php echo $i; ?>][config]" rows="4" class="large-text" placeholder='{"ability": "my/ability", "input": {"key": "/prev_step"}}'><?php
									$config = $step;
									unset( $config['id'], $config['type'] );
									echo esc_textarea( ! empty( $config ) ? wp_json_encode( $config, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) : '' );
								?></textarea>
								<p class="description">Step-specific configuration as JSON. For ExecuteAbility: <code>{"ability": "name", "input": {...}}</code></p>
							</td>
						</tr>
					</table>
				</div>
			<?php endforeach; ?>
			</div>

			<button type="button" class="button a2e-add-step">+ Add Step</button>

			<?php submit_button( $is_edit ? 'Update Workflow' : 'Create Workflow' ); ?>
		</form>
		<?php
	}

	public function handle_save(): void {
		check_admin_referer( 'a2e_save' );
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );

		$input = wp_unslash( $_POST );

		// Merge step config JSON back into step arrays
		if ( ! empty( $input['steps'] ) && is_array( $input['steps'] ) ) {
			foreach ( $input['steps'] as &$step ) {
				if ( ! empty( $step['config'] ) ) {
					$config = json_decode( $step['config'], true );
					if ( is_array( $config ) ) {
						$step = array_merge( $step, $config );
					}
					unset( $step['config'] );
				}
			}
		}

		$result = $this->workflows->sanitize( $input );
		if ( is_wp_error( $result ) ) {
			$url = admin_url( 'admin.php?page=a2e&action=new&error=' . urlencode( $result->get_error_message() ) );
			wp_safe_redirect( $url );
			exit;
		}

		$id = $result['id'];
		unset( $result['id'] );
		$this->workflows->save( $id, $result );

		wp_safe_redirect( admin_url( 'admin.php?page=a2e&message=saved' ) );
		exit;
	}

	public function handle_delete(): void {
		check_admin_referer( 'a2e_delete' );
		if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Unauthorized' );
		$this->workflows->delete( sanitize_key( $_GET['workflow'] ?? '' ) );
		wp_safe_redirect( admin_url( 'admin.php?page=a2e&message=deleted' ) );
		exit;
	}
}
