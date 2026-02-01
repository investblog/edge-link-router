<?php
/**
 * Cloudflare Diagnostics.
 *
 * @package EdgeLinkRouter
 */

namespace CFELR\Integrations\Cloudflare;

/**
 * Diagnostic checks for Cloudflare integration.
 */
class Diagnostics {

	/**
	 * Check status constants.
	 */
	public const STATUS_OK   = 'ok';
	public const STATUS_WARN = 'warn';
	public const STATUS_FAIL = 'fail';

	/**
	 * Default prefix.
	 *
	 * @var string
	 */
	private string $prefix = 'go';

	/**
	 * Reserved paths that cannot be used as prefix.
	 *
	 * @var array
	 */
	private const RESERVED_PATHS = array(
		'wp-admin',
		'wp-json',
		'wp-login.php',
		'wp-login',
		'feed',
		'sitemap',
		'sitemap.xml',
		'xmlrpc.php',
		'wp-content',
		'wp-includes',
	);

	/**
	 * Constructor.
	 */
	public function __construct() {
		$settings     = get_option( 'cfelr_settings', array() );
		$this->prefix = $settings['prefix'] ?? 'go';
	}

	/**
	 * Auto-detect host from WordPress settings.
	 *
	 * @return array Host info with primary and alias.
	 */
	public function auto_detect_host(): array {
		$home_host = wp_parse_url( home_url(), PHP_URL_HOST );
		$site_host = wp_parse_url( site_url(), PHP_URL_HOST );

		// Primary host is home_url host, fallback to site_url.
		$primary = $home_host ?: $site_host;

		// Determine www alias.
		$alias = null;
		if ( $primary ) {
			if ( str_starts_with( $primary, 'www.' ) ) {
				$alias = substr( $primary, 4 );
			} else {
				$alias = 'www.' . $primary;
			}
		}

		return array(
			'primary' => $primary,
			'alias'   => $alias,
			'home'    => home_url(),
			'site'    => site_url(),
		);
	}

	/**
	 * Run all public diagnostics (no token required).
	 *
	 * @param string $host Host to check.
	 * @return array Array of check results.
	 */
	public function run_public_checks( string $host ): array {
		return array(
			$this->check_ns( $host ),
			$this->check_http_headers( $host ),
			$this->check_cdn_cgi_trace( $host ),
			$this->check_https( $host ),
			$this->check_prefix_conflict(),
		);
	}

	/**
	 * Run authorized diagnostics (requires token).
	 *
	 * @param string $host Host to check.
	 * @return array Array of check results.
	 */
	public function run_authorized_checks( string $host ): array {
		$client = new Client();

		if ( ! $client->is_configured() ) {
			return array(
				array(
					'name'     => 'token_required',
					'label'    => __( 'API Token Required', 'edge-link-router' ),
					'status'   => self::STATUS_FAIL,
					'message'  => __( 'Please configure your Cloudflare API token first.', 'edge-link-router' ),
					'fix_hint' => null,
				),
			);
		}

		// First check permissions (verify token).
		$perm_check = $this->check_permissions( $client );

		if ( $perm_check['status'] === self::STATUS_FAIL ) {
			return array( $perm_check );
		}

		// Check zone match and get zone info.
		$zone_check = $this->check_zone_match( $client, $host );

		if ( $zone_check['status'] === self::STATUS_FAIL ) {
			return array( $perm_check, $zone_check );
		}

		// Get zone data from check.
		$zone_data = $zone_check['_zone_data'] ?? null;

		// Run remaining checks with zone data.
		return array(
			$perm_check,
			$zone_check,
			$this->check_proxied_dns( $client, $host, $zone_data ),
			$this->check_route_conflict_authorized( $client, $zone_data ),
		);
	}

