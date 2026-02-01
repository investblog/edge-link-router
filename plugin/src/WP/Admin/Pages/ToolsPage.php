<?php
/**
 * Tools Admin Page.
 *
 * @package EdgeLinkRouter
 */

namespace CFELR\WP\Admin\Pages;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CFELR\Core\Resolver;
use CFELR\Integrations\Cloudflare\IntegrationState;
use CFELR\WP\Repository\WPLinkRepository;

/**
 * Tools page.
 */
class ToolsPage {

	/**
	 * Render the page.
	 *
	 * @return void
	 */
	public function render(): void {
		$prefix = $this->get_prefix();
		?>
		<div class="wrap cfelr-admin">
			<h1><?php esc_html_e( 'Tools', 'edge-link-router' ); ?></h1>

			<!-- Flush Rewrite Rules -->
			<div class="cfelr-card">
				<h2><?php esc_html_e( 'Flush Rewrite Rules', 'edge-link-router' ); ?></h2>
				<p><?php esc_html_e( 'If your redirect URLs are not working, try flushing the rewrite rules.', 'edge-link-router' ); ?></p>
				<form method="post" action="">
					<?php wp_nonce_field( 'cfelr_flush_rewrite', 'cfelr_flush_nonce' ); ?>
					<button type="submit" name="cfelr_flush_rewrite" class="button button-secondary">
						<?php esc_html_e( 'Flush Rewrite Rules', 'edge-link-router' ); ?>
					</button>
				</form>
				<?php $this->handle_flush_rewrite(); ?>
			</div>

			<!-- Test Redirect -->
			<div class="cfelr-card">
				<h2><?php esc_html_e( 'Test Redirect', 'edge-link-router' ); ?></h2>
				<p><?php esc_html_e( 'Enter a slug to test the redirect resolution without actually redirecting.', 'edge-link-router' ); ?></p>

				<form method="post" action="">
					<?php wp_nonce_field( 'cfelr_test_redirect', 'cfelr_test_nonce' ); ?>
					<p>
						<label>
							<strong><?php echo esc_html( home_url( '/' . $prefix . '/' ) ); ?></strong>
							<input type="text" name="test_slug" value="" class="regular-text" placeholder="your-slug" style="width: 200px;">
						</label>
						<button type="submit" name="cfelr_test_redirect" class="button button-secondary">
							<?php esc_html_e( 'Test', 'edge-link-router' ); ?>
						</button>
					</p>
				</form>

				<?php $this->handle_test_redirect(); ?>

				<p class="description">
					<?php
					printf(
						/* translators: %s: debug URL example */
						esc_html__( 'Tip: You can also test redirects by visiting %s as an admin to see debug JSON.', 'edge-link-router' ),
						'<code>' . esc_html( home_url( '/' . $prefix . '/your-slug?cfelr_debug=1' ) ) . '</code>'
					);
					?>
				</p>
			</div>

			<!-- Force Re-publish -->
			<div class="cfelr-card">
				<h2><?php esc_html_e( 'Force Re-publish Snapshot', 'edge-link-router' ); ?></h2>
				<p><?php esc_html_e( 'Force republish all links to the Cloudflare edge worker.', 'edge-link-router' ); ?></p>

				<?php
				$health = new \CFELR\Integrations\Cloudflare\Health();
				$status = $health->get_status();

				if ( $status['state'] === 'wp-only' ) :
					?>
					<p class="description">
						<?php esc_html_e( 'Edge mode is not enabled. Enable it on the Integrations page first.', 'edge-link-router' ); ?>
					</p>
					<button type="button" class="button button-secondary" disabled>
						<?php esc_html_e( 'Force Re-publish', 'edge-link-router' ); ?>
					</button>
				<?php else : ?>
					<form method="post" action="">
						<?php wp_nonce_field( 'cfelr_force_publish', 'cfelr_publish_nonce' ); ?>
						<button type="submit" name="cfelr_force_publish" class="button button-secondary">
							<?php esc_html_e( 'Force Re-publish', 'edge-link-router' ); ?>
						</button>
					</form>
					<?php $this->handle_force_publish(); ?>
				<?php endif; ?>
			</div>

			<!-- Quick Stats -->
			<div class="cfelr-card">
				<h2><?php esc_html_e( 'Quick Stats', 'edge-link-router' ); ?></h2>
				<?php $this->render_quick_stats(); ?>
			</div>
		</div>
		<?php
	}

