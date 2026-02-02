<?php
/**
 * Cloudflare Deployer.
 *
 * @package EdgeLinkRouter
 */

namespace CFELR\Integrations\Cloudflare;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CFELR\WP\Repository\WPLinkRepository;

/**
 * Handles deploying Worker scripts and routes to Cloudflare.
 */
class Deployer {

	/**
	 * API client.
	 *
	 * @var Client
	 */
	private Client $client;

	/**
	 * Integration state.
	 *
	 * @var IntegrationState
	 */
	private IntegrationState $state;

	/**
	 * Snapshot publisher.
	 *
	 * @var SnapshotPublisher
	 */
	private SnapshotPublisher $publisher;

	/**
	 * Last error message.
	 *
	 * @var string|null
	 */
	private ?string $last_error = null;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$this->client    = new Client();
		$this->state     = new IntegrationState();
		$this->publisher = new SnapshotPublisher();
	}

	/**
	 * Enable edge mode: deploy Worker and create route.
	 *
	 * @return bool
	 */
	public function enable_edge(): bool {
		// Check prerequisites.
		if ( ! $this->state->has_required_state() ) {
			$this->last_error = __( 'Missing required configuration. Run diagnostics first.', 'edge-link-router' );
			return false;
		}

		// Step 1: Get all enabled links and publish to Worker.
		$repository = new WPLinkRepository();
		$links      = $repository->get_snapshot_data();

		if ( ! $this->publisher->publish( $links ) ) {
			$this->last_error = $this->publisher->get_last_error();
			return false;
		}

		// Step 2: Create route.
		if ( ! $this->create_route() ) {
			return false;
		}

		// Mark edge as enabled.
		$this->state->set_edge_enabled( true );

		// Update health status.
		$health = new Health();
		$health->set_status( Health::STATE_ACTIVE, __( 'Edge mode enabled.', 'edge-link-router' ) );

		$this->log_event( 'enable', __( 'Edge mode enabled successfully.', 'edge-link-router' ) );

		return true;
	}

	/**
	 * Disable edge mode: remove route, keep Worker.
	 *
	 * @return bool
	 */
	public function disable_edge(): bool {
		// Remove route but keep Worker (safe).
		$result = $this->remove_route();

		// Clear edge state.
		$this->state->clear_edge_state();

		// Update health status.
		$health = new Health();
		$health->set_status( Health::STATE_WP_ONLY, __( 'Edge mode disabled.', 'edge-link-router' ) );

		$this->log_event( 'disable', __( 'Edge mode disabled.', 'edge-link-router' ) );

		return $result;
	}

	/**
	 * Remove route on plugin deactivation.
	 * Worker is kept for fail-safe reasons.
	 *
	 * @return void
	 */
	public function remove_route_on_deactivation(): void {
		// Only attempt if edge was enabled.
		if ( ! $this->state->is_edge_enabled() ) {
			return;
		}

		// Try to remove route (best effort).
		$this->remove_route();

		// Clear the route ID but keep edge_enabled flag.
		// This allows us to restore if reactivated.
		$this->state->set_route_id( null );
	}

	/**
	 * Full cleanup on uninstall.
	 *
	 * @return void
	 */
	public function full_cleanup(): void {
		// Attempt to remove route and Worker (best effort).
		$this->remove_route();
		$this->remove_worker();

		// Clear all state.
		$this->state->clear_edge_state();
	}

	/**
	 * Deploy Worker script.
	 *
	 * @param string $script Worker JavaScript code.
	 * @return bool
	 */
	public function deploy_worker( string $script ): bool {
		$account_id = $this->state->get_account_id();

		if ( empty( $account_id ) ) {
			$this->last_error = __( 'Account ID not configured.', 'edge-link-router' );
			return false;
		}

		$result = $this->client->upload_worker( $account_id, IntegrationState::WORKER_NAME, $script );

		if ( ! $result ) {
			$this->last_error = $this->client->get_last_error();
			return false;
		}

		return true;
	}

	/**
	 * Create Worker route.
	 *
	 * @return bool
	 */
	public function create_route(): bool {
		$zone_id = $this->state->get_zone_id();

		if ( empty( $zone_id ) ) {
			$this->last_error = __( 'Zone ID not configured.', 'edge-link-router' );
			return false;
		}

		$pattern = $this->state->get_route_pattern();

		// Check if route already exists.
		$existing = $this->find_existing_route( $zone_id, $pattern );

		if ( $existing ) {
			// Route already exists for our Worker.
			if ( ( $existing['script'] ?? '' ) === IntegrationState::WORKER_NAME ) {
				$this->state->set_route_id( $existing['id'] );
				return true;
			}

			// Route exists but for different Worker - this is a conflict.
			$this->last_error = sprintf(
				/* translators: %s: route pattern */
				__( 'Route %s is already in use by another Worker.', 'edge-link-router' ),
				$pattern
			);
			return false;
		}

		// Create new route.
		$route = $this->client->create_route( $zone_id, $pattern, IntegrationState::WORKER_NAME );

		if ( ! $route ) {
			$this->last_error = $this->client->get_last_error() ?: __( 'Failed to create route.', 'edge-link-router' );
			return false;
		}

		// Store route ID.
		$this->state->set_route_id( $route['id'] );

		return true;
	}

	/**
	 * Remove Worker route.
	 *
	 * @return bool
	 */
	public function remove_route(): bool {
		$zone_id  = $this->state->get_zone_id();
		$route_id = $this->state->get_route_id();

		if ( empty( $zone_id ) ) {
			// Can't remove without zone ID.
			return true;
		}

		// If we don't have stored route ID, try to find it.
		if ( empty( $route_id ) ) {
			$pattern  = $this->state->get_route_pattern();
			$existing = $this->find_existing_route( $zone_id, $pattern );

			if ( $existing && ( $existing['script'] ?? '' ) === IntegrationState::WORKER_NAME ) {
				$route_id = $existing['id'];
			}
		}

		if ( empty( $route_id ) ) {
			// No route to remove.
			return true;
		}

		$result = $this->client->delete_route( $zone_id, $route_id );

		if ( $result ) {
			$this->state->set_route_id( null );
		}

		return $result;
	}

	/**
	 * Remove Worker script entirely.
	 * Used during uninstall.
	 *
	 * @return bool
	 */
	public function remove_worker(): bool {
		$account_id = $this->state->get_account_id();

		if ( empty( $account_id ) ) {
			return true;
		}

		return $this->client->delete_worker( $account_id, IntegrationState::WORKER_NAME );
	}

	/**
	 * Re-publish snapshot (update Worker with current links).
	 *
	 * @return bool
	 */
	public function republish(): bool {
		if ( ! $this->state->is_edge_enabled() ) {
			$this->last_error = __( 'Edge mode is not enabled.', 'edge-link-router' );
			return false;
		}

		$repository = new WPLinkRepository();
		$links      = $repository->get_snapshot_data();

		if ( ! $this->publisher->publish( $links ) ) {
			$this->last_error = $this->publisher->get_last_error();
			return false;
		}

		$this->log_event( 'republish', __( 'Snapshot republished successfully.', 'edge-link-router' ) );

		return true;
	}

	/**
	 * Fix route pattern mismatch.
	 * Removes any existing route for our worker and creates a new one with correct pattern.
	 *
	 * @return bool
	 */
	public function fix_route(): bool {
		if ( ! $this->state->is_edge_enabled() ) {
			$this->last_error = __( 'Edge mode is not enabled.', 'edge-link-router' );
			return false;
		}

		$zone_id = $this->state->get_zone_id();

		if ( empty( $zone_id ) ) {
			$this->last_error = __( 'Zone ID not configured.', 'edge-link-router' );
			return false;
		}

		// Find any route with our worker (regardless of pattern).
		$route_manager = new RouteManager();
		$existing      = $route_manager->find_worker_route( $zone_id );

		// Remove existing route if found.
		if ( $existing ) {
			$this->client->delete_route( $zone_id, $existing['id'] );
			$this->state->set_route_id( null );
		}

		// Create new route with correct pattern.
		if ( ! $this->create_route() ) {
			return false;
		}

		$this->log_event( 'fix_route', __( 'Route pattern fixed successfully.', 'edge-link-router' ) );

		return true;
	}

	/**
	 * Update prefix: remove old route, republish worker, create new route.
	 *
	 * @param string $old_prefix Old prefix.
	 * @param string $new_prefix New prefix.
	 * @return bool
	 */
	public function update_prefix( string $old_prefix, string $new_prefix ): bool {
		if ( ! $this->state->is_edge_enabled() ) {
			return true; // Nothing to update.
		}

		$zone_id = $this->state->get_zone_id();

		if ( empty( $zone_id ) ) {
			$this->last_error = __( 'Zone ID not configured.', 'edge-link-router' );
			return false;
		}

		// Step 1: Remove old route.
		$old_pattern = $this->build_route_pattern( $old_prefix );
		$old_route   = $this->find_existing_route( $zone_id, $old_pattern );

		if ( $old_route && ( $old_route['script'] ?? '' ) === IntegrationState::WORKER_NAME ) {
			$this->client->delete_route( $zone_id, $old_route['id'] );
			$this->state->set_route_id( null );
		}

		// Step 2: Republish Worker with new prefix.
		$repository = new WPLinkRepository();
		$links      = $repository->get_snapshot_data();

		if ( ! $this->publisher->publish( $links ) ) {
			$this->last_error = $this->publisher->get_last_error();
			return false;
		}

		// Step 3: Create new route.
		if ( ! $this->create_route() ) {
			return false;
		}

		$this->log_event(
			'prefix_change',
			sprintf( 'Route updated: /%s/* → /%s/*', $old_prefix, $new_prefix )
		);

		return true;
	}

	/**
	 * Build route pattern for a given prefix.
	 *
	 * @param string $prefix URL prefix.
	 * @return string Route pattern.
	 */
	private function build_route_pattern( string $prefix ): string {
		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		return $host . '/' . $prefix . '/*';
	}

	/**
	 * Find existing route by pattern.
	 *
	 * @param string $zone_id Zone ID.
	 * @param string $pattern Route pattern.
	 * @return array|null Route data or null.
	 */
	private function find_existing_route( string $zone_id, string $pattern ): ?array {
		$routes = $this->client->list_routes( $zone_id );

		if ( ! $routes ) {
			return null;
		}

		foreach ( $routes as $route ) {
			if ( $route['pattern'] === $pattern ) {
				return $route;
			}
		}

		return null;
	}

	/**
	 * Get last error message.
	 *
	 * @return string|null
	 */
	public function get_last_error(): ?string {
		return $this->last_error;
	}

	/**
	 * Log an event.
	 *
	 * @param string $type    Event type.
	 * @param string $message Message.
	 * @return void
	 */
	private function log_event( string $type, string $message ): void {
		$log = get_option( 'cfelr_reconcile_log', array() );

		$log[] = array(
			'time'    => gmdate( 'c' ),
			'type'    => $type,
			'message' => $message,
		);

		// Keep only last 50 entries.
		if ( count( $log ) > 50 ) {
			$log = array_slice( $log, -50 );
		}

		update_option( 'cfelr_reconcile_log', $log );
	}
}