	/**
	 * Check NS records for Cloudflare.
	 *
	 * @param string $host Host to check.
	 * @return array
	 */
	private function check_ns( string $host ): array {
		$result = array(
			'name'     => 'ns_cloudflare',
			'label'    => __( 'Cloudflare Nameservers', 'edge-link-router' ),
			'status'   => self::STATUS_FAIL,
			'message'  => '',
			'fix_hint' => null,
		);

		// Check if dns_get_record is available.
		if ( ! function_exists( 'dns_get_record' ) ) {
			$result['status']  = self::STATUS_WARN;
			$result['message'] = __( 'DNS lookup not available on this server. Cannot verify nameservers.', 'edge-link-router' );
			return $result;
		}

		// Get NS records.
		// phpcs:ignore WordPress.WP.AlternativeFunctions.dns_get_record_dns_get_record
		$ns_records = @dns_get_record( $host, DNS_NS );

		if ( empty( $ns_records ) ) {
			// Try getting NS for root domain if host is subdomain.
			$parts = explode( '.', $host );
			if ( count( $parts ) > 2 ) {
				$root_domain = implode( '.', array_slice( $parts, -2 ) );
				// phpcs:ignore WordPress.WP.AlternativeFunctions.dns_get_record_dns_get_record
				$ns_records = @dns_get_record( $root_domain, DNS_NS );
			}
		}

		if ( empty( $ns_records ) ) {
			$result['status']   = self::STATUS_WARN;
			$result['message']  = __( 'Could not retrieve NS records.', 'edge-link-router' );
			$result['fix_hint'] = __( 'DNS lookup failed. This may be a temporary issue or DNS configuration problem.', 'edge-link-router' );
			return $result;
		}

		// Check if any NS points to Cloudflare.
		$cloudflare_ns = false;
		$ns_list       = array();

		foreach ( $ns_records as $record ) {
			if ( isset( $record['target'] ) ) {
				$ns_list[] = $record['target'];
				if ( str_contains( strtolower( $record['target'] ), '.ns.cloudflare.com' ) ) {
					$cloudflare_ns = true;
				}
			}
		}

		if ( $cloudflare_ns ) {
			$result['status']  = self::STATUS_OK;
			$result['message'] = __( 'Domain is using Cloudflare nameservers.', 'edge-link-router' );
		} else {
			$result['status']   = self::STATUS_WARN;
			$result['message']  = sprintf(
				/* translators: %s: list of nameservers */
				__( 'Domain is not using Cloudflare nameservers. Current NS: %s', 'edge-link-router' ),
				implode( ', ', array_slice( $ns_list, 0, 3 ) )
			);
			$result['fix_hint'] = __( 'Edge features require Cloudflare. You can still use Cloudflare in CNAME/partial setup mode.', 'edge-link-router' );
		}

		return $result;
	}

