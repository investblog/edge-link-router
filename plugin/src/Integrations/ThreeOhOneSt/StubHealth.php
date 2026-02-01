<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * 301.st Stub Health.
 *
 * @package EdgeLinkRouter
 */

namespace CFELR\Integrations\ThreeOhOneSt;

use CFELR\Core\Contracts\HealthInterface;

/**
 * Stub health checker for 301.st integration (future implementation).
 */
class StubHealth implements HealthInterface {

	/**
	 * Get status.
	 *
	 * @return array
	 */
	public function get_status(): array {
		return array(
			'state'      => self::STATE_WP_ONLY,
			'message'    => __( '301.st integration is not available yet.', 'edge-link-router' ),
			'last_check' => null,
		);
	}

	/**
	 * Run health check.
	 *
	 * @return array
	 */
	public function check(): array {
		return $this->get_status();
	}
}
