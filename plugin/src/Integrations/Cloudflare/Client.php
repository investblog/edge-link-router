<?php
/**
 * Cloudflare API Client.
 *
 * @package EdgeLinkRouter
 */

namespace CFELR\Integrations\Cloudflare;

/**
 * Client for Cloudflare API operations.
 */
class Client {

	/**
	 * API base URL.
	 *
	 * @var string
	 */
	private const API_BASE = 'https://api.cloudflare.com/client/v4';

	/**
	 * Max retry attempts for rate limiting.
	 *
	 * @var int
	 */
	private const MAX_RETRIES = 3;

	/**
	 * Base backoff delay in seconds.
	 *
	 * @var int
	 */
	private const BASE_BACKOFF = 1;

	/**
	 * Max backoff delay in seconds.
	 *
	 * @var int
	 */
	private const MAX_BACKOFF = 60;

	/**
	 * UI time budget in seconds.
	 *
	 * @var int
	 */
	private const UI_TIME_BUDGET = 15;

	/**
	 * API token.
	 *
	 * @var string|null
	 */
	private ?string $token = null;

	/**
	 * Last error message.
	 *
	 * @var string|null
	 */
	private ?string $last_error = null;

	/**
	 * Last error code.
	 *
	 * @var int|null
	 */
	private ?int $last_error_code = null;

	/**
	 * Whether to use UI time budget.
	 *
	 * @var bool
	 */
	private bool $use_time_budget = true;

	/**
	 * Constructor.
	 */
	public function __construct() {
		$token_storage = new TokenStorage();
		$this->token   = $token_storage->retrieve();
	}

	/**
	 * Check if client is configured.
	 *
	 * @return bool
	 */
	public function is_configured(): bool {
		return ! empty( $this->token );
	}

	/**
	 * Set whether to use time budget (for cron jobs).
	 *
	 * @param bool $use_budget Whether to use time budget.
	 * @return self
	 */
	public function set_use_time_budget( bool $use_budget ): self {
		$this->use_time_budget = $use_budget;
		return $this;
	}

	/**
	 * Verify the API token and get permissions.
	 *
	 * @return array|null Token info or null on failure.
	 */
	public function verify_token(): ?array {
		$response = $this->request( 'GET', '/user/tokens/verify' );

		if ( ! $response || ! isset( $response['result'] ) ) {
			return null;
		}

		return $response['result'];
	}

	/**
	 * Get token permissions from verify endpoint.
	 *
	 * @return array|null Array of permission groups or null.
	 */
	public function get_token_permissions(): ?array {
		// Get the token details which include permissions.
		$verify = $this->verify_token();

		if ( ! $verify || $verify['status'] !== 'active' ) {
			return null;
		}

		// The verify endpoint doesn't return full permissions.
		// We need to check what operations we can actually perform.
		return array(
			'status'     => $verify['status'],
			'not_before' => $verify['not_before'] ?? null,
			'expires_on' => $verify['expires_on'] ?? null,
		);
	}

	/**
	 * List zones, optionally filtered by name.
	 *
	 * @param string $name Optional zone name filter.
	 * @return array|null Array of zones or null on failure.
	 */
	public function list_zones( string $name = '' ): ?array {
		$params = array(
			'per_page' => 50,
			'status'   => 'active',
		);

		if ( ! empty( $name ) ) {
			$params['name'] = $name;
		}

		$response = $this->request( 'GET', '/zones', $params );

		if ( ! $response || ! isset( $response['result'] ) ) {
			return null;
		}

		return $response['result'];
	}

	/**
	 * Find zone for a given host.
	 *
	 * @param string $host Hostname to find zone for.
	 * @return array|null Zone data with account_id or null.
	 */
	public function find_zone_for_host( string $host ): ?array {
		// First try exact match.
		$zones = $this->list_zones( $host );

		if ( $zones && count( $zones ) > 0 ) {
			$zone = $zones[0];
			return array(
				'zone_id'    => $zone['id'],
				'zone_name'  => $zone['name'],
				'account_id' => $zone['account']['id'] ?? null,
				'status'     => $zone['status'],
			);
		}

		// Try parent domain (e.g., www.example.com -> example.com).
		$parts = explode( '.', $host );
		if ( count( $parts ) > 2 ) {
			$parent = implode( '.', array_slice( $parts, 1 ) );
			$zones  = $this->list_zones( $parent );

			if ( $zones && count( $zones ) > 0 ) {
				$zone = $zones[0];
				return array(
					'zone_id'    => $zone['id'],
					'zone_name'  => $zone['name'],
					'account_id' => $zone['account']['id'] ?? null,
					'status'     => $zone['status'],
				);
			}
		}

		$this->last_error = __( 'No matching zone found for this domain.', 'edge-link-router' );
		return null;
	}

