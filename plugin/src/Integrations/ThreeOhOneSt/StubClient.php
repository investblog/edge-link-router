<?php
/**
 * 301.st Stub Client.
 *
 * @package EdgeLinkRouter
 */

namespace CFELR\Integrations\ThreeOhOneSt;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CFELR\Core\Contracts\PublisherInterface;

/**
 * Stub client for 301.st integration (future implementation).
 */
class StubClient implements PublisherInterface {

	/**
	 * Publish links to 301.st.
	 *
	 * @param array $links Links data.
	 * @return bool
	 */
	public function publish( array $links ): bool {
		// Stub - future implementation.
		return false;
	}

	/**
	 * Check if ready.
	 *
	 * @return bool
	 */
	public function is_ready(): bool {
		// Stub - future implementation.
		return false;
	}

	/**
	 * Get last error.
	 *
	 * @return string|null
	 */
	public function get_last_error(): ?string {
		return __( '301.st integration is not available yet.', 'edge-link-router' );
	}

	/**
	 * Get deep link URL to 301.st dashboard.
	 *
	 * @return string
	 */
	public function get_dashboard_url(): string {
		return 'https://301.st/dashboard';
	}
}
