<?php
/**
 * URL Normalization Handler.
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
 * Normalizes URLs by lowercasing paths, removing duplicate slashes,
 * and fixing trailing slashes — then 301-redirects to the canonical form.
 */
class NormalizeUrlHandler {

	/**
	 * Initialize the handler.
	 *
	 * @return void
	 */
	public function init(): void {
		// Hook before StripTrackingHandler (priority 0) so normalization happens first.
		add_action( 'template_redirect', array( $this, 'handle' ), -1 );
	}

	/**
	 * Normalize the current URL and redirect if changed.
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

		$settings = SettingsPage::get_url_normalization_settings();

		if ( ! $settings['enabled'] ) {
			return;
		}

		// Skip the homepage.
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_url( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '/';
		$path        = wp_parse_url( $request_uri, PHP_URL_PATH );

		if ( empty( $path ) || '/' === $path ) {
			return;
		}

		// Skip wp-admin paths.
		if ( str_starts_with( $path, '/wp-admin' ) || str_starts_with( $path, '/wp-login' ) ) {
			return;
		}

		// Skip files with extensions (e.g. .css, .js, .jpg).
		$basename = basename( $path );
		if ( str_contains( $basename, '.' ) && preg_match( '/\.[a-zA-Z0-9]{1,10}$/', $basename ) ) {
			return;
		}

		$normalized = $path;

		// 1. Lowercase the path.
		$normalized = strtolower( $normalized );

		// 2. Remove duplicate slashes.
		$normalized = preg_replace( '#/{2,}#', '/', $normalized );

		// 3. Fix trailing slash to match WP permalink structure.
		$normalized = user_trailingslashit( untrailingslashit( $normalized ) );

		// Nothing changed — bail.
		if ( $normalized === $path ) {
			return;
		}

		// Rebuild full URL with query string.
		$query_string = isset( $_SERVER['QUERY_STRING'] ) ? sanitize_text_field( wp_unslash( $_SERVER['QUERY_STRING'] ) ) : '';
		$canonical    = home_url( $normalized );

		if ( ! empty( $query_string ) ) {
			$canonical .= '?' . $query_string;
		}

		wp_redirect( $canonical, 301, 'Edge Link Router' ); // phpcs:ignore WordPress.Security.SafeRedirect.wp_redirect_wp_redirect
		exit;
	}
}
