<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Dashboard Widget.
 *
 * @package EdgeLinkRouter
 */

namespace CFELR\WP\Admin;

use CFELR\Integrations\Cloudflare\Health;
use CFELR\Integrations\Cloudflare\IntegrationState;

/**
 * Dashboard widget showing plugin status.
 */
class DashboardWidget {

	/**
	 * Widget ID.
	 *
	 * @var string
	 */
	private const WIDGET_ID = 'cfelr_status_widget';

	/**
	 * Required capability.
	 *
	 * @var string
	 */
	private const CAPABILITY = 'manage_options';

	/**
	 * Initialize the widget.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( 'wp_dashboard_setup', array( $this, 'register_widget' ) );
	}

	/**
	 * Register the dashboard widget.
	 *
	 * @return void
	 */
	public function register_widget(): void {
		if ( ! current_user_can( self::CAPABILITY ) ) {
			return;
		}

		wp_add_dashboard_widget(
			self::WIDGET_ID,
			__( 'Edge Link Router — Status', 'edge-link-router' ),
			array( $this, 'render' )
		);
	}

	/**
	 * Render the widget content.
	 *
	 * Uses max 2 SQL queries: links count + today clicks.
	 * No external HTTP requests.
	 *
	 * @return void
	 */
	public function render(): void {
		// Get data with minimal queries.
		$data = $this->get_widget_data();

		?>
		<div class="cfelr-dashboard-widget">
			<!-- Mode Badge -->
			<div class="cfelr-widget-row">
				<span class="cfelr-widget-label"><?php esc_html_e( 'Mode', 'edge-link-router' ); ?></span>
				<span class="cfelr-widget-value">
					<?php $this->render_status_badge( $data['health'] ); ?>
				</span>
			</div>

			<!-- Links Count -->
			<div class="cfelr-widget-row">
				<span class="cfelr-widget-label"><?php esc_html_e( 'Links', 'edge-link-router' ); ?></span>
				<span class="cfelr-widget-value">
					<?php
					printf(
						/* translators: %1$s: enabled count, %2$s: total count */
						esc_html__( '%1$s enabled / %2$s total', 'edge-link-router' ),
						'<strong>' . esc_html( number_format_i18n( $data['enabled_links'] ) ) . '</strong>',
						esc_html( number_format_i18n( $data['total_links'] ) )
					);
					?>
				</span>
			</div>

			<!-- Today Clicks -->
			<div class="cfelr-widget-row">
				<span class="cfelr-widget-label"><?php esc_html_e( 'Clicks Today', 'edge-link-router' ); ?></span>
				<span class="cfelr-widget-value">
					<strong><?php echo esc_html( number_format_i18n( $data['today_clicks'] ) ); ?></strong>
				</span>
			</div>

			<!-- Last Activity -->
			<?php if ( $data['last_publish'] || $data['last_reconcile'] ) : ?>
			<div class="cfelr-widget-row cfelr-widget-meta">
				<?php if ( $data['last_publish'] ) : ?>
					<span title="<?php esc_attr_e( 'Last edge publish', 'edge-link-router' ); ?>">
						<?php
						printf(
							/* translators: %s: human-readable time diff */
							esc_html__( 'Published %s ago', 'edge-link-router' ),
							esc_html( human_time_diff( strtotime( $data['last_publish'] ) ) )
						);
						?>
					</span>
				<?php endif; ?>
				<?php if ( $data['last_reconcile'] ) : ?>
					<span title="<?php esc_attr_e( 'Last health check', 'edge-link-router' ); ?>">
						<?php
						printf(
							/* translators: %s: human-readable time diff */
							esc_html__( 'Checked %s ago', 'edge-link-router' ),
							esc_html( human_time_diff( strtotime( $data['last_reconcile'] ) ) )
						);
						?>
					</span>
				<?php endif; ?>
			</div>
			<?php endif; ?>

			<!-- Action Links -->
			<div class="cfelr-widget-actions">
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=edge-link-router' ) ); ?>" class="button button-primary">
					<?php esc_html_e( 'Open Links', 'edge-link-router' ); ?>
				</a>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=edge-link-router-integrations' ) ); ?>" class="button">
					<?php esc_html_e( 'Run Diagnostics', 'edge-link-router' ); ?>
				</a>
			</div>
		</div>

		<style>
			.cfelr-dashboard-widget {
				margin: -12px;
			}
			.cfelr-widget-row {
				display: flex;
				justify-content: space-between;
				align-items: center;
				padding: 10px 12px;
				border-bottom: 1px solid #f0f0f1;
			}
			.cfelr-widget-row:last-of-type {
				border-bottom: none;
			}
			.cfelr-widget-label {
				color: #646970;
				font-size: 13px;
			}
			.cfelr-widget-value {
				text-align: right;
			}
			.cfelr-widget-meta {
				font-size: 12px;
				color: #8c8f94;
				flex-wrap: wrap;
				gap: 8px;
			}
			.cfelr-widget-actions {
				display: flex;
				gap: 8px;
				padding: 12px;
				background: #f6f7f7;
				margin-top: 0;
			}
			.cfelr-status-badge {
				display: inline-block;
				padding: 2px 8px;
				border-radius: 3px;
				font-size: 12px;
				font-weight: 500;
			}
			.cfelr-status-badge.wp-only {
				background: #dcdcde;
				color: #50575e;
			}
			.cfelr-status-badge.active {
				background: #d4edda;
				color: #155724;
			}
			.cfelr-status-badge.degraded {
				background: #fff3cd;
				color: #856404;
			}
		</style>
		<?php
	}

