<?php
/**
 * Stats Admin Page.
 *
 * @package EdgeLinkRouter
 */

namespace CFELR\WP\Admin\Pages;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CFELR\Integrations\Cloudflare\IntegrationState;
use CFELR\WP\Repository\WPLinkRepository;
use CFELR\WP\Repository\WPStatsRepository;

/**
 * Statistics page.
 */
class StatsPage {

	/**
	 * Link repository.
	 *
	 * @var WPLinkRepository
	 */
	private WPLinkRepository $links;

	/**
	 * Stats repository.
	 *
	 * @var WPStatsRepository
	 */
	private WPStatsRepository $stats;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->links = new WPLinkRepository();
		$this->stats = new WPStatsRepository();
	}

	/**
	 * Render the page.
	 *
	 * @return void
	 */
	public function render(): void {
		// phpcs:ignore WordPress.Security.NonceVerification.Recommended
		$days = isset( $_GET['days'] ) ? (int) $_GET['days'] : 30;
		if ( ! in_array( $days, array( 7, 30, 90 ), true ) ) {
			$days = 30;
		}

		$total_clicks = $this->stats->get_total_clicks( $days );
		$top_links    = $this->stats->get_top_links( $days, 20 );
		?>
		<div class="wrap cfelr-admin">
			<h1><?php esc_html_e( 'Statistics', 'edge-link-router' ); ?></h1>

			<!-- Period selector -->
			<div class="cfelr-period-selector" style="margin: 20px 0;">
				<?php
				$periods = array(
					7  => __( 'Last 7 days', 'edge-link-router' ),
					30 => __( 'Last 30 days', 'edge-link-router' ),
					90 => __( 'Last 90 days', 'edge-link-router' ),
				);
				foreach ( $periods as $period => $label ) :
					$url   = add_query_arg( 'days', $period );
					$class = $days === $period ? 'button button-primary' : 'button';
					?>
					<a href="<?php echo esc_url( $url ); ?>" class="<?php echo esc_attr( $class ); ?>">
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</div>

			<?php
			// Show edge mode notice.
			$state = new IntegrationState();
			if ( $state->is_edge_enabled() ) :
				$account_id = $state->get_account_id();
				$cf_url     = $account_id
					? 'https://dash.cloudflare.com/' . $account_id . '/workers/services/view/cfelr-edge-worker/production/metrics'
					: 'https://dash.cloudflare.com/';
				?>
				<div class="notice notice-info inline" style="margin: 0 0 20px;">
					<p>
						<span class="dashicons dashicons-cloud" style="color: #72aee6;"></span>
						<strong><?php esc_html_e( 'Edge Mode Active', 'edge-link-router' ); ?></strong>
						&mdash;
						<?php esc_html_e( 'Most redirects are handled by Cloudflare Worker and not counted here. Stats below show only WP fallback clicks.', 'edge-link-router' ); ?>
					</p>
					<p>
						<a href="<?php echo esc_url( $cf_url ); ?>" target="_blank" rel="noopener noreferrer" class="button button-small">
							<?php esc_html_e( 'View Edge Metrics in Cloudflare', 'edge-link-router' ); ?>
							<span class="dashicons dashicons-external cfelr-btn-icon--sm"></span>
						</a>
					</p>
				</div>
			<?php endif; ?>

			<!-- Summary -->
			<div class="cfelr-card">
				<h2><?php esc_html_e( 'Summary', 'edge-link-router' ); ?></h2>
				<table class="widefat striped" style="max-width: 400px;">
					<tr>
						<th><?php esc_html_e( 'Total Clicks', 'edge-link-router' ); ?></th>
						<td><strong><?php echo esc_html( number_format_i18n( $total_clicks ) ); ?></strong></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Total Links', 'edge-link-router' ); ?></th>
						<td><?php echo esc_html( number_format_i18n( $this->links->count() ) ); ?></td>
					</tr>
					<tr>
						<th><?php esc_html_e( 'Active Links', 'edge-link-router' ); ?></th>
						<td><?php echo esc_html( number_format_i18n( $this->links->count( array( 'enabled' => true ) ) ) ); ?></td>
					</tr>
				</table>
			</div>

			<!-- Top Links -->
			<div class="cfelr-card">
				<h2>
					<?php
					printf(
						/* translators: %d: number of days */
						esc_html__( 'Top Links (Last %d Days)', 'edge-link-router' ),
						(int) $days
					);
					?>
				</h2>

				<?php if ( empty( $top_links ) ) : ?>
					<div class="cfelr-empty-state">
						<span class="dashicons dashicons-chart-bar"></span>
						<h3><?php esc_html_e( 'No click data yet', 'edge-link-router' ); ?></h3>
						<p><?php esc_html_e( 'Statistics will appear here once your links start receiving clicks. Share your links to see traffic data.', 'edge-link-router' ); ?></p>
					</div>
				<?php else : ?>
					<table class="widefat striped">
						<thead>
							<tr>
								<th><?php esc_html_e( 'Rank', 'edge-link-router' ); ?></th>
								<th><?php esc_html_e( 'Slug', 'edge-link-router' ); ?></th>
								<th><?php esc_html_e( 'Target URL', 'edge-link-router' ); ?></th>
								<th><?php esc_html_e( 'Clicks', 'edge-link-router' ); ?></th>
								<th><?php esc_html_e( 'Share', 'edge-link-router' ); ?></th>
							</tr>
						</thead>
						<tbody>
							<?php
							$rank = 0;
							foreach ( $top_links as $item ) :
								++$rank;
								$link = $this->links->find( $item['link_id'] );
								if ( ! $link ) {
									continue;
								}

								$share = $total_clicks > 0 ? ( $item['clicks'] / $total_clicks ) * 100 : 0;

								$edit_url = add_query_arg(
									array(
										'page'   => 'edge-link-router',
										'action' => 'edit',
										'id'     => $link->id,
									),
									admin_url( 'admin.php' )
								);
								?>
								<tr>
									<td><?php echo esc_html( $rank ); ?></td>
									<td>
										<a href="<?php echo esc_url( $edit_url ); ?>">
											<code>/go/<?php echo esc_html( $link->slug ); ?></code>
										</a>
									</td>
									<td>
										<?php
										$display_url = strlen( $link->target_url ) > 50
											? substr( $link->target_url, 0, 50 ) . '...'
											: $link->target_url;
										?>
										<a href="<?php echo esc_url( $link->target_url ); ?>" target="_blank" rel="noopener" title="<?php echo esc_attr( $link->get_full_target_url() ); ?>">
											<?php echo esc_html( $display_url ); ?>
										</a>
									</td>
									<td>
										<strong><?php echo esc_html( number_format_i18n( $item['clicks'] ) ); ?></strong>
									</td>
									<td>
										<?php echo esc_html( number_format_i18n( $share, 1 ) ); ?>%
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
				<?php endif; ?>
			</div>

			<!-- Simple bar chart -->
			<?php if ( ! empty( $top_links ) ) : ?>
			<div class="cfelr-card">
				<h2><?php esc_html_e( 'Visual Overview', 'edge-link-router' ); ?></h2>
				<div class="cfelr-bar-chart">
					<?php
					$max_clicks = max( array_column( $top_links, 'clicks' ) );

					foreach ( array_slice( $top_links, 0, 10 ) as $item ) :
						$link = $this->links->find( $item['link_id'] );
						if ( ! $link ) {
							continue;
						}

						$width = $max_clicks > 0 ? ( $item['clicks'] / $max_clicks ) * 100 : 0;
						?>
						<div class="cfelr-bar-row">
							<div class="cfelr-bar-label">
								<code><?php echo esc_html( $link->slug ); ?></code>
							</div>
							<div class="cfelr-bar-container">
								<div class="cfelr-bar" style="width: <?php echo esc_attr( $width ); ?>%;"></div>
								<span class="cfelr-bar-value"><?php echo esc_html( number_format_i18n( $item['clicks'] ) ); ?></span>
							</div>
						</div>
					<?php endforeach; ?>
				</div>
			</div>
			<?php endif; ?>
		</div>
		<?php
	}
}
