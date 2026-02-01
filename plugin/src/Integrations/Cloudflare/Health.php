<?php
/**
 * Cloudflare Health.
 *
 * @package EdgeLinkRouter
 */

namespace CFELR\Integrations\Cloudflare;

use CFELR\Core\Contracts\HealthInterface;

/**
 * Cloudflare health status tracker.
 */
class Health implements HealthInterface {

	/**
	 * Option key for health status.
	 *
	 * @var string
	 */
	private const OPTION_KEY = 'cfelr_cf_health';

	/**
	 * Integration state.
	 *
	 * @var IntegrationState
	 */
	private IntegrationState $state;

	/**
	 * API client.
	 *
	 * @var Client
	 */
	private Client $client;

	/**
	 * Route manager.
	 *
	 * @var RouteManager
	 */
	private RouteManager $route_manager;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->state         = new IntegrationState();
		$this->client        = new Client();
		$this->route_manager = new RouteManager();
	}

	/**
	 * Get current health status.
	 *
	 * @return array
	 */
	public function get_status(): array {
		$status = get_option( self::OPTION_KEY );

		if ( ! is_array( $status ) ) {
			return array(
				'state'      => self::STATE_WP_ONLY,
				'message'    => __( 'Edge mode not configured.', 'edge-link-router' ),
				'last_check' => null,
			);
		}

		return $status;
	}

	/**
	 * Run health check.
	 *
	 * @return array
	 */
	public function check(): array {
		// Disable time budget for cron checks.
		$this->client->set_use_time_budget( false );

		// If no token configured, we're in WP-only mode.
		$token_storage = new TokenStorage();
		if ( ! $token_storage->has_token() ) {
			$status = array(
				'state'      => self::STATE_WP_ONLY,
				'message'    => __( 'Edge mode not configured.', 'edge-link-router' ),
				'last_check' => gmdate( 'c' ),
			);
			update_option( self::OPTION_KEY, $status );
			return $status;
		}

		// If edge is not enabled, we're in WP-only mode.
		if ( ! $this->state->is_edge_enabled() ) {
			$status = array(
				'state'      => self::STATE_WP_ONLY,
				'message'    => __( 'Edge mode not enabled.', 'edge-link-router' ),
				'last_check' => gmdate( 'c' ),
			);
			update_option( self::OPTION_KEY, $status );
			return $status;
		}

		// Check Worker existence.
		$account_id = $this->state->get_account_id();
		if ( empty( $account_id ) ) {
			$status = array(
				'state'      => self::STATE_DEGRADED,
				'message'    => __( 'Account ID missing. Run diagnostics.', 'edge-link-router' ),
				'last_check' => gmdate( 'c' ),
			);
			update_option( self::OPTION_KEY, $status );
			return $status;
		}

		$worker = $this->client->get_worker( $account_id, IntegrationState::WORKER_NAME );

		if ( ! $worker ) {
			$status = array(
				'state'      => self::STATE_DEGRADED,
				'message'    => __( 'Worker script not found. WP fallback active.', 'edge-link-router' ),
				'last_check' => gmdate( 'c' ),
			);
			update_option( self::OPTION_KEY, $status );
			return $status;
		}

		// Check route existence.
		$zone_id = $this->state->get_zone_id();
		if ( empty( $zone_id ) ) {
			$status = array(
				'state'      => self::STATE_DEGRADED,
				'message'    => __( 'Zone ID missing. Run diagnostics.', 'edge-link-router' ),
				'last_check' => gmdate( 'c' ),
			);
			update_option( self::OPTION_KEY, $status );
			return $status;
		}

		$route_exists = $this->route_manager->route_exists( $zone_id );

		if ( ! $route_exists ) {
			$status = array(
				'state'      => self::STATE_DEGRADED,
				'message'    => __( 'Worker route not found. WP fallback active.', 'edge-link-router' ),
				'last_check' => gmdate( 'c' ),
			);
			update_option( self::OPTION_KEY, $status );
			return $status;
		}

		// All checks passed - edge is active.
		$status = array(
			'state'      => self::STATE_ACTIVE,
			'message'    => __( 'Edge mode is active and healthy.', 'edge-link-router' ),
			'last_check' => gmdate( 'c' ),
		);
		update_option( self::OPTION_KEY, $status );

		return $status;
	}

	/**
	 * Set health status.
	 *
	 * @param string $state   Health state.
	 * @param string $message Status message.
	 * @return void
	 */
	public function set_status( string $state, string $message ): void {
		$status = array(
			'state'      => $state,
			'message'    => $message,
			'last_check' => gmdate( 'c' ),
		);

		update_option( self::OPTION_KEY, $status );
	}

	/**
	 * Get last reconcile timestamp.
	 *
	 * @return string|null ISO 8601 timestamp or null.
	 */
	public function get_last_reconcile(): ?string {
		$status = $this->get_status();
		return $status['last_check'] ?? null;
	}

	/**
	 * Quick check without API calls (for UI display).
	 * Uses cached status.
	 *
	 * @return array
	 */
	public function get_cached_status(): array {
		return $this->get_status();
	}

	/**
	 * Check if edge mode is healthy (active state).
	 *
	 * @return bool
	 */
	public function is_healthy(): bool {
		$status = $this->get_status();
		return ( $status['state'] ?? '' ) === self::STATE_ACTIVE;
	}

	/**
	 * Check if edge mode is degraded.
	 *
	 * @return bool
	 */
	public function is_degraded(): bool {
		$status = $this->get_status();
		return ( $status['state'] ?? '' ) === self::STATE_DEGRADED;
	}
}
