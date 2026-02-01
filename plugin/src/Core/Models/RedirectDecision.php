<?php
// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Redirect Decision Model.
 *
 * @package EdgeLinkRouter
 */

namespace CFELR\Core\Models;

/**
 * Represents the result of resolving a slug to a redirect.
 */
class RedirectDecision {

	/**
	 * Whether a redirect should occur.
	 *
	 * @var bool
	 */
	public bool $should_redirect = false;

	/**
	 * The target URL to redirect to.
	 *
	 * @var string
	 */
	public string $target_url = '';

	/**
	 * HTTP status code for the redirect.
	 *
	 * @var int
	 */
	public int $status_code = 302;

	/**
	 * The link ID (for stats tracking).
	 *
	 * @var int|null
	 */
	public ?int $link_id = null;

	/**
	 * How the match was made (rewrite|fallback).
	 *
	 * @var string
	 */
	public string $matched_by = '';

	/**
	 * Original link options.
	 *
	 * @var array
	 */
	public array $options = array();

	/**
	 * Create a "no redirect" decision.
	 *
	 * @return self
	 */
	public static function not_found(): self {
		return new self();
	}

	/**
	 * Create a redirect decision from a Link.
	 *
	 * @param Link   $link       The matched link.
	 * @param string $matched_by How the match occurred.
	 * @param string $query      Original query string.
	 * @return self
	 */
	public static function from_link( Link $link, string $matched_by, string $query = '' ): self {
		$decision                  = new self();
		$decision->should_redirect = true;
		$decision->link_id         = $link->id;
		$decision->status_code     = $link->status_code;
		$decision->matched_by      = $matched_by;
		$decision->options         = $link->options;

		// Build target URL.
		$target = $link->target_url;

		// Passthrough query string.
		if ( ! empty( $link->options['passthrough_query'] ) && ! empty( $query ) ) {
			$separator = str_contains( $target, '?' ) ? '&' : '?';
			$target   .= $separator . ltrim( $query, '?' );
		}

		// Append UTM parameters.
		if ( ! empty( $link->options['append_utm'] ) && is_array( $link->options['append_utm'] ) ) {
			$url_parts = wp_parse_url( $target );
			$query_str = $url_parts['query'] ?? '';

			parse_str( $query_str, $existing_params );
			$merged = array_merge( $existing_params, $link->options['append_utm'] );

			$base   = ( $url_parts['scheme'] ?? 'https' ) . '://';
			$base  .= $url_parts['host'] ?? '';
			$base  .= $url_parts['path'] ?? '/';
			$target = $base . '?' . http_build_query( $merged );

			if ( ! empty( $url_parts['fragment'] ) ) {
				$target .= '#' . $url_parts['fragment'];
			}
		}

		$decision->target_url = $target;

		return $decision;
	}

	/**
	 * Convert to debug array.
	 *
	 * @param string $slug Original slug.
	 * @return array
	 */
	public function to_debug_array( string $slug ): array {
		return array(
			'handler'     => 'wp',
			'slug'        => $slug,
			'target_url'  => $this->target_url,
			'status_code' => $this->status_code,
			'options'     => $this->options,
			'matched_by'  => $this->matched_by,
			'timestamp'   => gmdate( 'c' ),
		);
	}
}
