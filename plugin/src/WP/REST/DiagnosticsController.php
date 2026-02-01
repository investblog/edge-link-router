<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Diagnostics REST Controller.
 *
 * @package EdgeLinkRouter
 */

namespace CFELR\WP\REST;

use WP_REST_Controller;
use WP_REST_Server;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * REST API controller for diagnostics.
 */
class DiagnosticsController extends WP_REST_Controller {

	/**
	 * Namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'cfelr/v1';

	/**
	 * Resource name.
	 *
	 * @var string
	 */
	protected $rest_base = 'diagnostics';

	/**
	 * Register routes.
	 *
	 * @return void
	 */
	public function register_routes(): void {
		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base,
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_items' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/run',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'run_diagnostics' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
			)
		);
	}

	/**
	 * Check permissions.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Get cached diagnostics results.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_items( $request ) {
		// Stub - implementation in M3.
		return rest_ensure_response(
			array(
				'host'    => wp_parse_url( home_url(), PHP_URL_HOST ),
				'checks'  => array(),
				'last_run' => null,
			)
		);
	}

	/**
	 * Run diagnostics.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function run_diagnostics( $request ) {
		// Stub - implementation in M3.
		return rest_ensure_response(
			array(
				'host'   => wp_parse_url( home_url(), PHP_URL_HOST ),
				'checks' => array(
					array(
						'name'     => 'ns_cloudflare',
						'status'   => 'pending',
						'message'  => __( 'Not yet implemented.', 'edge-link-router' ),
						'fix_hint' => null,
					),
				),
				'last_run' => gmdate( 'c' ),
			)
		);
	}
}