	/**
	 * Render status badge.
	 *
	 * @param array $health Health status data.
	 * @return void
	 */
	private function render_status_badge( array $health ): void {
		$badges = array(
			'wp-only'  => array(
				'label' => __( 'WP Only', 'edge-link-router' ),
				'class' => 'wp-only',
			),
			'active'   => array(
				'label' => __( 'Edge Active', 'edge-link-router' ),
				'class' => 'active',
			),
			'degraded' => array(
				'label' => __( 'Edge Degraded', 'edge-link-router' ),
				'class' => 'degraded',
			),
		);

		$state = $health['state'] ?? 'wp-only';
		$badge = $badges[ $state ] ?? $badges['wp-only'];

		printf(
			'<span class="cfelr-status-badge %s" title="%s">%s</span>',
			esc_attr( $badge['class'] ),
			esc_attr( $health['message'] ?? '' ),
			esc_html( $badge['label'] )
		);
	}

	/**
	 * Get widget data with minimal queries.
	 *
	 * @return array
	 */
	private function get_widget_data(): array {
		global $wpdb;

		// Query 1: Get links count (total and enabled in one query).
		$links_table = $wpdb->prefix . 'cfelr_links';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$counts = $wpdb->get_row(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is safe
			"SELECT COUNT(*) as total, SUM(CASE WHEN enabled = 1 THEN 1 ELSE 0 END) as enabled FROM {$links_table}"
		);

		// Query 2: Get today's clicks.
		$clicks_table = $wpdb->prefix . 'cfelr_clicks_daily';
		$today        = gmdate( 'Y-m-d' );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$today_clicks = $wpdb->get_var(
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- table name is safe
			$wpdb->prepare( "SELECT COALESCE(SUM(clicks), 0) FROM {$clicks_table} WHERE day = %s", $today )
		);

		// Get cached health status (no SQL/HTTP).
		$health = new Health();
		$status = $health->get_status();

		// Get cached timestamps from options (no additional query - WP caches options).
		$last_publish   = get_option( IntegrationState::LAST_PUBLISH_KEY, '' );
		$last_reconcile = $status['last_check'] ?? '';

		return array(
			'total_links'    => (int) ( $counts->total ?? 0 ),
			'enabled_links'  => (int) ( $counts->enabled ?? 0 ),
			'today_clicks'   => (int) $today_clicks,
			'health'         => $status,
			'last_publish'   => $last_publish,
			'last_reconcile' => $last_reconcile,
		);
	}
}