	/**
	 * Get zone details.
	 *
	 * @param string $zone_id Zone ID.
	 * @return array|null Zone details or null.
	 */
	public function get_zone( string $zone_id ): ?array {
		$response = $this->request( 'GET', '/zones/' . $zone_id );

		if ( ! $response || ! isset( $response['result'] ) ) {
			return null;
		}

		return $response['result'];
	}

	/**
	 * List DNS records for a zone.
	 *
	 * @param string $zone_id Zone ID.
	 * @param string $name    Optional record name filter.
	 * @param string $type    Optional record type filter (A, AAAA, CNAME).
	 * @return array|null Array of DNS records or null.
	 */
	public function list_dns_records( string $zone_id, string $name = '', string $type = '' ): ?array {
		$params = array( 'per_page' => 100 );

		if ( ! empty( $name ) ) {
			$params['name'] = $name;
		}

		if ( ! empty( $type ) ) {
			$params['type'] = $type;
		}

		$response = $this->request( 'GET', '/zones/' . $zone_id . '/dns_records', $params );

		if ( ! $response || ! isset( $response['result'] ) ) {
			return null;
		}

		return $response['result'];
	}

	/**
	 * Check if DNS record is proxied through Cloudflare.
	 *
	 * @param string $zone_id Zone ID.
	 * @param string $host    Hostname to check.
	 * @return array Status array with 'proxied' boolean.
	 */
	public function check_dns_proxied( string $zone_id, string $host ): array {
		$result = array(
			'found'   => false,
			'proxied' => false,
			'type'    => null,
			'content' => null,
		);

		// Check A, AAAA, and CNAME records.
		foreach ( array( 'A', 'AAAA', 'CNAME' ) as $type ) {
			$records = $this->list_dns_records( $zone_id, $host, $type );

			if ( $records && count( $records ) > 0 ) {
				$record          = $records[0];
				$result['found'] = true;
				$result['type']  = $record['type'];
				$result['content'] = $record['content'];
				$result['proxied'] = $record['proxied'] ?? false;
				break;
			}
		}

		return $result;
	}

	/**
	 * List Worker routes for a zone.
	 *
	 * @param string $zone_id Zone ID.
	 * @return array|null Array of routes or null.
	 */
	public function list_routes( string $zone_id ): ?array {
		$response = $this->request( 'GET', '/zones/' . $zone_id . '/workers/routes' );

		if ( ! $response || ! isset( $response['result'] ) ) {
			return null;
		}

		return $response['result'];
	}

	/**
	 * Check if a route pattern conflicts with existing routes.
	 *
	 * @param string $zone_id Zone ID.
	 * @param string $pattern Route pattern to check.
	 * @return array Conflict info.
	 */
	public function check_route_conflict( string $zone_id, string $pattern ): array {
		$result = array(
			'has_conflict' => false,
			'conflicts'    => array(),
		);

		$routes = $this->list_routes( $zone_id );

		if ( ! $routes ) {
			return $result;
		}

		foreach ( $routes as $route ) {
			// Check for exact match or overlapping patterns.
			if ( $route['pattern'] === $pattern ) {
				$result['has_conflict'] = true;
				$result['conflicts'][]  = array(
					'id'      => $route['id'],
					'pattern' => $route['pattern'],
					'script'  => $route['script'] ?? null,
				);
			}
		}

		return $result;
	}

	/**
	 * Create a Worker route.
	 *
	 * @param string $zone_id     Zone ID.
	 * @param string $pattern     Route pattern.
	 * @param string $script_name Worker script name.
	 * @return array|null Created route or null.
	 */
	public function create_route( string $zone_id, string $pattern, string $script_name ): ?array {
		$response = $this->request(
			'POST',
			'/zones/' . $zone_id . '/workers/routes',
			array(
				'pattern' => $pattern,
				'script'  => $script_name,
			)
		);

		if ( ! $response || ! isset( $response['result'] ) ) {
			return null;
		}

		return $response['result'];
	}

