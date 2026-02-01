<?php
/**
 * Fallback Handler.
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
 * Fallback handler for when rewrite rules don't work.
 * Parses REQUEST_URI directly to catch redirect requests.
 */
class FallbackHandler {

	/**
	 * Default prefix (deprecated, use SettingsPage::get_prefix()).
	 *
	 * @var string
	 */
	private const DEFAULT_PREFIX = 'go';

	/**
	 * Initialize the handler.
	 *
	 * @return void
	 */
	public function init(): void {
		// Hook early in parse_request to catch requests before WordPress processes them.
		add_action( 'parse_request', array( $this, 'maybe_intercept' ), 1 );
	}

	/**
	 * Check if we should intercept this request.
	 *
	 * @param \WP $wp WordPress environment instance.
	 * @return void
	 */
	public function maybe_intercept( \WP $wp ): void {
		// If rewrite already matched, don't interfere.
		if ( ! empty( $wp->query_vars[ RewriteHandler::QUERY_VAR ] ) ) {
			return;
		}

		// Get request URI.
		$request_uri = isset( $_SERVER['REQUEST_URI'] ) ? sanitize_text_field( wp_unslash( $_SERVER['REQUEST_URI'] ) ) : '';

		if ( empty( $request_uri ) ) {
			return;
		}

		// Parse and check if it matches our pattern.
		$slug = $this->parse_request_uri( $request_uri );

		if ( $slug === null ) {
			return;
		}

		// Set the query var so template_redirect can handle it.
		$wp->query_vars[ RewriteHandler::QUERY_VAR ] = $slug;
	}

	/**
	 * Parse the request URI to extract slug.
	 *
	 * @param string $request_uri Raw REQUEST_URI.
	 * @return string|null Slug if matched, null otherwise.
	 */
	private function parse_request_uri( string $request_uri ): ?string {
		// Remove query string.
		$path = wp_parse_url( $request_uri, PHP_URL_PATH );

		if ( ! $path ) {
			return null;
		}

		// Get home path for subdirectory installs.
		$home_path = $this->get_home_path();

		// Remove home path prefix.
		if ( ! empty( $home_path ) && str_starts_with( $path, $home_path ) ) {
			$path = substr( $path, strlen( $home_path ) );
		}

		// Ensure path starts with /.
		$path = '/' . ltrim( $path, '/' );

		// Get prefix.
		$prefix = $this->get_prefix();

		// Build pattern.
		$pattern = '#^/' . preg_quote( $prefix, '#' ) . '/([^/]+)/?$#';

		if ( preg_match( $pattern, $path, $matches ) ) {
			return $matches[1];
		}

		return null;
	}

	/**
	 * Get the home path for subdirectory installs.
	 *
	 * @return string Path without trailing slash, empty for root installs.
	 */
	private function get_home_path(): string {
		$home_path = wp_parse_url( home_url(), PHP_URL_PATH );

		if ( ! $home_path ) {
			return '';
		}

		// Normalize: remove trailing slash.
		return rtrim( $home_path, '/' );
	}

	/**
	 * Get the redirect prefix.
	 *
	 * @return string
	 */
	private function get_prefix(): string {
		return SettingsPage::get_prefix();
	}
}
