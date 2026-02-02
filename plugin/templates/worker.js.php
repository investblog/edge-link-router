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
 *
 * Edge Hardening Requirements:
 * - Target URL must be absolute http(s):// (no relative URLs)
 * - Redirect codes allowed: 301, 302, 307, 308 (matches WP whitelist)
 * - Options must be object; otherwise treated as {}
 * - Malformed percent-encoding → fail-open to WP
 * - UTM keys: alphanumeric + underscore only; max 50 chars
 * - UTM values: strings only; max 200 chars
 * - Slug length capped at 200 (checked before decode)
 * - Prefix read from SNAPSHOT (not hardcoded in regex)
 */

// Ensure we're in the right context.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Default values if not provided.
$links  = $links ?? array();
$prefix = $prefix ?? 'go';

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

// Hardening constants (must match WP Validator)
const VALID_CODES = [301, 302, 307, 308];
const MAX_SLUG_LENGTH = 200;
const MAX_UTM_KEY_LENGTH = 50;
const MAX_UTM_VALUE_LENGTH = 200;
const UTM_KEY_PATTERN = /^[a-zA-Z0-9_]+$/;

export default {
	async fetch(request) {
		try {
			const url = new URL(request.url);
			const prefix = `/${SNAPSHOT.prefix}/`;

			// Quick path check (before any processing)
			if (!url.pathname.startsWith(prefix)) {
				return fetch(request);
			}

			// Extract slug part (after prefix, remove all trailing slashes)
			const slugRaw = url.pathname.slice(prefix.length).replace(/\/+$/, '');

			// Early length check BEFORE decode (prevent DoS via huge encoded string)
			if (!slugRaw || slugRaw.length > MAX_SLUG_LENGTH * 3) {
				return fetch(request);
			}

			// Decode and normalize slug
			let slug;
			try {
				slug = decodeURIComponent(slugRaw).trim().toLowerCase();
			} catch {
				// Malformed percent-encoding, fail-open to WP
				return fetch(request);
			}

			// Post-decode length check
			if (slug.length > MAX_SLUG_LENGTH) {
				return fetch(request);
			}

			const rule = SNAPSHOT.links[slug];

			if (!rule) {
				// Rule not found, fall back to WP
				return fetch(request);
			}

			let target = (rule.target_url || '').trim();

			// Validate target URL protocol (security: prevent open redirect)
			if (!target || !/^https?:\/\//i.test(target)) {
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

			// Append UTM parameters (with validation)
			if (options.append_utm && typeof options.append_utm === 'object' && !Array.isArray(options.append_utm)) {
				let targetUrl;
				try {
					targetUrl = new URL(target);
				} catch {
					// Malformed target URL, fail-open to WP
					return fetch(request);
				}

				for (const [key, value] of Object.entries(options.append_utm)) {
					// Validate key: charset + length
					if (
						UTM_KEY_PATTERN.test(key) &&
						key.length <= MAX_UTM_KEY_LENGTH &&
						typeof value === 'string' &&
						value.length <= MAX_UTM_VALUE_LENGTH
					) {
						targetUrl.searchParams.set(key, value);
					}
					// Invalid entries are silently dropped
				}
				target = targetUrl.toString();
			}

			// Validate status code (whitelist only, matches WP)
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
