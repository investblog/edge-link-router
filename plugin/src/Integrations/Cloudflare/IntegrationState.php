<?php
/**
 * Cloudflare Integration State.
 *
 * @package EdgeLinkRouter
 */

namespace CFELR\Integrations\Cloudflare;

/**
 * Helper class to manage Cloudflare integration state.
 */
class IntegrationState {

	/**
	 * Worker script name.
	 *
	 * @var string
	 */
	public const WORKER_NAME = 'cfelr-edge-worker';

	/**
	 * Option key for edge enabled flag.
	 *
	 * @var string
	 */
	public const EDGE_ENABLED_KEY = 'cfelr_edge_enabled';

	/**
	 * Option key for last publish timestamp.
	 *
	 * @var string
	 */
	public const LAST_PUBLISH_KEY = 'cfelr_last_publish';

	/**
	 * Option key for route ID.
	 *
	 * @var string
	 */
	public const ROUTE_ID_KEY = 'cfelr_cf_route_id';

	/**
	 * Get integration state from database.
	 *
	 * @return array|null State data or null.
	 */
	public function get(): ?array {
		global $wpdb;

		$table = $wpdb->prefix . 'cfelr_integrations';

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$row = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT state_json FROM {$table} WHERE provider = %s",
				'cloudflare'
			)
		);

		if ( ! $row || empty( $row->state_json ) ) {
			return null;
		}

		$state = json_decode( $row->state_json, true );

		if ( ! is_array( $state ) ) {
			return null;
		}

		return $state;
	}

	/**
	 * Get zone ID.
	 *
	 * @return string|null
	 */
	public function get_zone_id(): ?string {
		$state = $this->get();
		return $state['zone_id'] ?? null;
	}

	/**
	 * Get account ID.
	 *
	 * @return string|null
	 */
	public function get_account_id(): ?string {
		$state = $this->get();
		return $state['account_id'] ?? null;
	}

	/**
	 * Get zone name.
	 *
	 * @return string|null
	 */
	public function get_zone_name(): ?string {
		$state = $this->get();
		return $state['zone_name'] ?? null;
	}

	/**
	 * Update state field.
	 *
	 * @param string $key   Field key.
	 * @param mixed  $value Field value.
	 * @return bool
	 */
	public function update_field( string $key, $value ): bool {
		global $wpdb;

		$table = $wpdb->prefix . 'cfelr_integrations';
		$state = $this->get() ?: array();

		$state[ $key ]      = $value;
		$state['updated_at'] = gmdate( 'c' );

		$state_json = wp_json_encode( $state );

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching, WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE provider = %s",
				'cloudflare'
			)
		);

		if ( $existing ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$result = $wpdb->update(
				$table,
				array( 'state_json' => $state_json ),
				array( 'provider' => 'cloudflare' ),
				array( '%s' ),
				array( '%s' )
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$result = $wpdb->insert(
				$table,
				array(
					'provider'   => 'cloudflare',
					'state_json' => $state_json,
				),
				array( '%s', '%s' )
			);
		}

		return $result !== false;
	}

	/**
	 * Check if edge mode is enabled.
	 *
	 * @return bool
	 */
	public function is_edge_enabled(): bool {
		return (bool) get_option( self::EDGE_ENABLED_KEY, false );
	}

	/**
	 * Set edge enabled status.
	 *
	 * @param bool $enabled Whether edge is enabled.
	 * @return void
	 */
	public function set_edge_enabled( bool $enabled ): void {
		update_option( self::EDGE_ENABLED_KEY, $enabled );
	}

	/**
	 * Get stored route ID.
	 *
	 * @return string|null
	 */
	public function get_route_id(): ?string {
		return get_option( self::ROUTE_ID_KEY ) ?: null;
	}

	/**
	 * Set route ID.
	 *
	 * @param string|null $route_id Route ID or null to delete.
	 * @return void
	 */
	public function set_route_id( ?string $route_id ): void {
		if ( $route_id ) {
			update_option( self::ROUTE_ID_KEY, $route_id );
		} else {
			delete_option( self::ROUTE_ID_KEY );
		}
	}

	/**
	 * Get last publish timestamp.
	 *
	 * @return string|null ISO 8601 timestamp or null.
	 */
	public function get_last_publish(): ?string {
		return get_option( self::LAST_PUBLISH_KEY ) ?: null;
	}

	/**
	 * Set last publish timestamp.
	 *
	 * @return void
	 */
	public function set_last_publish(): void {
		update_option( self::LAST_PUBLISH_KEY, gmdate( 'c' ) );
	}

	/**
	 * Get the route pattern for this site.
	 *
	 * @return string Route pattern like "example.com/go/*".
	 */
	public function get_route_pattern(): string {
		$settings = get_option( 'cfelr_settings', array() );
		$prefix   = $settings['prefix'] ?? 'go';
		$host     = wp_parse_url( home_url(), PHP_URL_HOST );

		return $host . '/' . $prefix . '/*';
	}

	/**
	 * Get the URL prefix.
	 *
	 * @return string
	 */
	public function get_prefix(): string {
		$settings = get_option( 'cfelr_settings', array() );
		return $settings['prefix'] ?? 'go';
	}

	/**
	 * Check if all required state is available for edge operations.
	 *
	 * @return bool
	 */
	public function has_required_state(): bool {
		$token_storage = new TokenStorage();

		return $token_storage->has_token()
			&& ! empty( $this->get_zone_id() )
			&& ! empty( $this->get_account_id() );
	}

	/**
	 * Clear all edge-related state (for disable/cleanup).
	 *
	 * @return void
	 */
	public function clear_edge_state(): void {
		$this->set_edge_enabled( false );
		$this->set_route_id( null );
		delete_option( self::LAST_PUBLISH_KEY );
	}
}
