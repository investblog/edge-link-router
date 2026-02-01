<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Publisher Interface.
 *
 * @package EdgeLinkRouter
 */

namespace CFELR\Core\Contracts;

/**
 * Interface for publishing links to external services (Cloudflare edge, 301.st).
 */
interface PublisherInterface {

	/**
	 * Publish links snapshot to the external service.
	 *
	 * @param array $links Associative array of slug => link data.
	 * @return bool True on success, false on failure.
	 */
	public function publish( array $links ): bool;

	/**
	 * Check if the publisher is configured and ready.
	 *
	 * @return bool
	 */
	public function is_ready(): bool;

	/**
	 * Get the last error message.
	 *
	 * @return string|null
	 */
	public function get_last_error(): ?string;
}
