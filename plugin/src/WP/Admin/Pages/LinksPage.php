<?php
/**
 * Links Admin Page.
 *
 * @package EdgeLinkRouter
 */

namespace CFELR\WP\Admin\Pages;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CFELR\Core\Models\Link;
use CFELR\Core\Validator;
use CFELR\WP\Admin\LinksListTable;
use CFELR\WP\Repository\WPLinkRepository;
use CFELR\WP\CSV\Exporter;
use CFELR\WP\CSV\Importer;

/**
 * Links management page.
 */
class LinksPage {

	/**
	 * Repository instance.
	 *
	 * @var WPLinkRepository
	 */
	private WPLinkRepository $repository;

	/**
	 * Validator instance.
	 *
	 * @var Validator
	 */
	private Validator $validator;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->repository = new WPLinkRepository();
		$this->validator  = new Validator();
	}

	/**
	 * Render the page.
	 *
	 * @return void
	 */
	public function render(): void {
		// Handle actions.
		$this->handle_actions();

		// Get current action.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$action = isset( $_GET['action'] ) ? sanitize_text_field( wp_unslash( $_GET['action'] ) ) : 'list';

		switch ( $action ) {
			case 'add':
				$this->render_add_form();
				break;

			case 'edit':
				$this->render_edit_form();
				break;

			case 'import':
				$this->render_import_form();
				break;

			default:
				$this->render_list();
				break;
		}
	}

	/**
	 * Handle actions that require redirects (called early, before output).
	 *
	 * @return void
	 */
	public function handle_early_actions(): void {
		// Handle CSV export.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['action'] ) && $_GET['action'] === 'export' ) {
			if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'cfelr_export' ) ) {
				wp_die( esc_html__( 'Security check failed.', 'edge-link-router' ) );
			}
			$exporter = new Exporter();
			$exporter->download();
		}

		// Handle delete.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete' && isset( $_GET['id'] ) ) {
			$id = (int) $_GET['id'];
			if ( ! isset( $_GET['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['_wpnonce'] ) ), 'delete_link_' . $id ) ) {
				wp_die( esc_html__( 'Security check failed.', 'edge-link-router' ) );
			}
			$this->repository->delete( $id );
			$this->maybe_schedule_publish();
			wp_safe_redirect( admin_url( 'admin.php?page=edge-link-router&deleted=1' ) );
			exit;
		}

		// Handle bulk actions.
		if ( isset( $_POST['action'] ) && isset( $_POST['link'] ) && is_array( $_POST['link'] ) ) {
			if ( ! isset( $_POST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wpnonce'] ) ), 'bulk-links' ) ) {
				wp_die( esc_html__( 'Security check failed.', 'edge-link-router' ) );
			}

			$action = sanitize_text_field( wp_unslash( $_POST['action'] ) );
			$ids    = array_map( 'intval', $_POST['link'] );

			$this->handle_bulk_action( $action, $ids );
		}

		// Handle add/edit form submission.
		if ( isset( $_POST['cfelr_save_link'] ) ) {
			$this->handle_save_link();
		}

		// Handle import.
		if ( isset( $_POST['cfelr_import'] ) ) {
			$this->handle_import();
		}
	}

	/**
	 * Handle display-only actions (notices, etc).
	 *
	 * @return void
	 */
	private function handle_actions(): void {
		// This method now only handles things that don't require redirects.
		// All redirect-requiring actions are in handle_early_actions().
	}

	/**
	 * Handle bulk actions.
	 *
	 * @param string $action Action name.
	 * @param array  $ids    Link IDs.
	 * @return void
	 */
	private function handle_bulk_action( string $action, array $ids ): void {
		if ( empty( $ids ) ) {
			return;
		}

		$count = 0;

		foreach ( $ids as $id ) {
			$link = $this->repository->find( $id );
			if ( ! $link ) {
				continue;
			}

			switch ( $action ) {
				case 'enable':
					$link->enabled = true;
					$this->repository->update( $link );
					++$count;
					break;

				case 'disable':
					$link->enabled = false;
					$this->repository->update( $link );
					++$count;
					break;

				case 'delete':
					$this->repository->delete( $id );
					++$count;
					break;
			}
		}

		$redirect_args = array(
			'page'        => 'edge-link-router',
			'bulk_action' => $action,
			'count'       => $count,
		);

		wp_safe_redirect( add_query_arg( $redirect_args, admin_url( 'admin.php' ) ) );
		exit;
	}

	/**
	 * Handle save link form.
	 *
	 * @return void
	 */
	private function handle_save_link(): void {
		if ( ! isset( $_POST['cfelr_link_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cfelr_link_nonce'] ) ), 'cfelr_save_link' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'edge-link-router' ) );
		}

		$id = isset( $_POST['link_id'] ) ? (int) $_POST['link_id'] : 0;

		// Gather form data.
		$data = array(
			'slug'        => isset( $_POST['slug'] ) ? sanitize_text_field( wp_unslash( $_POST['slug'] ) ) : '',
			'target_url'  => isset( $_POST['target_url'] ) ? esc_url_raw( wp_unslash( $_POST['target_url'] ) ) : '',
			'status_code' => isset( $_POST['status_code'] ) ? (int) $_POST['status_code'] : 302,
			'enabled'     => isset( $_POST['enabled'] ),
			'options'     => array(
				'passthrough_query' => isset( $_POST['passthrough_query'] ),
				'append_utm'        => array(),
				'notes'             => isset( $_POST['notes'] ) ? sanitize_textarea_field( wp_unslash( $_POST['notes'] ) ) : '',
			),
		);

		// Parse UTM parameters.
		if ( ! empty( $_POST['utm_source'] ) ) {
			$data['options']['append_utm']['utm_source'] = sanitize_text_field( wp_unslash( $_POST['utm_source'] ) );
		}
		if ( ! empty( $_POST['utm_medium'] ) ) {
			$data['options']['append_utm']['utm_medium'] = sanitize_text_field( wp_unslash( $_POST['utm_medium'] ) );
		}
		if ( ! empty( $_POST['utm_campaign'] ) ) {
			$data['options']['append_utm']['utm_campaign'] = sanitize_text_field( wp_unslash( $_POST['utm_campaign'] ) );
		}

		// Get host for validation.
		$host   = wp_parse_url( home_url(), PHP_URL_HOST );
		$prefix = $this->get_prefix();

		// Validate.
		$errors = $this->validator->validate( $data, $host, $prefix, $id );

		// Check slug uniqueness.
		$sanitized_slug = $this->validator->sanitize_slug( $data['slug'] );
		$existing       = $this->repository->find_by_slug( $sanitized_slug );
		if ( $existing && $existing->id !== $id ) {
			$errors[] = __( 'A link with this slug already exists.', 'edge-link-router' );
		}

		if ( ! empty( $errors ) ) {
			// Store errors in transient for display.
			set_transient( 'cfelr_form_errors', $errors, 60 );
			set_transient( 'cfelr_form_data', $data, 60 );

			$redirect_url = $id
				? admin_url( 'admin.php?page=edge-link-router&action=edit&id=' . $id )
				: admin_url( 'admin.php?page=edge-link-router&action=add' );

			wp_safe_redirect( $redirect_url );
			exit;
		}

		// Create link object.
		$link              = new Link();
		$link->slug        = $sanitized_slug;
		$link->target_url  = $data['target_url'];
		$link->status_code = $data['status_code'];
		$link->enabled     = $data['enabled'];
		$link->options     = $data['options'];

		if ( $id ) {
			$link->id = $id;
			$this->repository->update( $link );
			$message = 'updated';
		} else {
			$this->repository->create( $link );
			$message = 'created';
		}

		// Trigger debounced edge publish if edge is enabled.
		$this->maybe_schedule_publish();

		wp_safe_redirect( admin_url( 'admin.php?page=edge-link-router&' . $message . '=1' ) );
		exit;
	}

	/**
	 * Handle CSV import.
	 *
	 * @return void
	 */
	private function handle_import(): void {
		if ( ! isset( $_POST['cfelr_import_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cfelr_import_nonce'] ) ), 'cfelr_import' ) ) {
			wp_die( esc_html__( 'Security check failed.', 'edge-link-router' ) );
		}

		if ( empty( $_FILES['csv_file'] ) || empty( $_FILES['csv_file']['tmp_name'] ) ) {
			set_transient( 'cfelr_import_error', __( 'Please select a CSV file to import.', 'edge-link-router' ), 60 );
			wp_safe_redirect( admin_url( 'admin.php?page=edge-link-router&action=import' ) );
			exit;
		}

		// Validate upload error code.
		$upload_error = isset( $_FILES['csv_file']['error'] ) ? (int) $_FILES['csv_file']['error'] : UPLOAD_ERR_NO_FILE;
		if ( UPLOAD_ERR_OK !== $upload_error ) {
			set_transient( 'cfelr_import_error', __( 'File upload failed. Please try again.', 'edge-link-router' ), 60 );
			wp_safe_redirect( admin_url( 'admin.php?page=edge-link-router&action=import' ) );
			exit;
		}

		// Sanitize and validate file name and type.
		$file_name = isset( $_FILES['csv_file']['name'] ) ? sanitize_file_name( wp_unslash( $_FILES['csv_file']['name'] ) ) : '';
		$file_type = wp_check_filetype( $file_name, array( 'csv' => 'text/csv' ) );

		if ( ! $file_type['ext'] ) {
			set_transient( 'cfelr_import_error', __( 'Please upload a CSV file.', 'edge-link-router' ), 60 );
			wp_safe_redirect( admin_url( 'admin.php?page=edge-link-router&action=import' ) );
			exit;
		}

		// Validate tmp_name is an actual uploaded file.
		$tmp_name = isset( $_FILES['csv_file']['tmp_name'] ) ? sanitize_text_field( $_FILES['csv_file']['tmp_name'] ) : '';
		if ( empty( $tmp_name ) || ! is_uploaded_file( $tmp_name ) ) {
			set_transient( 'cfelr_import_error', __( 'Invalid file upload.', 'edge-link-router' ), 60 );
			wp_safe_redirect( admin_url( 'admin.php?page=edge-link-router&action=import' ) );
			exit;
		}

		$importer = new Importer();
		$results  = $importer->import( $tmp_name );

		set_transient( 'cfelr_import_results', $results, 60 );

		// Trigger debounced edge publish if edge is enabled.
		if ( $results['created'] > 0 || $results['updated'] > 0 ) {
			$this->maybe_schedule_publish();
		}

		wp_safe_redirect( admin_url( 'admin.php?page=edge-link-router&action=import&completed=1' ) );
		exit;
	}

	/**
	 * Render list view.
	 *
	 * @return void
	 */
	private function render_list(): void {
		$list_table = new LinksListTable();
		$list_table->prepare_items();
		?>
		<div class="wrap cfelr-admin">
			<h1><?php esc_html_e( 'Links', 'edge-link-router' ); ?></h1>

			<?php $this->render_notices(); ?>

			<form method="post" id="cfelr-links-form">
				<div class="cfelr-toolbar">
					<div class="cfelr-toolbar__actions">
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=edge-link-router&action=add' ) ); ?>" class="button button-primary">
							<?php esc_html_e( 'Add New', 'edge-link-router' ); ?>
						</a>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=edge-link-router&action=import' ) ); ?>" class="button">
							<?php esc_html_e( 'Import', 'edge-link-router' ); ?>
						</a>
						<a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=edge-link-router&action=export' ), 'cfelr_export' ) ); ?>" class="button">
							<?php esc_html_e( 'Export', 'edge-link-router' ); ?>
						</a>
					</div>
					<?php $list_table->search_box( __( 'Search Links', 'edge-link-router' ), 'cfelr-search' ); ?>
				</div>
				<?php $list_table->display(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Render add form.
	 *
	 * @return void
	 */
	private function render_add_form(): void {
		$form_data = get_transient( 'cfelr_form_data' );
		delete_transient( 'cfelr_form_data' );

		$link = new Link();
		if ( $form_data ) {
			$link->slug        = $form_data['slug'] ?? '';
			$link->target_url  = $form_data['target_url'] ?? '';
			$link->status_code = $form_data['status_code'] ?? 302;
			$link->enabled     = $form_data['enabled'] ?? true;
			$link->options     = $form_data['options'] ?? array();
		}

		$this->render_form( $link, __( 'Add New Link', 'edge-link-router' ) );
	}

	/**
	 * Render edit form.
	 *
	 * @return void
	 */
	private function render_edit_form(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$id   = isset( $_GET['id'] ) ? (int) $_GET['id'] : 0;
		$link = $this->repository->find( $id );

		if ( ! $link ) {
			wp_die( esc_html__( 'Link not found.', 'edge-link-router' ) );
		}

		// Check for form data from failed validation.
		$form_data = get_transient( 'cfelr_form_data' );
		delete_transient( 'cfelr_form_data' );

		if ( $form_data ) {
			$link->slug        = $form_data['slug'] ?? $link->slug;
			$link->target_url  = $form_data['target_url'] ?? $link->target_url;
			$link->status_code = $form_data['status_code'] ?? $link->status_code;
			$link->enabled     = $form_data['enabled'] ?? $link->enabled;
			$link->options     = $form_data['options'] ?? $link->options;
		}

		$this->render_form( $link, __( 'Edit Link', 'edge-link-router' ) );
	}

	/**
	 * Render link form.
	 *
	 * @param Link   $link  Link object.
	 * @param string $title Page title.
	 * @return void
	 */
	private function render_form( Link $link, string $title ): void {
		$prefix          = $this->get_prefix();
		$home_url        = trailingslashit( home_url() );
		$status_codes    = $this->validator->get_allowed_status_codes();
		$is_edit         = ! empty( $link->id );
		?>
		<div class="wrap cfelr-admin">
			<h1><?php echo esc_html( $title ); ?></h1>

			<?php $this->render_notices(); ?>

			<form method="post" action="">
				<?php wp_nonce_field( 'cfelr_save_link', 'cfelr_link_nonce' ); ?>
				<?php if ( $is_edit ) : ?>
					<input type="hidden" name="link_id" value="<?php echo esc_attr( $link->id ); ?>">
				<?php endif; ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">
							<label for="slug"><?php esc_html_e( 'Slug', 'edge-link-router' ); ?> <span class="required">*</span></label>
						</th>
						<td>
							<div style="display: flex; align-items: center; gap: 0;">
								<span class="cfelr-url-prefix"><?php echo esc_html( $home_url . $prefix . '/' ); ?></span>
								<input type="text" name="slug" id="slug" value="<?php echo esc_attr( $link->slug ); ?>"
									class="regular-text" required pattern="[a-z0-9\-_]+" style="flex: 1;">
							</div>
							<p class="description">
								<?php esc_html_e( 'Only lowercase letters, numbers, hyphens, and underscores allowed.', 'edge-link-router' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="target_url"><?php esc_html_e( 'Target URL', 'edge-link-router' ); ?> <span class="required">*</span></label>
						</th>
						<td>
							<input type="url" name="target_url" id="target_url" value="<?php echo esc_url( $link->target_url ); ?>"
								class="large-text" required placeholder="https://example.com/page">
							<p class="description">
								<?php esc_html_e( 'The URL visitors will be redirected to.', 'edge-link-router' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="status_code"><?php esc_html_e( 'Redirect Type', 'edge-link-router' ); ?></label>
						</th>
						<td>
							<select name="status_code" id="status_code">
								<?php foreach ( $status_codes as $code ) : ?>
									<option value="<?php echo esc_attr( $code ); ?>" <?php selected( $link->status_code, $code ); ?>>
										<?php echo esc_html( $code . ' - ' . $this->get_status_code_label( $code ) ); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Status', 'edge-link-router' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="enabled" value="1" <?php checked( $link->enabled ); ?>>
								<?php esc_html_e( 'Enabled', 'edge-link-router' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Disabled links will return a 404 error.', 'edge-link-router' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'Options', 'edge-link-router' ); ?></th>
						<td>
							<label>
								<input type="checkbox" name="passthrough_query" value="1"
									<?php checked( ! empty( $link->options['passthrough_query'] ) ); ?>>
								<?php esc_html_e( 'Pass through query string', 'edge-link-router' ); ?>
							</label>
							<p class="description">
								<?php esc_html_e( 'Append the original query string to the target URL.', 'edge-link-router' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row"><?php esc_html_e( 'UTM Parameters', 'edge-link-router' ); ?></th>
						<td>
							<div class="cfelr-utm-fields">
								<div class="cfelr-utm-row">
									<label for="utm_source">utm_source</label>
									<input type="text" name="utm_source" id="utm_source" value="<?php echo esc_attr( $link->options['append_utm']['utm_source'] ?? '' ); ?>" placeholder="newsletter, twitter">
								</div>
								<div class="cfelr-utm-row">
									<label for="utm_medium">utm_medium</label>
									<input type="text" name="utm_medium" id="utm_medium" value="<?php echo esc_attr( $link->options['append_utm']['utm_medium'] ?? '' ); ?>" placeholder="email, social, cpc">
								</div>
								<div class="cfelr-utm-row">
									<label for="utm_campaign">utm_campaign</label>
									<input type="text" name="utm_campaign" id="utm_campaign" value="<?php echo esc_attr( $link->options['append_utm']['utm_campaign'] ?? '' ); ?>" placeholder="spring_sale, launch_2024">
								</div>
							</div>
							<p class="description">
								<?php esc_html_e( 'Optional. Added to target URL for Google Analytics tracking.', 'edge-link-router' ); ?>
							</p>
						</td>
					</tr>

					<tr>
						<th scope="row">
							<label for="notes"><?php esc_html_e( 'Notes', 'edge-link-router' ); ?></label>
						</th>
						<td>
							<textarea name="notes" id="notes" rows="3" class="large-text"><?php echo esc_textarea( $link->options['notes'] ?? '' ); ?></textarea>
							<p class="description">
								<?php esc_html_e( 'Internal notes (not visible to visitors).', 'edge-link-router' ); ?>
							</p>
						</td>
					</tr>
				</table>

				<p class="submit">
					<input type="submit" name="cfelr_save_link" class="button button-primary" value="<?php echo esc_attr( $is_edit ? __( 'Update Link', 'edge-link-router' ) : __( 'Create Link', 'edge-link-router' ) ); ?>">
					<a href="<?php echo esc_url( admin_url( 'admin.php?page=edge-link-router' ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'edge-link-router' ); ?></a>
				</p>
			</form>
		</div>
		<?php
	}

	/**
	 * Render import form.
	 *
	 * @return void
	 */
	private function render_import_form(): void {
		$results = get_transient( 'cfelr_import_results' );
		delete_transient( 'cfelr_import_results' );

		$error = get_transient( 'cfelr_import_error' );
		delete_transient( 'cfelr_import_error' );
		?>
		<div class="wrap cfelr-admin">
			<h1><?php esc_html_e( 'Import Links', 'edge-link-router' ); ?></h1>

			<?php if ( $error ) : ?>
				<div class="notice notice-error is-dismissible">
					<p><?php echo esc_html( $error ); ?></p>
				</div>
			<?php endif; ?>

			<?php if ( $results ) : ?>
				<div class="notice notice-<?php echo empty( $results['errors'] ) ? 'success' : 'warning'; ?> is-dismissible">
					<p>
						<?php
						printf(
							/* translators: %1$d: created count, %2$d: updated count, %3$d: error count */
							esc_html__( 'Import complete: %1$d created, %2$d updated, %3$d errors.', 'edge-link-router' ),
							(int) $results['created'],
							(int) $results['updated'],
							count( $results['errors'] )
						);
						?>
					</p>
					<?php if ( ! empty( $results['errors'] ) ) : ?>
						<details>
							<summary><?php esc_html_e( 'View errors', 'edge-link-router' ); ?></summary>
							<ul>
								<?php foreach ( $results['errors'] as $err ) : ?>
									<li><?php echo esc_html( $err ); ?></li>
								<?php endforeach; ?>
							</ul>
						</details>
					<?php endif; ?>
				</div>
			<?php endif; ?>

			<div class="cfelr-card">
				<h2><?php esc_html_e( 'Upload CSV File', 'edge-link-router' ); ?></h2>

				<form method="post" enctype="multipart/form-data">
					<?php wp_nonce_field( 'cfelr_import', 'cfelr_import_nonce' ); ?>

					<p>
						<input type="file" name="csv_file" accept=".csv" required>
					</p>

					<p>
						<input type="submit" name="cfelr_import" class="button button-primary" value="<?php esc_attr_e( 'Import', 'edge-link-router' ); ?>">
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=edge-link-router' ) ); ?>" class="button"><?php esc_html_e( 'Cancel', 'edge-link-router' ); ?></a>
					</p>
				</form>

				<h3><?php esc_html_e( 'CSV Format', 'edge-link-router' ); ?></h3>
				<p><?php esc_html_e( 'The CSV file must include a header row with the following columns:', 'edge-link-router' ); ?></p>
				<code>slug,target_url,status_code,enabled,passthrough_query,append_utm_json,notes</code>

				<h4><?php esc_html_e( 'Example:', 'edge-link-router' ); ?></h4>
				<pre>slug,target_url,status_code,enabled,passthrough_query,append_utm_json,notes
example,https://example.com,302,1,0,,My first link
docs,https://docs.example.com,301,1,1,"{""utm_source"":""website""}",Documentation</pre>

				<p class="description">
					<?php esc_html_e( 'Note: append_utm_json should be a JSON object or empty. Use double quotes inside JSON.', 'edge-link-router' ); ?>
				</p>
			</div>
		</div>
		<?php
	}

	/**
	 * Render admin notices.
	 *
	 * @return void
	 */
	private function render_notices(): void {
		// Form validation errors.
		$errors = get_transient( 'cfelr_form_errors' );
		delete_transient( 'cfelr_form_errors' );

		if ( $errors ) {
			echo '<div class="notice notice-error is-dismissible"><ul>';
			foreach ( $errors as $error ) {
				echo '<li>' . esc_html( $error ) . '</li>';
			}
			echo '</ul></div>';
		}

		// Success messages.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['created'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Link created successfully.', 'edge-link-router' ) . '</p></div>';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['updated'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Link updated successfully.', 'edge-link-router' ) . '</p></div>';
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['deleted'] ) ) {
			echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Link deleted successfully.', 'edge-link-router' ) . '</p></div>';
		}

		// Bulk action messages.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['bulk_action'] ) && isset( $_GET['count'] ) ) {
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$action = sanitize_text_field( wp_unslash( $_GET['bulk_action'] ) );
			// phpcs:ignore WordPress.Security.NonceVerification.Recommended
			$count = (int) $_GET['count'];

			$messages = array(
				'enable'  => sprintf(
					/* translators: %d: number of links */
					_n( '%d link enabled.', '%d links enabled.', $count, 'edge-link-router' ),
					$count
				),
				'disable' => sprintf(
					/* translators: %d: number of links */
					_n( '%d link disabled.', '%d links disabled.', $count, 'edge-link-router' ),
					$count
				),
				'delete'  => sprintf(
					/* translators: %d: number of links */
					_n( '%d link deleted.', '%d links deleted.', $count, 'edge-link-router' ),
					$count
				),
			);

			if ( isset( $messages[ $action ] ) ) {
				echo '<div class="notice notice-success is-dismissible"><p>' . esc_html( $messages[ $action ] ) . '</p></div>';
			}
		}
	}

	/**
	 * Get status code label.
	 *
	 * @param int $code Status code.
	 * @return string
	 */
	private function get_status_code_label( int $code ): string {
		$labels = array(
			301 => __( 'Permanent Redirect', 'edge-link-router' ),
			302 => __( 'Temporary Redirect (default)', 'edge-link-router' ),
			307 => __( 'Temporary Redirect (strict)', 'edge-link-router' ),
			308 => __( 'Permanent Redirect (strict)', 'edge-link-router' ),
		);

		return $labels[ $code ] ?? '';
	}

	/**
	 * Get redirect prefix.
	 *
	 * @return string
	 */
	private function get_prefix(): string {
		return SettingsPage::get_prefix();
	}

	/**
	 * Maybe schedule edge publish if edge is enabled.
	 *
	 * @return void
	 */
	private function maybe_schedule_publish(): void {
		\CFELR\WP\Cron::maybe_schedule_publish();
	}
}
