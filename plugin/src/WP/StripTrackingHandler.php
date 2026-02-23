<?php
/**
 * Strip Tracking Parameters Handler.
 *
 * @package EdgeLinkRouter
 */

namespace CFELR\WP;

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

use CFELR\WP\Admin\Pages\SettingsPage;

/**
 * Strips ad-platform tracking parameters from URLs and 301-redirects to the clean URL.
 */
class StripTrackingHandler {

	/**
	 * Initialize the handler.
	 *
	 * @return void
	 */
	public function init(): void {
		// Hook before everything else (RewriteHandler is at priority 1).
		add_action( 'template_redirect', array( $this, 'handle' ), 0 );
	}

	/**
	 * Strip tracking parameters and redirect if needed.
	 *
	 * @return void
	 */
	public function handle(): void {
		// Don't run on admin, AJAX, REST, or cron requests.
		if ( is_admin() || wp_doing_ajax() || defined( 'REST_REQUEST' ) || defined( 'DOING_CRON' ) ) {
			return;
		}

		// Only process GET requests.
		if ( isset( $_SERVER['REQUEST_METHOD'] ) && 'GET' !== $_SERVER['REQUEST_METHOD'] ) {
			return;
		}

		$settings = SettingsPage::get_strip_tracking_settings();

		if ( ! $settings['enabled'] ) {
			return;
		}

		$query_string = isset( $_SERVER['QUERY_STRING'] ) ? sanitize_text_field( wp_unslash( $_SERVER['QUERY_STRING'] ) ) : '';

		if ( empty( $query_string ) ) {
			return;
		}

		$params = array();
		wp_parse_str( $query_string, $params );

		if ( empty( $params ) ) {
			return;
		}

		$strip_list = array_map( 'strtolower', $settings['params'] );
		$found      = false;

		foreach ( array_keys( $params ) as $key ) {
			if ( in_array( strtolower( $key ), $strip_list, true ) ) {
				unset( $params[ $key ] );
				$found = true;
			}
		}

		if ( ! $found ) {
			return;
		}

		// Build clean URL.
		$clean_url = home_url( sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '/' ) ) );

		// Remove existing query string from the URL.
		$clean_url = strtok( $clean_url, '?' );

		if ( ! empty( $params ) ) {
			$clean_url .= '?' . http_build_query( $params );
		}

		wp_redirect( $clean_url, 301, 'Edge Link Router' ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit;
	}
}
