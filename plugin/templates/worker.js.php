<?php
/**
 * Cloudflare Worker Template.
 *
 * This file generates the Worker JavaScript with inline snapshot.
 *
 * @package EdgeLinkRouter
 *
 * Variables available:
 * @var array  $links  Associative array of slug => link data.
 * @var string $prefix URL prefix (e.g., 'go').
 */

// Ensure we're in the right context.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Default values if not provided.
$links  = $links ?? array();
$prefix = $prefix ?? 'go';

// Escape prefix for regex.
$escaped_prefix = preg_quote( $prefix, '/' );

// Generate snapshot JSON.
$snapshot = array(
	'version'    => 1,
	'updated_at' => gmdate( 'c' ),
	'prefix'     => $prefix,
	'links'      => $links,
);

$snapshot_json = wp_json_encode( $snapshot, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE );
?>
/**
 * Edge Link Router - Cloudflare Worker
 *
 * Generated: <?php echo esc_js( gmdate( 'c' ) ); ?>

 * Links count: <?php echo count( $links ); ?>

 *
 * This Worker handles redirect requests at the edge.
 * If a rule is not found or an error occurs, it falls back to the origin (WP).
 */

const SNAPSHOT = <?php echo $snapshot_json; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>;

// Valid redirect status codes whitelist
const VALID_CODES = [301, 302, 303, 307, 308];

export default {
	async fetch(request) {
		try {
			const url = new URL(request.url);

			// Match redirect pattern: /<prefix>/<slug>
			const match = url.pathname.match(/^\/(<?php echo esc_js( $escaped_prefix ); ?>)\/([^\/]+)\/?$/);

			if (!match) {
				// Not our route, pass through to origin
				return fetch(request);
			}

			// Decode and normalize slug (with error handling)
			let slug;
			try {
				slug = decodeURIComponent(match[2]).trim().toLowerCase();
			} catch {
				// Malformed URL encoding, fall back to WP
				return fetch(request);
			}

			const rule = SNAPSHOT.links[slug];

			if (!rule) {
				// Rule not found, fall back to WP
				return fetch(request);
			}

			let target = rule.target_url;

			// Validate target URL protocol (security: prevent open redirect)
			if (!/^https?:\/\//i.test(target)) {
				return fetch(request);
			}

			// Normalize options (handle undefined, null, or array)
			const options = (rule.options && typeof rule.options === 'object' && !Array.isArray(rule.options))
				? rule.options
				: {};

			// Passthrough query string
			if (options.passthrough_query && url.search) {
				const sep = target.includes('?') ? '&' : '?';
				target += sep + url.search.substring(1);
			}

			// Append UTM parameters
			if (options.append_utm && typeof options.append_utm === 'object' && !Array.isArray(options.append_utm)) {
				const targetUrl = new URL(target);
				for (const [key, value] of Object.entries(options.append_utm)) {
					if (typeof value === 'string') {
						targetUrl.searchParams.set(key, value);
					}
				}
				target = targetUrl.toString();
			}

			// Validate status code (whitelist only)
			const statusCode = VALID_CODES.includes(Number(rule.status_code)) ? Number(rule.status_code) : 302;

			// Return redirect response
			return new Response(null, {
				status: statusCode,
				headers: {
					'Location': target,
					'X-Handled-By': 'cfelr-edge',
					'Cache-Control': 'no-store'
				}
			});

		} catch (e) {
			// Fail-open: any error falls back to origin
			// This ensures WP handles requests if Worker has issues
			return fetch(request);
		}
	}
};
