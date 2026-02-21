<?php
/**
 * 404 Catch-All Redirect Handler.
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
 * Redirects 404 pages to a configured URL when enabled.
 */
class NotFoundHandler {

	/**
	 * Initialize the handler.
	 *
	 * @return void
	 */
	public function init(): void {
		// Hook late so other plugins and our RewriteHandler (priority 1) run first.
		add_action( 'template_redirect', array( $this, 'handle_404' ), 99 );
	}

	/**
	 * Redirect 404 pages if catch-all is enabled.
	 *
	 * @return void
	 */
	public function handle_404(): void {
		// Only act on 404 pages.
		if ( ! is_404() ) {
			return;
		}

		// Don't redirect admin, AJAX, REST, or cron requests.
		if ( is_admin() || wp_doing_ajax() || defined( 'REST_REQUEST' ) || defined( 'DOING_CRON' ) ) {
			return;
		}

		$settings = SettingsPage::get_catch_all_settings();

		if ( ! $settings['enabled'] ) {
			return;
		}

		$target_url = $settings['url'];

		// Fallback to homepage if target is empty.
		if ( empty( $target_url ) ) {
			$target_url = home_url( '/' );
		}

		wp_redirect( $target_url, $settings['status_code'], 'Edge Link Router' ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit;
	}
}
