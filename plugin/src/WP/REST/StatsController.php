<?php
/**
 * Stats REST Controller.
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
 * REST API controller for statistics.
 */
class StatsController extends WP_REST_Controller {

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
	protected $rest_base = 'stats';

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
				'args'                => array(
					'days' => array(
						'description' => __( 'Number of days to include.', 'edge-link-router' ),
						'type'        => 'integer',
						'default'     => 30,
						'enum'        => array( 7, 30, 90 ),
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/(?P<link_id>[\d]+)',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'get_item' ),
				'permission_callback' => array( $this, 'get_items_permissions_check' ),
				'args'                => array(
					'link_id' => array(
						'validate_callback' => fn( $param ) => is_numeric( $param ),
					),
					'days'    => array(
						'type'    => 'integer',
						'default' => 30,
						'enum'    => array( 7, 30, 90 ),
					),
				),
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
	 * Get statistics overview.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_items( $request ) {
		// Stub - implementation in M2.
		return rest_ensure_response(
			array(
				'top_links'    => array(),
				'total_clicks' => 0,
				'period_days'  => $request->get_param( 'days' ),
			)
		);
	}

	/**
	 * Get stats for a single link.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		// Stub - implementation in M2.
		return rest_ensure_response(
			array(
				'link_id'     => (int) $request->get_param( 'link_id' ),
				'total'       => 0,
				'daily_stats' => array(),
			)
		);
	}
}
