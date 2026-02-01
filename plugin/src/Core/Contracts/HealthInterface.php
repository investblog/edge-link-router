<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Health Interface.
 *
 * @package EdgeLinkRouter
 */

namespace CFELR\Core\Contracts;

/**
 * Interface for health status reporting.
 */
interface HealthInterface {

	/**
	 * Health states.
	 */
	public const STATE_WP_ONLY  = 'wp-only';
	public const STATE_ACTIVE   = 'active';
	public const STATE_DEGRADED = 'degraded';

	/**
	 * Get current health status.
	 *
	 * @return array {
	 *     @type string $state      One of: wp-only, active, degraded.
	 *     @type string $message    Human-readable status message.
	 *     @type string $last_check ISO 8601 timestamp of last check.
	 * }
	 */
	public function get_status(): array;

	/**
	 * Run a health check and update status.
	 *
	 * @return array Same format as get_status().
	 */
	public function check(): array;
}