	/**
	 * Delete a Worker route.
	 *
	 * @param string $zone_id  Zone ID.
	 * @param string $route_id Route ID.
	 * @return bool Success status.
	 */
	public function delete_route( string $zone_id, string $route_id ): bool {
		$response = $this->request( 'DELETE', '/zones/' . $zone_id . '/workers/routes/' . $route_id );
		return $response !== null && ( $response['success'] ?? false );
	}

	/**
	 * Upload a Worker script.
	 *
	 * @param string $account_id  Account ID.
	 * @param string $script_name Script name.
	 * @param string $script      JavaScript code.
	 * @return bool Success status.
	 */
	public function upload_worker( string $account_id, string $script_name, string $script ): bool {
		// Workers API requires multipart form data.
		$response = $this->request_multipart(
			'PUT',
			'/accounts/' . $account_id . '/workers/scripts/' . $script_name,
			$script
		);

		return $response !== null && ( $response['success'] ?? false );
	}

	/**
	 * Get Worker script info.
	 *
	 * @param string $account_id  Account ID.
	 * @param string $script_name Script name.
	 * @return array|null Script info or null.
	 */
	public function get_worker( string $account_id, string $script_name ): ?array {
		$response = $this->request( 'GET', '/accounts/' . $account_id . '/workers/scripts/' . $script_name );

		if ( ! $response || ! isset( $response['result'] ) ) {
			return null;
		}

		return $response['result'];
	}

