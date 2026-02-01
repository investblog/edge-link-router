<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Cron Handler.
 *
 * @package EdgeLinkRouter
 */

namespace CFELR\WP;

/**
 * Handles scheduled tasks.
 */
class Cron {

	/**
	 * Stats cleanup hook name.
	 *
	 * @var string
	 */
	private const STATS_CLEANUP_HOOK = 'cfelr_stats_cleanup';

	/**
	 * Reconcile hook name.
	 *
	 * @var string
	 */
	private const RECONCILE_HOOK = 'cfelr_reconcile';

	/**
	 * Edge publish debounce hook name.
	 *
	 * @var string
	 */
	private const PUBLISH_DEBOUNCE_HOOK = 'cfelr_edge_publish_debounced';

	/**
	 * Initialize cron handlers.
	 *
	 * @return void
	 */
	public function init(): void {
		add_action( self::STATS_CLEANUP_HOOK, array( $this, 'run_stats_cleanup' ) );
		add_action( self::RECONCILE_HOOK, array( $this, 'run_reconcile' ) );
		add_action( self::PUBLISH_DEBOUNCE_HOOK, array( $this, 'run_debounced_publish' ) );
	}

	/**
	 * Schedule cron jobs.
	 *
	 * @return void
	 */
	public function schedule(): void {
		// Stats cleanup - daily.
		if ( ! wp_next_scheduled( self::STATS_CLEANUP_HOOK ) ) {
			wp_schedule_event( time(), 'daily', self::STATS_CLEANUP_HOOK );
		}

		// Reconcile - hourly.
		if ( ! wp_next_scheduled( self::RECONCILE_HOOK ) ) {
			wp_schedule_event( time(), 'hourly', self::RECONCILE_HOOK );
		}
	}

	/**
	 * Unschedule cron jobs.
	 *
	 * @return void
	 */
	public function unschedule(): void {
		$timestamp = wp_next_scheduled( self::STATS_CLEANUP_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::STATS_CLEANUP_HOOK );
		}

		$timestamp = wp_next_scheduled( self::RECONCILE_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::RECONCILE_HOOK );
		}

		// Clear any pending debounced publish.
		$timestamp = wp_next_scheduled( self::PUBLISH_DEBOUNCE_HOOK );
		if ( $timestamp ) {
			wp_unschedule_event( $timestamp, self::PUBLISH_DEBOUNCE_HOOK );
		}
	}

	/**
	 * Run stats cleanup.
	 *
	 * @return void
	 */
	public function run_stats_cleanup(): void {
		$stats   = new Repository\WPStatsRepository();
		$deleted = $stats->cleanup( 90 );

		$this->log( 'stats_cleanup', "Deleted {$deleted} old stats records" );
	}

	/**
	 * Run reconcile check.
	 *
	 * @return void
	 */
	public function run_reconcile(): void {
		$health = new \CFELR\Integrations\Cloudflare\Health();
		$status = $health->check();

		$this->log( 'reconcile', "Health check: {$status['state']} - {$status['message']}" );
	}

	/**
	 * Run debounced edge publish.
	 *
	 * @return void
	 */
	public function run_debounced_publish(): void {
		// Clear the transient.
		delete_transient( 'cfelr_edge_publish_debounce' );

		$state = new \CFELR\Integrations\Cloudflare\IntegrationState();

		// Only publish if edge is enabled.
		if ( ! $state->is_edge_enabled() ) {
			$this->log( 'publish', 'Skipped: Edge not enabled' );
			return;
		}

		$publisher = new \CFELR\Integrations\Cloudflare\SnapshotPublisher();

		if ( ! $publisher->is_ready() ) {
			$this->log( 'publish', 'Skipped: Edge not configured' );
			return;
		}

		$repository = new Repository\WPLinkRepository();
		$links      = $repository->get_snapshot_data();

		$result = $publisher->publish( $links );

		if ( $result ) {
			$this->log( 'publish', 'Snapshot published successfully' );
		} else {
			$this->log( 'publish', 'Snapshot publish failed: ' . $publisher->get_last_error() );
		}
	}

	/**
	 * Maybe schedule a debounced publish if edge is enabled.
	 * Static helper for calling from other classes.
	 *
	 * @return void
	 */
	public static function maybe_schedule_publish(): void {
		$state = new \CFELR\Integrations\Cloudflare\IntegrationState();

		if ( ! $state->is_edge_enabled() ) {
			return;
		}

		$cron = new self();
		$cron->schedule_debounced_publish();
	}

	/**
	 * Schedule a debounced publish.
	 *
	 * @return void
	 */
	public function schedule_debounced_publish(): void {
		// Check if already scheduled.
		$existing = get_transient( 'cfelr_edge_publish_debounce' );

		if ( $existing ) {
			// Already pending, reschedule.
			$timestamp = wp_next_scheduled( self::PUBLISH_DEBOUNCE_HOOK );
			if ( $timestamp ) {
				wp_unschedule_event( $timestamp, self::PUBLISH_DEBOUNCE_HOOK );
			}
		}

		// Set transient to mark as pending.
		set_transient( 'cfelr_edge_publish_debounce', true, 60 );

		// Schedule for 10 seconds from now.
		wp_schedule_single_event( time() + 10, self::PUBLISH_DEBOUNCE_HOOK );
	}

	/**
	 * Log a cron event.
	 *
	 * @param string $type    Event type.
	 * @param string $message Log message.
	 * @return void
	 */
	private function log( string $type, string $message ): void {
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
