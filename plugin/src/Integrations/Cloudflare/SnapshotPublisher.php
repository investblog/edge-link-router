<?php
/**
 * Cloudflare Snapshot Publisher.
 *
 * @package EdgeLinkRouter
 */

namespace CFELR\Integrations\Cloudflare;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CFELR\Core\Contracts\PublisherInterface;

/**
 * Publishes link snapshots to Cloudflare Worker.
 */
class SnapshotPublisher implements PublisherInterface {

	/**
	 * Soft limit for links count (warning threshold).
	 *
	 * @var int
	 */
	private const SOFT_LINK_LIMIT = 2000;

	/**
	 * Hard limit for bundle size (3MB gzip, estimate 3:1 ratio).
	 *
	 * @var int
	 */
	private const HARD_SIZE_LIMIT = 9 * 1024 * 1024; // 9MB uncompressed ~ 3MB gzip.

	/**
	 * Last error message.
	 *
	 * @var string|null
	 */
	private ?string $last_error = null;

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
	 * Constructor.
	 */
	public function __construct() {
		$this->state  = new IntegrationState();
		$this->client = new Client();
	}

	/**
	 * Publish links to Cloudflare edge.
	 *
	 * @param array $links Associative array slug => link data.
	 * @return bool
	 */
	public function publish( array $links ): bool {
		// Check prerequisites.
		if ( ! $this->is_ready() ) {
			$this->last_error = __( 'Edge publishing not ready. Configure API token and run diagnostics first.', 'edge-link-router' );
			return false;
		}

		// Pre-flight size check.
		if ( ! $this->check_size_limit( $links ) ) {
			$size             = $this->estimate_size( $links );
			$this->last_error = sprintf(
				/* translators: %1$s: current size, %2$s: limit size */
				__( 'Bundle size (%1$s) exceeds the limit (%2$s). Reduce the number of links or URL lengths.', 'edge-link-router' ),
				size_format( $size ),
				size_format( self::HARD_SIZE_LIMIT )
			);
			return false;
		}

		// Check soft limit (warning only, doesn't block).
		$links_count = count( $links );
		if ( $links_count > self::SOFT_LINK_LIMIT ) {
			// Log warning but continue.
			$this->log_event(
				'warning',
				sprintf(
					/* translators: 1: number of links being published, 2: recommended limit */
					__( 'Publishing %1$d links exceeds the recommended limit of %2$d.', 'edge-link-router' ),
					$links_count,
					self::SOFT_LINK_LIMIT
				)
			);
		}

		// Get required IDs.
		$account_id = $this->state->get_account_id();
		if ( empty( $account_id ) ) {
			$this->last_error = __( 'Account ID not available. Run authorized diagnostics first.', 'edge-link-router' );
			return false;
		}

		// Generate Worker script.
		$script = $this->generate_worker_script( $links, $this->state->get_prefix() );

		if ( empty( $script ) ) {
			$this->last_error = __( 'Failed to generate Worker script.', 'edge-link-router' );
			return false;
		}

		// Upload Worker.
		$result = $this->client->upload_worker( $account_id, IntegrationState::WORKER_NAME, $script );

		if ( ! $result ) {
			$this->last_error = $this->client->get_last_error() ?: __( 'Failed to upload Worker script.', 'edge-link-router' );
			return false;
		}

		// Update last publish timestamp.
		$this->state->set_last_publish();

		// Log success.
		$this->log_event(
			'publish',
			sprintf(
				/* translators: %d: number of links */
				__( 'Snapshot published successfully with %d links.', 'edge-link-router' ),
				$links_count
			)
		);

		return true;
	}

	/**
	 * Check if publisher is ready.
	 *
	 * @return bool
	 */
	public function is_ready(): bool {
		return $this->state->has_required_state();
	}

	/**
	 * Get last error.
	 *
	 * @return string|null
	 */
	public function get_last_error(): ?string {
		return $this->last_error;
	}

	/**
	 * Generate Worker script with inline snapshot.
	 *
	 * @param array  $links  Links data.
	 * @param string $prefix URL prefix.
	 * @return string JavaScript code.
	 */
	public function generate_worker_script( array $links, string $prefix = 'go' ): string {
		// Load template.
		ob_start();
		include CFELR_PLUGIN_DIR . 'templates/worker.js.php';
		return ob_get_clean();
	}

	/**
	 * Estimate bundle size.
	 *
	 * @param array $links Links data.
	 * @return int Estimated bytes.
	 */
	public function estimate_size( array $links ): int {
		$json = wp_json_encode( $links );
		// Base Worker code is ~1KB, JSON adds variable size.
		return 1024 + strlen( $json );
	}

	/**
	 * Check if bundle exceeds size limit.
	 *
	 * @param array $links Links data.
	 * @return bool True if within limit.
	 */
	public function check_size_limit( array $links ): bool {
		$size = $this->estimate_size( $links );
		return $size < self::HARD_SIZE_LIMIT;
	}

	/**
	 * Get soft limit for links count.
	 *
	 * @return int
	 */
	public function get_soft_link_limit(): int {
		return self::SOFT_LINK_LIMIT;
	}

	/**
	 * Log a publish event.
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