	/**
	 * Delete a Worker script.
	 *
	 * @param string $account_id  Account ID.
	 * @param string $script_name Script name.
	 * @return bool Success status.
	 */
	public function delete_worker( string $account_id, string $script_name ): bool {
		$response = $this->request( 'DELETE', '/accounts/' . $account_id . '/workers/scripts/' . $script_name );
		return $response !== null && ( $response['success'] ?? false );
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
	 * Get last error code.
	 *
	 * @return int|null
	 */
	public function get_last_error_code(): ?int {
		return $this->last_error_code;
	}

	/**
	 * Make an API request with retry and backoff.
	 *
	 * @param string $method   HTTP method.
	 * @param string $endpoint API endpoint.
	 * @param array  $data     Request data.
	 * @return array|null Response data or null on failure.
	 */
	protected function request( string $method, string $endpoint, array $data = array() ): ?array {
		if ( ! $this->is_configured() ) {
			$this->last_error = __( 'API token not configured.', 'edge-link-router' );
			return null;
		}

		$url = self::API_BASE . $endpoint;

		// Add query params for GET requests.
		if ( $method === 'GET' && ! empty( $data ) ) {
			$url = add_query_arg( $data, $url );
			$data = array();
		}

		$start_time = time();
		$attempt    = 0;

		while ( $attempt < self::MAX_RETRIES ) {
			// Check time budget for UI operations.
			if ( $this->use_time_budget && ( time() - $start_time ) > self::UI_TIME_BUDGET ) {
				$this->last_error = __( 'Request timed out. Please try again later.', 'edge-link-router' );
				return null;
			}

			$args = array(
				'method'  => $method,
				'headers' => array(
					'Authorization' => 'Bearer ' . $this->token,
					'Content-Type'  => 'application/json',
				),
				'timeout' => 15,
			);

			if ( ! empty( $data ) && in_array( $method, array( 'POST', 'PUT', 'PATCH' ), true ) ) {
				$args['body'] = wp_json_encode( $data );
			}

			$response = wp_remote_request( $url, $args );

			if ( is_wp_error( $response ) ) {
				$this->last_error = $response->get_error_message();
				return null;
			}

			$code = wp_remote_retrieve_response_code( $response );
			$body = wp_remote_retrieve_body( $response );
			$json = json_decode( $body, true );

			// Handle rate limiting.
			if ( $code === 429 ) {
				++$attempt;

				if ( $attempt >= self::MAX_RETRIES ) {
					$this->last_error      = __( 'Rate limit exceeded. Please try again later.', 'edge-link-router' );
					$this->last_error_code = 429;
					return null;
				}

				// Calculate backoff with jitter.
				$retry_after = $this->get_retry_after( $response );
				$backoff     = min( self::BASE_BACKOFF * pow( 2, $attempt ) + wp_rand( 0, 1000 ) / 1000, self::MAX_BACKOFF );
				$delay       = $retry_after ?: $backoff;

				// Check if delay exceeds time budget.
				if ( $this->use_time_budget && ( time() - $start_time + $delay ) > self::UI_TIME_BUDGET ) {
					$this->last_error = __( 'Rate limited. Please try again later.', 'edge-link-router' );
					return null;
				}

				sleep( (int) ceil( $delay ) );
				continue;
			}

			// Handle other errors.
			if ( $code >= 400 ) {
				$this->last_error_code = $code;

				if ( $json && isset( $json['errors'] ) && is_array( $json['errors'] ) ) {
					$errors = array_map(
						fn( $e ) => $e['message'] ?? __( 'Unknown error', 'edge-link-router' ),
						$json['errors']
					);
					$this->last_error = implode( '; ', $errors );
				} else {
					$this->last_error = sprintf(
						/* translators: %d: HTTP status code */
						__( 'API error: HTTP %d', 'edge-link-router' ),
						$code
					);
				}

				return null;
			}

			// Success.
			$this->last_error      = null;
			$this->last_error_code = null;

			return $json;
		}

		return null;
	}

	/**
	 * Make a multipart request for Worker upload.
	 *
	 * @param string $method   HTTP method.
	 * @param string $endpoint API endpoint.
	 * @param string $script   Worker script content.
	 * @return array|null Response data or null on failure.
	 */
	protected function request_multipart( string $method, string $endpoint, string $script ): ?array {
		if ( ! $this->is_configured() ) {
			$this->last_error = __( 'API token not configured.', 'edge-link-router' );
			return null;
		}

		$url      = self::API_BASE . $endpoint;
		$boundary = wp_generate_password( 24, false );

		$body  = "--{$boundary}\r\n";
		$body .= "Content-Disposition: form-data; name=\"script\"; filename=\"script.js\"\r\n";
		$body .= "Content-Type: application/javascript\r\n\r\n";
		$body .= $script . "\r\n";
		$body .= "--{$boundary}--\r\n";

		$args = array(
			'method'  => $method,
			'headers' => array(
				'Authorization' => 'Bearer ' . $this->token,
				'Content-Type'  => 'multipart/form-data; boundary=' . $boundary,
			),
			'body'    => $body,
			'timeout' => 30,
		);

		$response = wp_remote_request( $url, $args );

		if ( is_wp_error( $response ) ) {
			$this->last_error = $response->get_error_message();
			return null;
		}

		$code = wp_remote_retrieve_response_code( $response );
		$json = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( $code >= 400 ) {
			$this->last_error_code = $code;

			if ( $json && isset( $json['errors'] ) && is_array( $json['errors'] ) ) {
				$errors = array_map(
					fn( $e ) => $e['message'] ?? __( 'Unknown error', 'edge-link-router' ),
					$json['errors']
				);
				$this->last_error = implode( '; ', $errors );
			} else {
				$this->last_error = sprintf(
					/* translators: %d: HTTP status code */
					__( 'API error: HTTP %d', 'edge-link-router' ),
					$code
				);
			}

			return null;
		}

		return $json;
	}

	/**
	 * Get Retry-After value from response headers.
	 *
	 * @param array $response WP HTTP response.
	 * @return int|null Seconds to wait or null.
	 */
	private function get_retry_after( array $response ): ?int {
		$headers = wp_remote_retrieve_headers( $response );

		if ( isset( $headers['retry-after'] ) ) {
			$value = $headers['retry-after'];

			// Could be seconds or HTTP date.
			if ( is_numeric( $value ) ) {
				return (int) $value;
			}

			$timestamp = strtotime( $value );
			if ( $timestamp ) {
				return max( 0, $timestamp - time() );
			}
		}

		// Check X-RateLimit headers.
		if ( isset( $headers['x-ratelimit-reset'] ) ) {
			$reset = (int) $headers['x-ratelimit-reset'];
			return max( 0, $reset - time() );
		}

		return null;
	}
}