	/**
	 * Handle flush rewrite rules action.
	 *
	 * @return void
	 */
	private function handle_flush_rewrite(): void {
		if ( ! isset( $_POST['cfelr_flush_rewrite'] ) ) {
			return;
		}

		if ( ! isset( $_POST['cfelr_flush_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cfelr_flush_nonce'] ) ), 'cfelr_flush_rewrite' ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Security check failed.', 'edge-link-router' ) . '</p></div>';
			return;
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Permission denied.', 'edge-link-router' ) . '</p></div>';
			return;
		}

		$rewrite_handler = new \CFELR\WP\RewriteHandler();
		$rewrite_handler->flush_rules();

		echo '<div class="notice notice-success"><p>' . esc_html__( 'Rewrite rules flushed successfully.', 'edge-link-router' ) . '</p></div>';
	}

	/**
	 * Handle test redirect action.
	 *
	 * @return void
	 */
	private function handle_test_redirect(): void {
		if ( ! isset( $_POST['cfelr_test_redirect'] ) ) {
			return;
		}

		if ( ! isset( $_POST['cfelr_test_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cfelr_test_nonce'] ) ), 'cfelr_test_redirect' ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Security check failed.', 'edge-link-router' ) . '</p></div>';
			return;
		}

		$slug = isset( $_POST['test_slug'] ) ? sanitize_text_field( wp_unslash( $_POST['test_slug'] ) ) : '';

		if ( empty( $slug ) ) {
			echo '<div class="notice notice-warning"><p>' . esc_html__( 'Please enter a slug to test.', 'edge-link-router' ) . '</p></div>';
			return;
		}

		$repository = new WPLinkRepository();
		$resolver   = new Resolver( $repository );
		$decision   = $resolver->resolve( $slug, 'test' );

		echo '<div class="cfelr-test-result">';
		echo '<h4>' . esc_html__( 'Test Result:', 'edge-link-router' ) . '</h4>';

		if ( ! $decision->should_redirect ) {
			echo '<div class="notice notice-warning inline"><p>';
			printf(
				/* translators: %s: slug */
				esc_html__( 'Slug "%s" not found or disabled. This would return a 404.', 'edge-link-router' ),
				esc_html( $slug )
			);
			echo '</p></div>';
		} else {
			// Determine handler.
			$state        = new IntegrationState();
			$edge_enabled = $state->is_edge_enabled();

			echo '<table class="widefat striped" style="max-width: 600px;">';
			echo '<tr><th>' . esc_html__( 'Handler', 'edge-link-router' ) . '</th><td>';
			if ( $edge_enabled ) {
				echo '<span class="dashicons dashicons-cloud-saved" style="color: #4caf50;"></span> ';
				echo '<strong style="color: #4caf50;">' . esc_html__( 'Edge (Cloudflare Worker)', 'edge-link-router' ) . '</strong>';
				echo '<br><small class="description">' . esc_html__( 'WP serves as fallback if edge fails', 'edge-link-router' ) . '</small>';
			} else {
				echo '<span class="dashicons dashicons-wordpress" style="color: #666;"></span> ';
				echo '<strong>' . esc_html__( 'WordPress Only', 'edge-link-router' ) . '</strong>';
			}
			echo '</td></tr>';
			echo '<tr><th>' . esc_html__( 'Slug', 'edge-link-router' ) . '</th><td><code>' . esc_html( $slug ) . '</code></td></tr>';
			echo '<tr><th>' . esc_html__( 'Target URL', 'edge-link-router' ) . '</th><td><a href="' . esc_url( $decision->target_url ) . '" target="_blank">' . esc_html( $decision->target_url ) . '</a></td></tr>';
			echo '<tr><th>' . esc_html__( 'Status Code', 'edge-link-router' ) . '</th><td>' . esc_html( $decision->status_code ) . '</td></tr>';
			echo '<tr><th>' . esc_html__( 'Link ID', 'edge-link-router' ) . '</th><td>' . esc_html( $decision->link_id ) . '</td></tr>';

			if ( ! empty( $decision->options['passthrough_query'] ) ) {
				echo '<tr><th>' . esc_html__( 'Passthrough Query', 'edge-link-router' ) . '</th><td>' . esc_html__( 'Yes', 'edge-link-router' ) . '</td></tr>';
			}

			if ( ! empty( $decision->options['append_utm'] ) ) {
				echo '<tr><th>' . esc_html__( 'UTM Parameters', 'edge-link-router' ) . '</th><td><code>' . esc_html( wp_json_encode( $decision->options['append_utm'] ) ) . '</code></td></tr>';
			}

			echo '</table>';

			$prefix   = $this->get_prefix();
			$test_url = home_url( '/' . $prefix . '/' . $slug );

			echo '<p style="margin-top: 10px;">';
			echo '<a href="' . esc_url( $test_url ) . '" class="button" target="_blank">' . esc_html__( 'Open Redirect', 'edge-link-router' ) . '</a> ';
			echo '<a href="' . esc_url( $test_url . '?cfelr_debug=1' ) . '" class="button" target="_blank">' . esc_html__( 'View Debug JSON', 'edge-link-router' ) . '</a>';
			echo '</p>';
		}

		echo '</div>';
	}

	/**
	 * Handle force publish action.
	 *
	 * @return void
	 */
	private function handle_force_publish(): void {
		if ( ! isset( $_POST['cfelr_force_publish'] ) ) {
			return;
		}

		if ( ! isset( $_POST['cfelr_publish_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['cfelr_publish_nonce'] ) ), 'cfelr_force_publish' ) ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Security check failed.', 'edge-link-router' ) . '</p></div>';
			return;
		}

		$publisher = new \CFELR\Integrations\Cloudflare\SnapshotPublisher();

		if ( ! $publisher->is_ready() ) {
			echo '<div class="notice notice-error"><p>' . esc_html__( 'Edge mode is not configured.', 'edge-link-router' ) . '</p></div>';
			return;
		}

		$repository = new WPLinkRepository();
		$links      = $repository->get_snapshot_data();
		$result     = $publisher->publish( $links );

		if ( $result ) {
			echo '<div class="notice notice-success"><p>' . esc_html__( 'Snapshot published successfully.', 'edge-link-router' ) . '</p></div>';
		} else {
			echo '<div class="notice notice-error"><p>' . esc_html( $publisher->get_last_error() ) . '</p></div>';
		}
	}

	/**
	 * Render quick stats.
	 *
	 * @return void
	 */
	private function render_quick_stats(): void {
		$repository = new WPLinkRepository();

		$total    = $repository->count();
		$enabled  = $repository->count( array( 'enabled' => true ) );
		$disabled = $repository->count( array( 'enabled' => false ) );

		echo '<table class="widefat striped" style="max-width: 400px;">';
		echo '<tr><th>' . esc_html__( 'Total Links', 'edge-link-router' ) . '</th><td>' . esc_html( number_format_i18n( $total ) ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Enabled', 'edge-link-router' ) . '</th><td>' . esc_html( number_format_i18n( $enabled ) ) . '</td></tr>';
		echo '<tr><th>' . esc_html__( 'Disabled', 'edge-link-router' ) . '</th><td>' . esc_html( number_format_i18n( $disabled ) ) . '</td></tr>';
		echo '</table>';
	}

	/**
	 * Get redirect prefix.
	 *
	 * @return string
	 */
	private function get_prefix(): string {
		return SettingsPage::get_prefix();
	}
}
