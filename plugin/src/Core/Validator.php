<?php
/**
 * Validator - validates link data.
 *
 * @package EdgeLinkRouter
 */

namespace CFELR\Core;

/**
 * Validates link input data.
 */
class Validator {

	/**
	 * Reserved slugs that cannot be used.
	 *
	 * @var array
	 */
	private const RESERVED_SLUGS = array(
		'wp-admin',
		'wp-login',
		'wp-json',
		'feed',
		'sitemap',
		'xmlrpc',
		'wp-content',
		'wp-includes',
	);

	/**
	 * Allowed redirect status codes.
	 *
	 * @var array
	 */
	private const ALLOWED_STATUS_CODES = array( 301, 302, 307, 308 );

	/**
	 * Maximum slug length.
	 *
	 * @var int
	 */
	private const MAX_SLUG_LENGTH = 200;

	/**
	 * Maximum target URL length.
	 *
	 * @var int
	 */
	private const MAX_URL_LENGTH = 2048;

	/**
	 * Validate link data.
	 *
	 * @param array  $data       Link data to validate.
	 * @param string $host       Current site host (for loop detection).
	 * @param string $prefix     Redirect prefix (e.g., 'go').
	 * @param int    $exclude_id Link ID to exclude from uniqueness check.
	 * @return array Array of errors. Empty if valid.
	 */
	public function validate( array $data, string $host = '', string $prefix = 'go', int $exclude_id = 0 ): array {
		$errors = array();

		// Validate slug.
		$slug_errors = $this->validate_slug( $data['slug'] ?? '', $exclude_id );
		$errors      = array_merge( $errors, $slug_errors );

		// Validate target URL.
		$url_errors = $this->validate_target_url( $data['target_url'] ?? '', $host, $prefix, $data['slug'] ?? '' );
		$errors     = array_merge( $errors, $url_errors );

		// Validate status code.
		if ( isset( $data['status_code'] ) ) {
			$code_errors = $this->validate_status_code( $data['status_code'] );
			$errors      = array_merge( $errors, $code_errors );
		}

		// Validate options.
		if ( isset( $data['options'] ) ) {
			$option_errors = $this->validate_options( $data['options'] );
			$errors        = array_merge( $errors, $option_errors );
		}

		return $errors;
	}

	/**
	 * Validate and sanitize a slug.
	 *
	 * @param string $slug       Raw slug.
	 * @param int    $exclude_id Link ID to exclude from uniqueness check.
	 * @return array Errors.
	 */
	public function validate_slug( string $slug, int $exclude_id = 0 ): array {
		$errors = array();

		// Empty check.
		if ( empty( $slug ) ) {
			$errors[] = __( 'Slug is required.', 'edge-link-router' );
			return $errors;
		}

		// Length check.
		if ( strlen( $slug ) > self::MAX_SLUG_LENGTH ) {
			$errors[] = sprintf(
				/* translators: %d: maximum slug length */
				__( 'Slug must be %d characters or less.', 'edge-link-router' ),
				self::MAX_SLUG_LENGTH
			);
		}

		// Character check (after sanitization).
		$sanitized = $this->sanitize_slug( $slug );
		if ( ! preg_match( '/^[a-z0-9\-_]+$/', $sanitized ) ) {
			$errors[] = __( 'Slug can only contain lowercase letters, numbers, hyphens, and underscores.', 'edge-link-router' );
		}

		// Reserved slug check.
		if ( in_array( $sanitized, self::RESERVED_SLUGS, true ) ) {
			$errors[] = __( 'This slug is reserved and cannot be used.', 'edge-link-router' );
		}

		return $errors;
	}

	/**
	 * Sanitize a slug.
	 *
	 * @param string $slug Raw slug.
	 * @return string Sanitized slug.
	 */
	public function sanitize_slug( string $slug ): string {
		// Trim.
		$slug = trim( $slug );

		// Lowercase.
		$slug = strtolower( $slug );

		// Replace spaces with hyphens.
		$slug = str_replace( ' ', '-', $slug );

		// Use WP sanitize_title for additional cleaning.
		$slug = sanitize_title( $slug );

		// Ensure only allowed characters remain.
		$slug = preg_replace( '/[^a-z0-9\-_]/', '', $slug );

		return $slug;
	}

	/**
	 * Validate target URL.
	 *
	 * @param string $url    Target URL.
	 * @param string $host   Current site host.
	 * @param string $prefix Redirect prefix.
	 * @param string $slug   Current slug (for loop detection).
	 * @return array Errors.
	 */
	public function validate_target_url( string $url, string $host = '', string $prefix = 'go', string $slug = '' ): array {
		$errors = array();

		// Empty check.
		if ( empty( $url ) ) {
			$errors[] = __( 'Target URL is required.', 'edge-link-router' );
			return $errors;
		}

		// Length check.
		if ( strlen( $url ) > self::MAX_URL_LENGTH ) {
			$errors[] = sprintf(
				/* translators: %d: maximum URL length */
				__( 'Target URL must be %d characters or less.', 'edge-link-router' ),
				self::MAX_URL_LENGTH
			);
		}

		// Scheme check.
		$scheme = wp_parse_url( $url, PHP_URL_SCHEME );
		if ( ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
			$errors[] = __( 'Target URL must use http or https scheme.', 'edge-link-router' );
		}

		// Valid URL check.
		$sanitized_url = esc_url_raw( $url );
		if ( empty( $sanitized_url ) ) {
			$errors[] = __( 'Target URL is not valid.', 'edge-link-router' );
		}

		// Loop detection.
		if ( ! empty( $host ) && ! empty( $slug ) ) {
			$target_host = wp_parse_url( $url, PHP_URL_HOST );
			$target_path = wp_parse_url( $url, PHP_URL_PATH );

			if ( $target_host === $host && $target_path === "/{$prefix}/{$slug}" ) {
				$errors[] = __( 'Target URL cannot point to the same redirect (infinite loop).', 'edge-link-router' );
			}
		}

		return $errors;
	}

	/**
	 * Validate status code.
	 *
	 * @param mixed $code Status code.
	 * @return array Errors.
	 */
	public function validate_status_code( mixed $code ): array {
		$errors = array();

		$code = (int) $code;
		if ( ! in_array( $code, self::ALLOWED_STATUS_CODES, true ) ) {
			$errors[] = sprintf(
				/* translators: %s: list of allowed status codes */
				__( 'Status code must be one of: %s.', 'edge-link-router' ),
				implode( ', ', self::ALLOWED_STATUS_CODES )
			);
		}

		return $errors;
	}

	/**
	 * Validate options.
	 *
	 * @param mixed $options Options array.
	 * @return array Errors.
	 */
	public function validate_options( mixed $options ): array {
		$errors = array();

		if ( ! is_array( $options ) ) {
			$errors[] = __( 'Options must be an array.', 'edge-link-router' );
			return $errors;
		}

		// Validate append_utm if present.
		if ( isset( $options['append_utm'] ) && ! is_array( $options['append_utm'] ) ) {
			$errors[] = __( 'UTM parameters must be an array.', 'edge-link-router' );
		}

		return $errors;
	}

	/**
	 * Get allowed status codes.
	 *
	 * @return array
	 */
	public function get_allowed_status_codes(): array {
		return self::ALLOWED_STATUS_CODES;
	}
}