	/**
	 * Check HTTP headers for Cloudflare presence.
	 *
	 * @param string $host Host to check.
	 * @return array
	 */
	private function check_http_headers( string $host ): array {
		$result = array(
			'name'     => 'http_headers',
			'label'    => __( 'Cloudflare HTTP Headers', 'edge-link-router' ),
			'status'   => self::STATUS_FAIL,
			'message'  => '',
			'fix_hint' => null,
		);

		$url      = 'https://' . $host . '/';
		$response = wp_remote_request(
			$url,
			array(
				'method'      => 'HEAD',
				'timeout'     => 5,
				'redirection' => 2,
				'sslverify'   => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			$result['status']   = self::STATUS_WARN;
			$result['message']  = sprintf(
				/* translators: %s: error message */
				__( 'Could not connect to site: %s', 'edge-link-router' ),
				$response->get_error_message()
			);
			$result['fix_hint'] = __( 'Ensure your site is accessible over HTTPS.', 'edge-link-router' );
			return $result;
		}

		$headers   = wp_remote_retrieve_headers( $response );
		$cf_ray    = $headers['cf-ray'] ?? null;
		$server    = strtolower( $headers['server'] ?? '' );
		$cf_cache  = $headers['cf-cache-status'] ?? null;

		$is_cloudflare = $cf_ray || str_contains( $server, 'cloudflare' ) || $cf_cache;

		if ( $is_cloudflare ) {
			$result['status']  = self::STATUS_OK;
			$result['message'] = __( 'Site is being served through Cloudflare.', 'edge-link-router' );

			if ( $cf_ray ) {
				$result['message'] .= ' ' . sprintf(
					/* translators: %s: CF-Ray header value */
					__( '(CF-Ray: %s)', 'edge-link-router' ),
					$cf_ray
				);
			}
		} else {
			$result['status']   = self::STATUS_WARN;
			$result['message']  = __( 'Cloudflare headers not detected. Site may not be proxied through Cloudflare.', 'edge-link-router' );
			$result['fix_hint'] = __( 'Ensure your DNS record has the orange cloud (proxied) enabled in Cloudflare.', 'edge-link-router' );
		}

		return $result;
	}

	/**
	 * Check /cdn-cgi/trace endpoint.
	 *
	 * @param string $host Host to check.
	 * @return array
	 */
	private function check_cdn_cgi_trace( string $host ): array {
		$result = array(
			'name'     => 'cdn_cgi_trace',
			'label'    => __( 'Cloudflare Trace Endpoint', 'edge-link-router' ),
			'status'   => self::STATUS_FAIL,
			'message'  => '',
			'fix_hint' => null,
		);

		$url      = 'https://' . $host . '/cdn-cgi/trace';
		$response = wp_remote_get(
			$url,
			array(
				'timeout'     => 5,
				'redirection' => 0,
				'sslverify'   => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			$result['status']   = self::STATUS_WARN;
			$result['message']  = __( 'Could not reach Cloudflare trace endpoint.', 'edge-link-router' );
			$result['fix_hint'] = __( 'This endpoint is only available when traffic is proxied through Cloudflare.', 'edge-link-router' );
			return $result;
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( $code === 200 ) {
			$body = wp_remote_retrieve_body( $response );

			// Parse trace response.
			$info = array();
			foreach ( explode( "\n", $body ) as $line ) {
				if ( strpos( $line, '=' ) !== false ) {
					list( $key, $value ) = explode( '=', $line, 2 );
					$info[ trim( $key ) ] = trim( $value );
				}
			}

			$result['status']  = self::STATUS_OK;
			$result['message'] = __( 'Cloudflare trace endpoint is accessible.', 'edge-link-router' );

			if ( isset( $info['colo'] ) ) {
				$result['message'] .= ' ' . sprintf(
					/* translators: %s: Cloudflare datacenter code */
					__( '(Datacenter: %s)', 'edge-link-router' ),
					$info['colo']
				);
			}
		} else {
			$result['status']   = self::STATUS_WARN;
			$result['message']  = sprintf(
				/* translators: %d: HTTP status code */
				__( 'Cloudflare trace endpoint returned HTTP %d.', 'edge-link-router' ),
				$code
			);
			$result['fix_hint'] = __( 'This may indicate the site is not proxied through Cloudflare.', 'edge-link-router' );
		}

		return $result;
	}

	/**
	 * Check HTTPS availability.
	 *
	 * @param string $host Host to check.
	 * @return array
	 */
	private function check_https( string $host ): array {
		$result = array(
			'name'     => 'https',
			'label'    => __( 'HTTPS Availability', 'edge-link-router' ),
			'status'   => self::STATUS_FAIL,
			'message'  => '',
			'fix_hint' => null,
		);

		$url      = 'https://' . $host . '/';
		$response = wp_remote_request(
			$url,
			array(
				'method'      => 'HEAD',
				'timeout'     => 5,
				'redirection' => 2,
				'sslverify'   => true,
			)
		);

		if ( is_wp_error( $response ) ) {
			$error = $response->get_error_message();

			// Check if it's an SSL error.
			if ( str_contains( strtolower( $error ), 'ssl' ) || str_contains( strtolower( $error ), 'certificate' ) ) {
				$result['status']   = self::STATUS_FAIL;
				$result['message']  = __( 'SSL certificate error.', 'edge-link-router' );
				$result['fix_hint'] = __( 'Ensure your SSL certificate is valid. Cloudflare provides free SSL certificates.', 'edge-link-router' );
			} else {
				$result['status']   = self::STATUS_FAIL;
				$result['message']  = sprintf(
					/* translators: %s: error message */
					__( 'Could not connect via HTTPS: %s', 'edge-link-router' ),
					$error
				);
				$result['fix_hint'] = __( 'Edge mode requires HTTPS. Ensure your site is accessible over HTTPS.', 'edge-link-router' );
			}

			return $result;
		}

		$code = wp_remote_retrieve_response_code( $response );

		if ( $code >= 200 && $code < 400 ) {
			$result['status']  = self::STATUS_OK;
			$result['message'] = __( 'Site is accessible over HTTPS.', 'edge-link-router' );
		} else {
			$result['status']   = self::STATUS_WARN;
			$result['message']  = sprintf(
				/* translators: %d: HTTP status code */
				__( 'HTTPS request returned HTTP %d.', 'edge-link-router' ),
				$code
			);
			$result['fix_hint'] = __( 'Ensure your site responds correctly to HTTPS requests.', 'edge-link-router' );
		}

		return $result;
	}

	/**
	 * Check for prefix conflicts.
	 *
	 * @return array
	 */
	private function check_prefix_conflict(): array {
		$result = array(
			'name'     => 'prefix_conflict',
			'label'    => __( 'Redirect Prefix Availability', 'edge-link-router' ),
			'status'   => self::STATUS_OK,
			'message'  => '',
			'fix_hint' => null,
		);

		$prefix = $this->prefix;
		$issues = array();

		// Check reserved paths.
		if ( in_array( $prefix, self::RESERVED_PATHS, true ) ) {
			$issues[] = sprintf(
				/* translators: %s: prefix */
				__( '"%s" is a reserved WordPress path.', 'edge-link-router' ),
				$prefix
			);
		}

		// Check if page/post exists with this slug.
		$page = get_page_by_path( $prefix );
		if ( $page ) {
			$issues[] = sprintf(
				/* translators: %1$s: prefix, %2$s: post type */
				__( 'A %2$s with slug "%1$s" already exists.', 'edge-link-router' ),
				$prefix,
				get_post_type_object( $page->post_type )->labels->singular_name
			);
		}

		// Check custom post types.
		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		foreach ( $post_types as $post_type ) {
			if ( isset( $post_type->rewrite['slug'] ) && $post_type->rewrite['slug'] === $prefix ) {
				$issues[] = sprintf(
					/* translators: %1$s: prefix, %2$s: post type name */
					__( 'Post type "%2$s" uses the slug "%1$s".', 'edge-link-router' ),
					$prefix,
					$post_type->labels->name
				);
			}
		}

		// Check taxonomies.
		$taxonomies = get_taxonomies( array( 'public' => true ), 'objects' );
		foreach ( $taxonomies as $taxonomy ) {
			if ( isset( $taxonomy->rewrite['slug'] ) && $taxonomy->rewrite['slug'] === $prefix ) {
				$issues[] = sprintf(
					/* translators: %1$s: prefix, %2$s: taxonomy name */
					__( 'Taxonomy "%2$s" uses the slug "%1$s".', 'edge-link-router' ),
					$prefix,
					$taxonomy->labels->name
				);
			}
		}

		if ( ! empty( $issues ) ) {
			$result['status']   = self::STATUS_WARN;
			$result['message']  = implode( ' ', $issues );
			$result['fix_hint'] = __( 'Consider using a different prefix to avoid conflicts. You can change it in the plugin settings.', 'edge-link-router' );
		} else {
			$result['message'] = sprintf(
				/* translators: %s: prefix */
				__( 'Prefix "/%s/" is available and has no conflicts.', 'edge-link-router' ),
				$prefix
			);
		}

		return $result;
	}

	/**
	 * Check zone match via API.
	 *
	 * @param Client $client Cloudflare client.
	 * @param string $host   Host to check.
	 * @return array
	 */
	private function check_zone_match( Client $client, string $host ): array {
		$result = array(
			'name'       => 'zone_match',
			'label'      => __( 'Cloudflare Zone Match', 'edge-link-router' ),
			'status'     => self::STATUS_FAIL,
			'message'    => '',
			'fix_hint'   => null,
			'_zone_data' => null,
		);

		$zone = $client->find_zone_for_host( $host );

		if ( ! $zone ) {
			$result['message']  = $client->get_last_error() ?: __( 'Could not find a Cloudflare zone for this domain.', 'edge-link-router' );
			$result['fix_hint'] = __( 'Ensure the domain is added to your Cloudflare account and the API token has Zone:Read permission.', 'edge-link-router' );
			return $result;
		}

		// Store zone data for later checks.
		$result['_zone_data'] = $zone;

		// Save zone info to integration state for later use.
		$this->save_integration_state( $zone );

		$result['status']  = self::STATUS_OK;
		$result['message'] = sprintf(
			/* translators: %1$s: zone name, %2$s: zone ID */
			__( 'Zone found: %1$s (ID: %2$s)', 'edge-link-router' ),
			$zone['zone_name'],
			substr( $zone['zone_id'], 0, 8 ) . '...'
		);

		return $result;
	}

	/**
	 * Check proxied DNS record.
	 *
	 * @param Client     $client    Cloudflare client.
	 * @param string     $host      Host to check.
	 * @param array|null $zone_data Zone data from previous check.
	 * @return array
	 */
	private function check_proxied_dns( Client $client, string $host, ?array $zone_data ): array {
		$result = array(
			'name'     => 'proxied_dns',
			'label'    => __( 'Proxied DNS Record', 'edge-link-router' ),
			'status'   => self::STATUS_FAIL,
			'message'  => '',
			'fix_hint' => null,
		);

		if ( ! $zone_data || empty( $zone_data['zone_id'] ) ) {
			$result['message'] = __( 'Zone information not available.', 'edge-link-router' );
			return $result;
		}

		$dns_check = $client->check_dns_proxied( $zone_data['zone_id'], $host );

		if ( ! $dns_check['found'] ) {
			$result['status']   = self::STATUS_WARN;
			$result['message']  = __( 'No DNS record found for this hostname.', 'edge-link-router' );
			$result['fix_hint'] = __( 'Ensure an A, AAAA, or CNAME record exists for this hostname.', 'edge-link-router' );
			return $result;
		}

		if ( $dns_check['proxied'] ) {
			$result['status']  = self::STATUS_OK;
			$result['message'] = sprintf(
				/* translators: %s: DNS record type */
				__( 'DNS record (%s) is proxied through Cloudflare.', 'edge-link-router' ),
				$dns_check['type']
			);
		} else {
			$result['status']   = self::STATUS_FAIL;
			$result['message']  = sprintf(
				/* translators: %s: DNS record type */
				__( 'DNS record (%s) is not proxied (grey cloud).', 'edge-link-router' ),
				$dns_check['type']
			);
			$result['fix_hint'] = __( 'Enable the proxy (orange cloud) in Cloudflare DNS settings. Workers only work with proxied records.', 'edge-link-router' );
		}

		return $result;
	}

	/**
	 * Check API token permissions.
	 *
	 * @param Client $client Cloudflare client.
	 * @return array
	 */
	private function check_permissions( Client $client ): array {
		$result = array(
			'name'     => 'permissions',
			'label'    => __( 'API Token Status', 'edge-link-router' ),
			'status'   => self::STATUS_FAIL,
			'message'  => '',
			'fix_hint' => null,
		);

		$verify = $client->verify_token();

		if ( ! $verify ) {
			$result['message']  = $client->get_last_error() ?: __( 'Could not verify API token.', 'edge-link-router' );
			$result['fix_hint'] = __( 'Check that your API token is valid and not expired.', 'edge-link-router' );
			return $result;
		}

		if ( ( $verify['status'] ?? '' ) !== 'active' ) {
			$result['message']  = sprintf(
				/* translators: %s: token status */
				__( 'API token is not active (status: %s).', 'edge-link-router' ),
				$verify['status'] ?? 'unknown'
			);
			$result['fix_hint'] = __( 'Create a new API token in Cloudflare dashboard.', 'edge-link-router' );
			return $result;
		}

		// Check expiration.
		if ( ! empty( $verify['expires_on'] ) ) {
			$expires = strtotime( $verify['expires_on'] );
			if ( $expires && $expires < time() + 86400 * 7 ) {
				$result['status']   = self::STATUS_WARN;
				$result['message']  = __( 'API token is active but expires soon.', 'edge-link-router' );
				$result['fix_hint'] = sprintf(
					/* translators: %s: expiration date */
					__( 'Token expires on %s. Consider creating a new one.', 'edge-link-router' ),
					wp_date( get_option( 'date_format' ), $expires )
				);
				return $result;
			}
		}

		$result['status']  = self::STATUS_OK;
		$result['message'] = __( 'API token is valid and active.', 'edge-link-router' );

		return $result;
	}

	/**
	 * Check for Worker route conflicts (authorized version).
	 *
	 * @param Client     $client    Cloudflare client.
	 * @param array|null $zone_data Zone data.
	 * @return array
	 */
	private function check_route_conflict_authorized( Client $client, ?array $zone_data ): array {
		$result = array(
			'name'     => 'route_conflict',
			'label'    => __( 'Worker Route Availability', 'edge-link-router' ),
			'status'   => self::STATUS_OK,
			'message'  => '',
			'fix_hint' => null,
		);

		if ( ! $zone_data || empty( $zone_data['zone_id'] ) ) {
			$result['status']  = self::STATUS_WARN;
			$result['message'] = __( 'Could not check routes without zone information.', 'edge-link-router' );
			return $result;
		}

		// Build the route pattern we would use.
		$host    = wp_parse_url( home_url(), PHP_URL_HOST );
		$pattern = $host . '/' . $this->prefix . '/*';

		$conflict = $client->check_route_conflict( $zone_data['zone_id'], $pattern );

		if ( $conflict['has_conflict'] ) {
			$existing = $conflict['conflicts'][0] ?? array();
			$result['status']   = self::STATUS_WARN;
			$result['message']  = sprintf(
				/* translators: %s: route pattern */
				__( 'Route pattern "%s" is already in use.', 'edge-link-router' ),
				$pattern
			);

			if ( ! empty( $existing['script'] ) && $existing['script'] === 'cfelr-edge-worker' ) {
				$result['status']  = self::STATUS_OK;
				$result['message'] = __( 'Route is already configured for this plugin.', 'edge-link-router' );
			} else {
				$result['fix_hint'] = __( 'Another Worker is using this route. You may need to remove it first or use a different prefix.', 'edge-link-router' );
			}
		} else {
			$result['message'] = sprintf(
				/* translators: %s: route pattern */
				__( 'Route pattern "%s" is available.', 'edge-link-router' ),
				$pattern
			);
		}

		return $result;
	}

	/**
	 * Save integration state to database.
	 *
	 * @param array $zone_data Zone data to save.
	 * @return void
	 */
	private function save_integration_state( array $zone_data ): void {
		global $wpdb;

		$table = $wpdb->prefix . 'cfelr_integrations';
		$state = wp_json_encode(
			array(
				'zone_id'    => $zone_data['zone_id'],
				'zone_name'  => $zone_data['zone_name'],
				'account_id' => $zone_data['account_id'],
				'updated_at' => gmdate( 'c' ),
			)
		);

		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$existing = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT id FROM {$table} WHERE provider = %s", // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				'cloudflare'
			)
		);

		if ( $existing ) {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
			$wpdb->update(
				$table,
				array( 'state_json' => $state ),
				array( 'provider' => 'cloudflare' ),
				array( '%s' ),
				array( '%s' )
			);
		} else {
			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
			$wpdb->insert(
				$table,
				array(
					'provider'   => 'cloudflare',
					'state_json' => $state,
				),
				array( '%s', '%s' )
			);
		}
	}

	/**
	 * Get overall status from check results.
	 *
	 * @param array $checks Array of check results.
	 * @return string Overall status: ok, warn, or fail.
	 */
	public function get_overall_status( array $checks ): string {
		$has_fail = false;
		$has_warn = false;

		foreach ( $checks as $check ) {
			if ( $check['status'] === self::STATUS_FAIL ) {
				$has_fail = true;
			} elseif ( $check['status'] === self::STATUS_WARN ) {
				$has_warn = true;
			}
		}

		if ( $has_fail ) {
			return self::STATUS_FAIL;
		}

		if ( $has_warn ) {
			return self::STATUS_WARN;
		}

		return self::STATUS_OK;
	}
}
