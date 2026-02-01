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

			// Decode and normalize slug
			const slug = decodeURIComponent(match[2]).trim().toLowerCase();
			const rule = SNAPSHOT.links[slug];

			if (!rule) {
				// Rule not found, fall back to WP
				return fetch(request);
			}

			let target = rule.target_url;

			// Passthrough query string
			if (rule.options?.passthrough_query && url.search) {
				const sep = target.includes('?') ? '&' : '?';
				target += sep + url.search.substring(1);
			}

			// Append UTM parameters
			if (rule.options?.append_utm && Object.keys(rule.options.append_utm).length > 0) {
				const targetUrl = new URL(target);
				for (const [key, value] of Object.entries(rule.options.append_utm)) {
					targetUrl.searchParams.set(key, value);
				}
				target = targetUrl.toString();
			}

			// Return redirect response
			return new Response(null, {
				status: rule.status_code || 302,
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
