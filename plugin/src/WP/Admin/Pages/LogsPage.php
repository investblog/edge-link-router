<?php
/**
 * Logs Admin Page.
 *
 * @package EdgeLinkRouter
 */

namespace CFELR\WP\Admin\Pages;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Logs page.
 */
class LogsPage {

	/**
	 * Log type labels.
	 *
	 * @var array
	 */
	private const TYPE_LABELS = array(
		'reconcile'     => 'Reconcile',
		'stats_cleanup' => 'Stats Cleanup',
		'publish'       => 'Publish',
		'republish'     => 'Republish',
		'enable'        => 'Enable Edge',
		'disable'       => 'Disable Edge',
		'warning'       => 'Warning',
		'error'         => 'Error',
	);

	/**
	 * Render the page.
	 *
	 * @return void
	 */
	public function render(): void {
		// Handle actions first.
		$this->handle_clear_logs();

		$logs   = get_option( 'cfelr_reconcile_log', array() );
		$logs   = array_reverse( $logs ); // Most recent first.
		$health = new \CFELR\Integrations\Cloudflare\Health();
		$status = $health->get_status();
		?>
		<div class="wrap cfelr-admin">
			<h1><?php esc_html_e( 'Logs', 'edge-link-router' ); ?></h1>

			<!-- Status Summary -->
			<div class="cfelr-card" style="margin-bottom: 20px;">
				<h2 style="margin-top: 0;"><?php esc_html_e( 'Status Summary', 'edge-link-router' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Edge Status', 'edge-link-router' ); ?></th>
						<td>
							<?php
							$state_labels = array(
								'wp-only'  => __( 'WP Only', 'edge-link-router' ),
								'active'   => __( 'Edge Active', 'edge-link-router' ),
								'degraded' => __( 'Edge Degraded', 'edge-link-router' ),
							);
							$state_colors = array(
								'wp-only'  => '#666',
								'active'   => '#00a32a',
								'degraded' => '#dba617',
							);
							$state = $status['state'] ?? 'wp-only';
							?>
							<span style="color: <?php echo esc_attr( $state_colors[ $state ] ?? '#666' ); ?>; font-weight: 600;">
								<?php echo esc_html( $state_labels[ $state ] ?? $state ); ?>
							</span>
							<?php if ( ! empty( $status['message'] ) ) : ?>
								<br><small class="description"><?php echo esc_html( $status['message'] ); ?></small>
							<?php endif; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Last Reconcile', 'edge-link-router' ); ?></th>
						<td>
							<?php
							if ( ! empty( $status['last_check'] ) ) {
								echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $status['last_check'] ) ) );
							} else {
								esc_html_e( 'Never', 'edge-link-router' );
							}
							?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Next Scheduled', 'edge-link-router' ); ?></th>
						<td>
							<?php
							$next = wp_next_scheduled( 'cfelr_reconcile' );
							if ( $next ) {
								echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $next ) );
							} else {
								esc_html_e( 'Not scheduled', 'edge-link-router' );
							}
							?>
						</td>
					</tr>
				</table>
			</div>

			<p><?php esc_html_e( 'Recent activity logs (last 50 entries).', 'edge-link-router' ); ?></p>

			<?php if ( empty( $logs ) ) : ?>
				<div class="cfelr-empty-state">
					<span class="dashicons dashicons-list-view"></span>
					<h3><?php esc_html_e( 'No log entries yet', 'edge-link-router' ); ?></h3>
					<p><?php esc_html_e( 'Logs will appear here when the system performs health checks, publishes to the edge, or cleans up stats. Check back after some activity.', 'edge-link-router' ); ?></p>
				</div>
			<?php else : ?>
				<table class="widefat fixed striped">
					<thead>
						<tr>
							<th style="width: 180px;"><?php esc_html_e( 'Time', 'edge-link-router' ); ?></th>
							<th style="width: 120px;"><?php esc_html_e( 'Type', 'edge-link-router' ); ?></th>
							<th><?php esc_html_e( 'Message', 'edge-link-router' ); ?></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $logs as $log ) : ?>
							<tr>
								<td>
									<?php
									$time = strtotime( $log['time'] );
									echo esc_html( wp_date( 'Y-m-d H:i:s', $time ) );
									?>
								</td>
								<td>
									<?php
									$type       = $log['type'] ?? 'info';
									$type_label = self::TYPE_LABELS[ $type ] ?? ucfirst( $type );
									?>
									<span class="cfelr-log-type cfelr-log-type-<?php echo esc_attr( $type ); ?>">
										<?php echo esc_html( $type_label ); ?>
									</span>
								</td>
								<td><?php echo esc_html( $log['message'] ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php endif; ?>

			<?php if ( ! empty( $logs ) ) : ?>
				<form method="post" action="" style="margin-top: 20px;">
					<?php wp_nonce_field( 'cfelr_clear_logs', 'cfelr_clear_logs_nonce' ); ?>
					<button type="submit" name="cfelr_clear_logs" class="button button-secondary cfelr-clear-logs">
						<span class="dashicons dashicons-trash cfelr-btn-icon"></span>
						<?php esc_html_e( 'Clear Logs', 'edge-link-router' ); ?>
					</button>
				</form>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Handle clear logs action.
	 *
	 * @return void
	 */
	private function handle_clear_logs(): void {
		if ( ! isset( $_POST['cfelr_clear_logs'] ) ) {
			return;
		}

		if ( ! isset( $_POST['cfelr_clear_logs_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cfelr_clear_logs_nonce'] ) ), 'cfelr_clear_logs' ) ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Security check failed.', 'edge-link-router' ) . '</p></div>';
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			echo '<div class="notice notice-error is-dismissible"><p>' . esc_html__( 'Permission denied.', 'edge-link-router' ) . '</p></div>';
			return;
		}

		delete_option( 'cfelr_reconcile_log' );

		echo '<div class="notice notice-success is-dismissible"><p>' . esc_html__( 'Logs cleared successfully.', 'edge-link-router' ) . '</p></div>';
	}
}
