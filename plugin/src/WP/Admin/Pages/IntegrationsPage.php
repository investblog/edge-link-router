<?php
/**
 * Integrations Admin Page.
 *
 * @package EdgeLinkRouter
 */

namespace CFELR\WP\Admin\Pages;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CFELR\Integrations\Cloudflare\Deployer;
use CFELR\Integrations\Cloudflare\Diagnostics;
use CFELR\Integrations\Cloudflare\Health;
use CFELR\Integrations\Cloudflare\IntegrationState;
use CFELR\Integrations\Cloudflare\TokenStorage;

/**
 * Integrations page.
 */
class IntegrationsPage {

	/**
	 * Diagnostics instance.
	 *
	 * @var Diagnostics
	 */
	private Diagnostics $diagnostics;

	/**
	 * Health instance.
	 *
	 * @var Health
	 */
	private Health $health;

	/**
	 * Token storage instance.
	 *
	 * @var TokenStorage
	 */
	private TokenStorage $token_storage;

	/**
	 * Integration state.
	 *
	 * @var IntegrationState
	 */
	private IntegrationState $state;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->diagnostics   = new Diagnostics();
		$this->health        = new Health();
		$this->token_storage = new TokenStorage();
		$this->state         = new IntegrationState();
	}

	/**
	 * Render the page.
	 *
	 * @return void
	 */
	public function render(): void {
		// Handle form submissions.
		$this->handle_actions();

		$host_info        = $this->diagnostics->auto_detect_host();
		$health_status    = $this->health->get_status();
		$has_token        = $this->token_storage->has_token();
		$edge_enabled     = $this->state->is_edge_enabled();
		$selected_host    = $this->get_selected_host( $host_info );
		$public_checks    = null;
		$auth_checks      = null;
		$overall_status   = null;
		$auth_status      = null;

		// Run public diagnostics if requested.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['run_diagnostics'] ) ) {
			$public_checks  = $this->diagnostics->run_public_checks( $selected_host );
			$overall_status = $this->diagnostics->get_overall_status( $public_checks );
		}

		// Run authorized diagnostics if requested and token exists.
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		if ( isset( $_GET['run_auth_diagnostics'] ) && $has_token ) {
			$auth_checks = $this->diagnostics->run_authorized_checks( $selected_host );
			$auth_status = $this->diagnostics->get_overall_status( $auth_checks );
		}

		?>
		<div class="wrap cfelr-admin">
			<h1><?php esc_html_e( 'Integrations', 'edge-link-router' ); ?></h1>

			<!-- Cloudflare Section -->
			<div class="cfelr-card">
				<h2>
					<svg class="cfelr-brand-icon" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><path d="M19.027 11.311c-.056 0-.106.042-.127.097l-.337 1.156c-.148.499-.092.956.154 1.295.226.311.605.491 1.063.512l1.842.11a.16.16 0 0 1 .134.07.2.2 0 0 1 .021.152.24.24 0 0 1-.204.153l-1.92.11c-1.041.049-2.16.873-2.553 1.884l-.141.353c-.028.069.021.138.098.138h6.598a.17.17 0 0 0 .17-.125 4.7 4.7 0 0 0 .175-1.26c0-2.561-2.124-4.652-4.734-4.652-.077 0-.162 0-.24.007" fill="#fbad41"/><path d="M16.509 16.767c.148-.499.091-.956-.155-1.295-.225-.311-.605-.492-1.062-.512l-8.659-.111a.16.16 0 0 1-.134-.07.2.2 0 0 1-.02-.152.24.24 0 0 1 .203-.152l8.737-.11c1.034-.05 2.159-.873 2.553-1.884l.5-1.28a.27.27 0 0 0 .013-.167c-.562-2.506-2.834-4.375-5.55-4.375-2.504 0-4.628 1.592-5.388 3.8a2.6 2.6 0 0 0-1.793-.49c-1.203.117-2.167 1.065-2.286 2.25a2.6 2.6 0 0 0 .063.878C1.57 13.153 0 14.731 0 16.677q.002.26.035.519a.17.17 0 0 0 .169.145h15.981a.22.22 0 0 0 .204-.152z" fill="#f6821f"/></svg>
					<?php esc_html_e( 'Cloudflare Edge', 'edge-link-router' ); ?>
				</h2>
				<p><?php esc_html_e( 'Accelerate your redirects with Cloudflare Workers. Redirects happen at the edge, before reaching your WordPress server.', 'edge-link-router' ); ?></p>

				<!-- Health Badge -->
				<div style="margin: 15px 0;">
					<?php $this->render_health_badge( $health_status ); ?>
				</div>

				<?php if ( $edge_enabled ) : ?>
					<?php
					// Check for route mismatch.
					$route_mismatch = $this->health->get_route_mismatch();
					?>
					<?php if ( $route_mismatch ) : ?>
						<!-- Route Mismatch Warning -->
						<div class="cfelr-route-mismatch-warning">
							<p>
								<span class="dashicons dashicons-warning"></span>
								<strong><?php esc_html_e( 'Route Pattern Mismatch Detected', 'edge-link-router' ); ?></strong>
							</p>
							<p>
								<?php esc_html_e( 'The Cloudflare route pattern does not match the expected pattern. Redirects may not work correctly.', 'edge-link-router' ); ?>
							</p>
							<table>
								<tr>
									<td><strong><?php esc_html_e( 'Expected:', 'edge-link-router' ); ?></strong></td>
									<td><code><?php echo esc_html( $route_mismatch['expected'] ); ?></code></td>
								</tr>
								<tr>
									<td><strong><?php esc_html_e( 'Actual:', 'edge-link-router' ); ?></strong></td>
									<td><code><?php echo esc_html( $route_mismatch['actual'] ); ?></code></td>
								</tr>
							</table>
							<p>
								<form method="post" style="display: inline;">
									<?php wp_nonce_field( 'cfelr_fix_route', 'cfelr_fix_route_nonce' ); ?>
									<button type="submit" name="cfelr_fix_route" class="button button-primary">
										<span class="dashicons dashicons-admin-tools cfelr-btn-icon"></span>
										<?php esc_html_e( 'Fix Route Now', 'edge-link-router' ); ?>
									</button>
								</form>
							</p>
						</div>
					<?php endif; ?>

					<!-- Edge Status Info -->
					<div class="cfelr-subsection cfelr-edge-status">
						<h3><?php esc_html_e( 'Edge Status', 'edge-link-router' ); ?></h3>
						<table class="form-table" role="presentation">
							<tr>
								<th scope="row"><?php esc_html_e( 'Route Pattern', 'edge-link-router' ); ?></th>
								<td><code><?php echo esc_html( $this->state->get_route_pattern() ); ?></code></td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Last Publish', 'edge-link-router' ); ?></th>
								<td>
									<?php
									$last_publish = $this->state->get_last_publish();
									if ( $last_publish ) {
										echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $last_publish ) ) );
									} else {
										esc_html_e( 'Never', 'edge-link-router' );
									}
									?>
								</td>
							</tr>
							<tr>
								<th scope="row"><?php esc_html_e( 'Last Health Check', 'edge-link-router' ); ?></th>
								<td>
									<?php
									$last_check = $health_status['last_check'] ?? null;
									if ( $last_check ) {
										echo esc_html( wp_date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), strtotime( $last_check ) ) );
									} else {
										esc_html_e( 'Never', 'edge-link-router' );
									}
									?>
								</td>
							</tr>
						</table>

						<?php settings_errors( 'cfelr_edge_messages' ); ?>

						<p style="margin-top: 15px;">
							<form method="post" style="display: inline;">
								<?php wp_nonce_field( 'cfelr_republish', 'cfelr_republish_nonce' ); ?>
								<button type="submit" name="cfelr_republish" class="button button-secondary">
									<span class="dashicons dashicons-update cfelr-btn-icon"></span>
									<?php esc_html_e( 'Republish Snapshot', 'edge-link-router' ); ?>
								</button>
							</form>

							<form method="post" style="display: inline; margin-left: 10px;">
								<?php wp_nonce_field( 'cfelr_disable_edge', 'cfelr_disable_edge_nonce' ); ?>
								<button type="submit" name="cfelr_disable_edge" class="button button-secondary" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to disable edge mode?', 'edge-link-router' ) ); ?>');">
									<span class="dashicons dashicons-no cfelr-btn-icon"></span>
									<?php esc_html_e( 'Disable Edge', 'edge-link-router' ); ?>
								</button>
							</form>
						</p>
					</div>
				<?php endif; ?>

				<!-- Domain Auto-detect -->
				<div class="cfelr-subsection">
					<h3><?php esc_html_e( 'Domain Configuration', 'edge-link-router' ); ?></h3>
					<table class="form-table" role="presentation">
						<tr>
							<th scope="row"><?php esc_html_e( 'Detected Domain', 'edge-link-router' ); ?></th>
							<td>
								<code><?php echo esc_html( $host_info['primary'] ); ?></code>
								<?php if ( $host_info['alias'] ) : ?>
									<br>
									<span class="description">
										<?php
										printf(
											/* translators: %s: alias domain */
											esc_html__( 'Alias: %s', 'edge-link-router' ),
											'<code>' . esc_html( $host_info['alias'] ) . '</code>'
										);
										?>
									</span>
								<?php endif; ?>
							</td>
						</tr>
						<tr>
							<th scope="row"><?php esc_html_e( 'Home URL', 'edge-link-router' ); ?></th>
							<td><code><?php echo esc_html( $host_info['home'] ); ?></code></td>
						</tr>
					</table>
				</div>

				<!-- Diagnostics Section -->
				<div class="cfelr-subsection">
					<h3><?php esc_html_e( 'Diagnostics', 'edge-link-router' ); ?></h3>

					<?php if ( $public_checks ) : ?>
						<!-- Results -->
						<div class="cfelr-diagnostics-results">
							<div class="cfelr-overall-status cfelr-overall-<?php echo esc_attr( $overall_status ); ?>">
								<?php
								$status_labels = array(
									'ok'   => __( 'All checks passed', 'edge-link-router' ),
									'warn' => __( 'Some warnings detected', 'edge-link-router' ),
									'fail' => __( 'Issues detected', 'edge-link-router' ),
								);
								echo esc_html( $status_labels[ $overall_status ] ?? '' );
								?>
							</div>

							<ul class="cfelr-diagnostics-list">
								<?php foreach ( $public_checks as $check ) : ?>
									<li>
										<div class="cfelr-check-status cfelr-check-<?php echo esc_attr( $check['status'] ); ?>">
											<?php $this->render_status_icon( $check['status'] ); ?>
										</div>
										<div class="cfelr-check-content">
											<div class="cfelr-check-name"><?php echo esc_html( $check['label'] ); ?></div>
											<div class="cfelr-check-message"><?php echo esc_html( $check['message'] ); ?></div>
											<?php if ( $check['fix_hint'] ) : ?>
												<div class="cfelr-check-hint">
													<strong><?php esc_html_e( 'Tip:', 'edge-link-router' ); ?></strong>
													<?php echo esc_html( $check['fix_hint'] ); ?>
												</div>
											<?php endif; ?>
										</div>
									</li>
								<?php endforeach; ?>
							</ul>
						</div>
					<?php else : ?>
						<p class="description">
							<?php esc_html_e( 'Run diagnostics to check if your site is ready for Cloudflare edge integration.', 'edge-link-router' ); ?>
						</p>
					<?php endif; ?>

					<p style="margin-top: 15px;">
						<a href="<?php echo esc_url( add_query_arg( 'run_diagnostics', '1' ) ); ?>" class="button button-secondary">
							<span class="dashicons dashicons-search cfelr-btn-icon"></span>
							<?php echo $public_checks ? esc_html__( 'Run Again', 'edge-link-router' ) : esc_html__( 'Run Diagnostics', 'edge-link-router' ); ?>
						</a>
					</p>
				</div>

				<!-- Token Section -->
				<div class="cfelr-subsection">
					<h3><?php esc_html_e( 'Cloudflare API Token', 'edge-link-router' ); ?></h3>

					<?php settings_errors( 'cfelr_messages' ); ?>

					<?php if ( $has_token ) : ?>
						<p class="cfelr-token-status cfelr-token-configured">
							<span class="dashicons dashicons-yes-alt"></span>
							<?php esc_html_e( 'API token configured.', 'edge-link-router' ); ?>
						</p>

						<!-- Authorized Diagnostics -->
						<?php if ( $auth_checks ) : ?>
							<div class="cfelr-diagnostics-results" style="margin: 15px 0;">
								<h4><?php esc_html_e( 'Cloudflare API Checks', 'edge-link-router' ); ?></h4>
								<div class="cfelr-overall-status cfelr-overall-<?php echo esc_attr( $auth_status ); ?>">
									<?php
									$status_labels = array(
										'ok'   => __( 'All API checks passed', 'edge-link-router' ),
										'warn' => __( 'Some warnings detected', 'edge-link-router' ),
										'fail' => __( 'Issues detected', 'edge-link-router' ),
									);
									echo esc_html( $status_labels[ $auth_status ] ?? '' );
									?>
								</div>

								<ul class="cfelr-diagnostics-list">
									<?php foreach ( $auth_checks as $check ) : ?>
										<?php
										if ( str_starts_with( $check['name'], '_' ) ) {
											continue;
										}
										?>
										<li>
											<div class="cfelr-check-status cfelr-check-<?php echo esc_attr( $check['status'] ); ?>">
												<?php $this->render_status_icon( $check['status'] ); ?>
											</div>
											<div class="cfelr-check-content">
												<div class="cfelr-check-name"><?php echo esc_html( $check['label'] ); ?></div>
												<div class="cfelr-check-message"><?php echo esc_html( $check['message'] ); ?></div>
												<?php if ( ! empty( $check['fix_hint'] ) ) : ?>
													<div class="cfelr-check-hint">
														<strong><?php esc_html_e( 'Tip:', 'edge-link-router' ); ?></strong>
														<?php echo esc_html( $check['fix_hint'] ); ?>
													</div>
												<?php endif; ?>
											</div>
										</li>
									<?php endforeach; ?>
								</ul>
							</div>
						<?php endif; ?>

						<p style="margin-top: 15px;">
							<a href="<?php echo esc_url( add_query_arg( 'run_auth_diagnostics', '1' ) ); ?>" class="button button-secondary">
								<span class="dashicons dashicons-cloud cfelr-btn-icon"></span>
								<?php echo $auth_checks ? esc_html__( 'Re-check API', 'edge-link-router' ) : esc_html__( 'Check API Connection', 'edge-link-router' ); ?>
							</a>
						</p>

						<hr style="margin: 20px 0;">

						<form method="post" style="margin-top: 15px;">
							<?php wp_nonce_field( 'cfelr_remove_token', 'cfelr_remove_token_nonce' ); ?>
							<p class="description"><?php esc_html_e( 'Remove the API token to disconnect from Cloudflare.', 'edge-link-router' ); ?></p>
							<p>
								<button type="submit" name="cfelr_remove_token" class="button button-secondary" onclick="return confirm('<?php echo esc_js( __( 'Are you sure you want to remove the API token?', 'edge-link-router' ) ); ?>');">
									<?php esc_html_e( 'Remove Token', 'edge-link-router' ); ?>
								</button>
							</p>
						</form>
					<?php else : ?>
						<div class="cfelr-token-instructions">
							<p><strong><?php esc_html_e( 'How to create an API token:', 'edge-link-router' ); ?></strong></p>
							<ol>
								<li>
									<?php
									printf(
										/* translators: %s: link to Cloudflare dashboard */
										esc_html__( 'Go to %s (User API Tokens page)', 'edge-link-router' ),
										'<a href="https://dash.cloudflare.com/profile/api-tokens" target="_blank" rel="noopener noreferrer">dash.cloudflare.com/profile/api-tokens</a>'
									);
									?>
								</li>
								<li><?php esc_html_e( 'Click "Create Token"', 'edge-link-router' ); ?></li>
								<li><?php esc_html_e( 'Scroll down and click "Create Custom Token"', 'edge-link-router' ); ?></li>
								<li>
									<?php esc_html_e( 'Set token name (e.g., "Edge Link Router")', 'edge-link-router' ); ?>
								</li>
								<li>
									<?php esc_html_e( 'Add 4 permissions (click "+ Add more" after each):', 'edge-link-router' ); ?>
									<ul style="list-style: disc; margin: 5px 0 5px 20px;">
										<li><code><?php esc_html_e( 'Account / Workers Scripts / Edit', 'edge-link-router' ); ?></code></li>
										<li><code><?php esc_html_e( 'Zone / Workers Routes / Edit', 'edge-link-router' ); ?></code></li>
										<li><code><?php esc_html_e( 'Zone / Zone / Read', 'edge-link-router' ); ?></code></li>
										<li><code><?php esc_html_e( 'Zone / DNS / Read', 'edge-link-router' ); ?></code></li>
									</ul>
								</li>
								<li>
									<?php esc_html_e( 'In "Account Resources": Include → Your account', 'edge-link-router' ); ?>
								</li>
								<li>
									<?php esc_html_e( 'In "Zone Resources": Include → Specific zone → Your domain', 'edge-link-router' ); ?>
								</li>
								<li><?php esc_html_e( 'Click "Continue to summary" → "Create Token"', 'edge-link-router' ); ?></li>
								<li><?php esc_html_e( 'Copy the token (it is shown only once!)', 'edge-link-router' ); ?></li>
							</ol>

							<details style="margin: 15px 0;">
								<summary style="cursor: pointer; color: #2271b1;">
									<?php esc_html_e( 'Required permissions summary', 'edge-link-router' ); ?>
								</summary>
								<table class="widefat striped" style="margin-top: 10px; max-width: 400px;">
									<thead>
										<tr>
											<th><?php esc_html_e( 'Permission', 'edge-link-router' ); ?></th>
											<th><?php esc_html_e( 'Level', 'edge-link-router' ); ?></th>
										</tr>
									</thead>
									<tbody>
										<tr>
											<td><?php esc_html_e( 'Workers Scripts: Edit', 'edge-link-router' ); ?></td>
											<td><?php esc_html_e( 'Account', 'edge-link-router' ); ?></td>
										</tr>
										<tr>
											<td><?php esc_html_e( 'Workers Routes: Edit', 'edge-link-router' ); ?></td>
											<td><?php esc_html_e( 'Zone', 'edge-link-router' ); ?></td>
										</tr>
										<tr>
											<td><?php esc_html_e( 'Zone: Read', 'edge-link-router' ); ?></td>
											<td><?php esc_html_e( 'Zone', 'edge-link-router' ); ?></td>
										</tr>
										<tr>
											<td><?php esc_html_e( 'DNS: Read', 'edge-link-router' ); ?></td>
											<td><?php esc_html_e( 'Zone', 'edge-link-router' ); ?></td>
										</tr>
									</tbody>
								</table>
								<p class="description" style="margin-top: 10px;">
									<?php esc_html_e( 'Account-level permissions apply to your entire Cloudflare account. Zone-level permissions can be restricted to a specific domain.', 'edge-link-router' ); ?>
								</p>
							</details>
						</div>

						<p>
							<a href="https://dash.cloudflare.com/profile/api-tokens" target="_blank" rel="noopener noreferrer" class="button button-secondary">
								<?php esc_html_e( 'Open Cloudflare Dashboard', 'edge-link-router' ); ?>
								<span class="dashicons dashicons-external cfelr-btn-icon--after"></span>
							</a>
						</p>

						<form method="post" style="margin-top: 20px; max-width: 500px;">
							<?php wp_nonce_field( 'cfelr_save_token', 'cfelr_token_nonce' ); ?>
							<table class="form-table" role="presentation">
								<tr>
									<th scope="row">
										<label for="cf_token"><?php esc_html_e( 'API Token', 'edge-link-router' ); ?></label>
									</th>
									<td>
										<input type="password" name="cf_token" id="cf_token" class="regular-text" autocomplete="off" required>
										<p class="description">
											<?php esc_html_e( 'Your token will be encrypted before storage.', 'edge-link-router' ); ?>
										</p>
									</td>
								</tr>
							</table>
							<p>
								<button type="submit" name="cfelr_save_token" class="button button-primary">
									<?php esc_html_e( 'Save Token', 'edge-link-router' ); ?>
								</button>
							</p>
						</form>
					<?php endif; ?>
				</div>

				<!-- Enable Edge Section -->
				<?php if ( ! $edge_enabled ) : ?>
				<div class="cfelr-subsection">
					<h3><?php esc_html_e( 'Enable Edge Mode', 'edge-link-router' ); ?></h3>

					<?php
					// Check all conditions for enabling edge.
					$public_ok    = $public_checks && $overall_status !== 'fail';
					$auth_ok      = $auth_checks && $auth_status !== 'fail';
					$can_enable   = $has_token && $public_ok && $auth_ok;
					$missing_step = null;

					if ( ! $has_token ) {
						$missing_step = __( 'Configure your API token first.', 'edge-link-router' );
					} elseif ( ! $public_checks ) {
						$missing_step = __( 'Run public diagnostics first.', 'edge-link-router' );
					} elseif ( $overall_status === 'fail' ) {
						$missing_step = __( 'Fix the public diagnostic issues first.', 'edge-link-router' );
					} elseif ( ! $auth_checks ) {
						$missing_step = __( 'Run API diagnostics first.', 'edge-link-router' );
					} elseif ( $auth_status === 'fail' ) {
						$missing_step = __( 'Fix the API diagnostic issues first.', 'edge-link-router' );
					}

					if ( $missing_step ) :
						?>
						<p class="description"><?php echo esc_html( $missing_step ); ?></p>
					<?php else : ?>
						<p class="description cfelr-status-ok">
							<span class="dashicons dashicons-yes-alt"></span>
							<?php esc_html_e( 'All checks passed. Ready to enable edge mode.', 'edge-link-router' ); ?>
						</p>
					<?php endif; ?>

					<?php settings_errors( 'cfelr_enable_edge_messages' ); ?>

					<form method="post" style="margin-top: 15px;">
						<?php wp_nonce_field( 'cfelr_enable_edge', 'cfelr_enable_edge_nonce' ); ?>
						<button type="submit" name="cfelr_enable_edge" class="button button-primary" <?php disabled( ! $can_enable ); ?>>
							<span class="dashicons dashicons-cloud-saved cfelr-btn-icon"></span>
							<?php esc_html_e( 'Enable Edge Mode', 'edge-link-router' ); ?>
						</button>
					</form>
				</div>
				<?php endif; ?>
			</div>

			<!-- 301.st Section -->
			<div class="cfelr-card">
				<h2>
					<svg class="cfelr-brand-icon" width="32" height="32" viewBox="0 0 32 32" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true" focusable="false"><path d="M16.363 22.856c-.016 1.262-.091 2.519-.54 3.723-.837 2.251-2.464 3.573-4.79 4.043-.627.126-1.28.173-1.92.18-2.978.028-5.956.03-8.934.04H0V24.13h.264c2.733.013 5.466.021 8.199.004a4.8 4.8 0 0 0 1.301-.197 2.04 2.04 0 0 0 1.501-1.903 36 36 0 0 0 .04-2.757c-.035-1.268-.947-2.173-2.215-2.219-1.052-.037-2.105-.039-3.158-.042-1.89-.006-3.78-.002-5.67-.01H0v-5.313h.24c2.59 0 5.18.007 7.77-.008a3.6 3.6 0 0 0 1.13-.19c.748-.26 1.205-.997 1.213-1.846q.005-.705 0-1.41c-.002-1.08-.732-1.815-1.805-1.816H.042v-5.27h.211c3.794.012 7.588.008 11.383.016.785.002 1.578.036 2.289.432.838.466 1.373 1.175 1.425 2.144.059 1.129.047 2.26.048 3.39.002.794-.005 1.589-.045 2.38-.055 1.092-.332 2.119-1.044 2.98-.582.705-1.303 1.205-2.208 1.41-.011.003-.02.018-.051.045.11.022.206.038.3.061 1.962.497 3.243 1.688 3.672 3.694.158.736.25 1.493.296 2.244.057.967.058 1.938.045 2.906m10.365.063c-.003 1.248-.172 2.475-.735 3.61-1.059 2.134-2.773 3.455-5.056 4.06-1.043.275-2.107.276-3.187.246v-5.028c.326-.023.65-.032.97-.071 1.36-.167 2.24-.955 2.755-2.191.341-.82.487-1.691.505-2.57.047-2.265.065-4.53.078-6.794a74 74 0 0 0-.043-3.13c-.036-.984-.157-1.958-.537-2.883-.53-1.29-1.545-1.99-2.938-2.004-.527-.005-1.054 0-1.591 0V1.174c.022-.008.04-.022.06-.022.954.013 1.914-.028 2.863.053a7.3 7.3 0 0 1 4.446 1.97 7.3 7.3 0 0 1 2.285 4.19c.08.466.128.944.129 1.417q.012 7.222-.004 14.446zM32 1.181v29.646c-.067.004-.127.012-.187.012q-1.423.004-2.845.001-.179-.001-.353-.041a.46.46 0 0 1-.38-.4c-.021-.138-.02-.28-.02-.42-.002-9.344 0-18.687-.008-28.031 0-.542.228-.761.762-.764.773-.005 1.546-.002 2.319-.003z" fill="#f6821f"/></svg>
					<?php esc_html_e( '301.st Integration', 'edge-link-router' ); ?>
				</h2>
				<p><?php esc_html_e( 'Advanced routing features: geo-targeting, A/B testing, multi-domain management, and detailed analytics.', 'edge-link-router' ); ?></p>

				<div class="cfelr-placeholder">
					<p><?php esc_html_e( 'Coming in a future release. Features will include:', 'edge-link-router' ); ?></p>
					<ul>
						<li><?php esc_html_e( 'Sync links to 301.st', 'edge-link-router' ); ?></li>
						<li><?php esc_html_e( 'Advanced routing rules', 'edge-link-router' ); ?></li>
						<li><?php esc_html_e( 'Detailed click analytics', 'edge-link-router' ); ?></li>
						<li><?php esc_html_e( 'Geo-targeting and A/B testing', 'edge-link-router' ); ?></li>
					</ul>
					<p>
						<a href="https://301.st" target="_blank" rel="noopener noreferrer" class="button">
							<?php esc_html_e( 'Learn more about 301.st', 'edge-link-router' ); ?>
							<span class="dashicons dashicons-external cfelr-btn-icon--after"></span>
						</a>
					</p>
				</div>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle form submissions that require redirects.
	 * Called from AdminMenu::handle_early_actions() before any output.
	 *
	 * @return void
	 */
	public function handle_early_actions(): void {
		// Save token (redirects after success).
		if ( isset( $_POST['cfelr_save_token'] ) ) {
			if ( ! isset( $_POST['cfelr_token_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cfelr_token_nonce'] ) ), 'cfelr_save_token' ) ) {
				add_settings_error( 'cfelr_messages', 'nonce_error', __( 'Security check failed.', 'edge-link-router' ), 'error' );
				return;
			}

			$token = isset( $_POST['cf_token'] ) ? sanitize_text_field( wp_unslash( $_POST['cf_token'] ) ) : '';

			if ( empty( $token ) ) {
				add_settings_error( 'cfelr_messages', 'empty_token', __( 'Please enter an API token.', 'edge-link-router' ), 'error' );
				return;
			}

			// Basic format validation.
			if ( strlen( $token ) < 20 ) {
				add_settings_error( 'cfelr_messages', 'invalid_token', __( 'API token appears to be invalid.', 'edge-link-router' ), 'error' );
				return;
			}

			if ( $this->token_storage->store( $token ) ) {
				// Redirect to run auth diagnostics.
				wp_safe_redirect( add_query_arg( 'run_auth_diagnostics', '1' ) );
				exit;
			} else {
				add_settings_error( 'cfelr_messages', 'token_error', __( 'Failed to save API token.', 'edge-link-router' ), 'error' );
			}
		}

		// Enable edge (redirects after success).
		if ( isset( $_POST['cfelr_enable_edge'] ) ) {
			if ( ! isset( $_POST['cfelr_enable_edge_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cfelr_enable_edge_nonce'] ) ), 'cfelr_enable_edge' ) ) {
				add_settings_error( 'cfelr_enable_edge_messages', 'nonce_error', __( 'Security check failed.', 'edge-link-router' ), 'error' );
				return;
			}

			$deployer = new Deployer();

			if ( $deployer->enable_edge() ) {
				// Redirect to refresh page state.
				wp_safe_redirect( remove_query_arg( array( 'run_diagnostics', 'run_auth_diagnostics' ) ) );
				exit;
			} else {
				add_settings_error( 'cfelr_enable_edge_messages', 'edge_error', $deployer->get_last_error() ?: __( 'Failed to enable edge mode.', 'edge-link-router' ), 'error' );
			}
		}

		// Disable edge (redirects after success).
		if ( isset( $_POST['cfelr_disable_edge'] ) ) {
			if ( ! isset( $_POST['cfelr_disable_edge_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cfelr_disable_edge_nonce'] ) ), 'cfelr_disable_edge' ) ) {
				add_settings_error( 'cfelr_edge_messages', 'nonce_error', __( 'Security check failed.', 'edge-link-router' ), 'error' );
				return;
			}

			$deployer = new Deployer();

			if ( $deployer->disable_edge() ) {
				// Redirect to refresh page state.
				wp_safe_redirect( remove_query_arg( array( 'run_diagnostics', 'run_auth_diagnostics' ) ) );
				exit;
			} else {
				add_settings_error( 'cfelr_edge_messages', 'edge_error', $deployer->get_last_error() ?: __( 'Failed to disable edge mode.', 'edge-link-router' ), 'error' );
			}
		}
	}

	/**
	 * Handle form submissions that don't require redirects.
	 *
	 * @return void
	 */
	private function handle_actions(): void {
		// Remove token (no redirect needed).
		if ( isset( $_POST['cfelr_remove_token'] ) ) {
			if ( ! isset( $_POST['cfelr_remove_token_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cfelr_remove_token_nonce'] ) ), 'cfelr_remove_token' ) ) {
				add_settings_error( 'cfelr_messages', 'nonce_error', __( 'Security check failed.', 'edge-link-router' ), 'error' );
				return;
			}

			// If edge is enabled, disable it first.
			if ( $this->state->is_edge_enabled() ) {
				$deployer = new Deployer();
				$deployer->disable_edge();
			}

			$this->token_storage->delete();
			add_settings_error( 'cfelr_messages', 'token_removed', __( 'API token removed.', 'edge-link-router' ), 'success' );
		}

		// Republish snapshot (no redirect needed).
		if ( isset( $_POST['cfelr_republish'] ) ) {
			if ( ! isset( $_POST['cfelr_republish_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cfelr_republish_nonce'] ) ), 'cfelr_republish' ) ) {
				add_settings_error( 'cfelr_edge_messages', 'nonce_error', __( 'Security check failed.', 'edge-link-router' ), 'error' );
				return;
			}

			$deployer = new Deployer();

			if ( $deployer->republish() ) {
				add_settings_error( 'cfelr_edge_messages', 'republished', __( 'Snapshot republished successfully.', 'edge-link-router' ), 'success' );
			} else {
				add_settings_error( 'cfelr_edge_messages', 'republish_error', $deployer->get_last_error() ?: __( 'Failed to republish snapshot.', 'edge-link-router' ), 'error' );
			}
		}

		// Fix route mismatch (no redirect needed).
		if ( isset( $_POST['cfelr_fix_route'] ) ) {
			if ( ! isset( $_POST['cfelr_fix_route_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cfelr_fix_route_nonce'] ) ), 'cfelr_fix_route' ) ) {
				add_settings_error( 'cfelr_edge_messages', 'nonce_error', __( 'Security check failed.', 'edge-link-router' ), 'error' );
				return;
			}

			$deployer = new Deployer();

			if ( $deployer->fix_route() ) {
				// Run health check to clear the mismatch.
				$this->health->check();
				add_settings_error( 'cfelr_edge_messages', 'route_fixed', __( 'Route fixed successfully.', 'edge-link-router' ), 'success' );
			} else {
				add_settings_error( 'cfelr_edge_messages', 'route_fix_error', $deployer->get_last_error() ?: __( 'Failed to fix route.', 'edge-link-router' ), 'error' );
			}
		}
	}

	/**
	 * Get selected host (from query or primary).
	 *
	 * @param array $host_info Host info array.
	 * @return string
	 */
	private function get_selected_host( array $host_info ): string {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$selected = isset( $_GET['host'] ) ? sanitize_text_field( wp_unslash( $_GET['host'] ) ) : '';

		if ( $selected && ( $selected === $host_info['primary'] || $selected === $host_info['alias'] ) ) {
			return $selected;
		}

		return $host_info['primary'];
	}

	/**
	 * Render health badge.
	 *
	 * @param array $status Health status array.
	 * @return void
	 */
	private function render_health_badge( array $status ): void {
		$badges = array(
			'wp-only'  => array(
				'class' => 'cfelr-health-wp-only',
				'icon'  => 'dashicons-wordpress',
				'label' => __( 'WP Only', 'edge-link-router' ),
			),
			'active'   => array(
				'class' => 'cfelr-health-active',
				'icon'  => 'dashicons-cloud-saved',
				'label' => __( 'Edge Active', 'edge-link-router' ),
			),
			'degraded' => array(
				'class' => 'cfelr-health-degraded',
				'icon'  => 'dashicons-warning',
				'label' => __( 'Edge Degraded', 'edge-link-router' ),
			),
		);

		$state = $status['state'] ?? 'wp-only';
		$badge = $badges[ $state ] ?? $badges['wp-only'];

		?>
		<div class="cfelr-health-badge <?php echo esc_attr( $badge['class'] ); ?>" title="<?php echo esc_attr( $status['message'] ?? '' ); ?>">
			<span class="dashicons <?php echo esc_attr( $badge['icon'] ); ?>"></span>
			<?php echo esc_html( $badge['label'] ); ?>
		</div>
		<?php
	}

	/**
	 * Render status icon.
	 *
	 * @param string $status Status: ok, warn, fail, pending.
	 * @return void
	 */
	private function render_status_icon( string $status ): void {
		$icons = array(
			'ok'      => 'dashicons-yes-alt',
			'warn'    => 'dashicons-warning',
			'fail'    => 'dashicons-dismiss',
			'pending' => 'dashicons-clock',
		);

		$icon = $icons[ $status ] ?? $icons['pending'];

		echo '<span class="dashicons ' . esc_attr( $icon ) . '"></span>';
	}
}
