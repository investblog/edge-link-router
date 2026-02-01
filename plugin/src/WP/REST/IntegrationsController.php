<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Integrations REST Controller.
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
 * REST API controller for integrations.
 */
class IntegrationsController extends WP_REST_Controller {

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
	protected $rest_base = 'integrations';

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
			'/' . $this->rest_base . '/cloudflare',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_cloudflare' ),
					'permission_callback' => array( $this, 'get_items_permissions_check' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_cloudflare' ),
					'permission_callback' => array( $this, 'update_item_permissions_check' ),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/cloudflare/enable',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'enable_cloudflare' ),
				'permission_callback' => array( $this, 'update_item_permissions_check' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/cloudflare/disable',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'disable_cloudflare' ),
				'permission_callback' => array( $this, 'update_item_permissions_check' ),
			)
		);

		register_rest_route(
			$this->namespace,
			'/' . $this->rest_base . '/cloudflare/publish',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'publish_cloudflare' ),
				'permission_callback' => array( $this, 'update_item_permissions_check' ),
			)
		);
	}

	/**
	 * Check read permissions.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function get_items_permissions_check( $request ) {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Check write permissions.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return bool|WP_Error
	 */
	public function update_item_permissions_check( $request ) {
		return current_user_can( 'manage_options' );
	}

	/**
	 * Get all integrations status.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_items( $request ) {
		return rest_ensure_response(
			array(
				'cloudflare' => array(
					'configured' => false,
					'health'     => 'wp-only',
				),
				'threeofonest' => array(
					'configured' => false,
					'available'  => false,
				),
			)
		);
	}

	/**
	 * Get Cloudflare integration status.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_cloudflare( $request ) {
		// Stub - implementation in M4.
		$health = new \CFELR\Integrations\Cloudflare\Health();

		return rest_ensure_response(
			array(
				'configured' => false,
				'health'     => $health->get_status(),
			)
		);
	}

	/**
	 * Update Cloudflare settings (save token).
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function update_cloudflare( $request ) {
		// Stub - implementation in M4.
		return new WP_Error( 'not_implemented', __( 'Not implemented yet.', 'edge-link-router' ), array( 'status' => 501 ) );
	}

	/**
	 * Enable Cloudflare edge mode.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function enable_cloudflare( $request ) {
		// Stub - implementation in M5.
		return new WP_Error( 'not_implemented', __( 'Not implemented yet.', 'edge-link-router' ), array( 'status' => 501 ) );
	}

	/**
	 * Disable Cloudflare edge mode.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function disable_cloudflare( $request ) {
		// Stub - implementation in M5.
		return new WP_Error( 'not_implemented', __( 'Not implemented yet.', 'edge-link-router' ), array( 'status' => 501 ) );
	}

	/**
	 * Publish snapshot to Cloudflare.
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return WP_REST_Response|WP_Error
	 */
	public function publish_cloudflare( $request ) {
		// Stub - implementation in M5.
		return new WP_Error( 'not_implemented', __( 'Not implemented yet.', 'edge-link-router' ), array( 'status' => 501 ) );
	}
}
