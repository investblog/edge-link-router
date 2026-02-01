<?php
/**
 * Cloudflare Route Manager.
 *
 * @package EdgeLinkRouter
 */

namespace CFELR\Integrations\Cloudflare;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Manages Cloudflare Worker routes.
 */
class RouteManager {

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
	 * Constructor.
	 */
	public function __construct() {
		$this->client = new Client();
		$this->state  = new IntegrationState();
	}

	/**
	 * Get the route pattern for this site.
	 *
	 * @param string $prefix URL prefix (default: from settings).
	 * @return string Route pattern.
	 */
	public function get_route_pattern( string $prefix = '' ): string {
		if ( empty( $prefix ) ) {
			$prefix = $this->state->get_prefix();
		}

		$host = wp_parse_url( home_url(), PHP_URL_HOST );
		return "{$host}/{$prefix}/*";
	}

	/**
	 * Check if route exists.
	 *
	 * @param string|null $zone_id Optional zone ID (uses stored if not provided).
	 * @return bool
	 */
	public function route_exists( ?string $zone_id = null ): bool {
		if ( empty( $zone_id ) ) {
			$zone_id = $this->state->get_zone_id();
		}

		if ( empty( $zone_id ) ) {
			return false;
		}

		$route = $this->find_our_route( $zone_id );
		return $route !== null;
	}

	/**
	 * Get existing route ID if any.
	 *
	 * @param string|null $zone_id Optional zone ID (uses stored if not provided).
	 * @return string|null
	 */
	public function get_route_id( ?string $zone_id = null ): ?string {
		// First check stored route ID.
		$stored = $this->state->get_route_id();
		if ( $stored ) {
			return $stored;
		}

		// Then try to find it via API.
		if ( empty( $zone_id ) ) {
			$zone_id = $this->state->get_zone_id();
		}

		if ( empty( $zone_id ) ) {
			return null;
		}

		$route = $this->find_our_route( $zone_id );
		return $route['id'] ?? null;
	}

	/**
	 * Find our route in the zone.
	 *
	 * @param string $zone_id Zone ID.
	 * @return array|null Route data or null.
	 */
	public function find_our_route( string $zone_id ): ?array {
		$routes = $this->client->list_routes( $zone_id );

		if ( ! $routes ) {
			return null;
		}

		$pattern = $this->get_route_pattern();

		foreach ( $routes as $route ) {
			if ( $route['pattern'] === $pattern && ( $route['script'] ?? '' ) === IntegrationState::WORKER_NAME ) {
				return $route;
			}
		}

		return null;
	}

	/**
	 * Check if any conflicting route exists (same pattern, different worker).
	 *
	 * @param string|null $zone_id Optional zone ID.
	 * @return array|null Conflicting route data or null.
	 */
	public function find_conflicting_route( ?string $zone_id = null ): ?array {
		if ( empty( $zone_id ) ) {
			$zone_id = $this->state->get_zone_id();
		}

		if ( empty( $zone_id ) ) {
			return null;
		}

		$routes = $this->client->list_routes( $zone_id );

		if ( ! $routes ) {
			return null;
		}

		$pattern = $this->get_route_pattern();

		foreach ( $routes as $route ) {
			if ( $route['pattern'] === $pattern && ( $route['script'] ?? '' ) !== IntegrationState::WORKER_NAME ) {
				return $route;
			}
		}

		return null;
	}
}
