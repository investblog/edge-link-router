=== Edge Link Router ===
Contributors: yourname
Tags: redirect, links, shortlinks, cloudflare, edge
Requires at least: 6.0
Tested up to: 6.4
Requires PHP: 8.0
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Simple redirect management with optional Cloudflare edge acceleration.

== Description ==

Edge Link Router is a WordPress plugin for managing redirect links (`/go/your-slug`) with optional edge acceleration via Cloudflare Workers.

= Features =

* **Works immediately** - Redirects work right after activation, no configuration required
* **Simple management** - Create, edit, and manage redirects from WordPress admin
* **Optional edge mode** - Accelerate redirects with Cloudflare Workers (optional, not required)
* **Fail-safe design** - Edge mode falls back to WordPress if anything goes wrong
* **Privacy-focused** - Only aggregated statistics, no personal data collected
* **CSV import/export** - Bulk manage your redirects

= How It Works =

1. Install and activate the plugin
2. Create redirect rules in **Link Router > Links**
3. Your redirects work immediately at `/go/your-slug`
4. (Optional) Connect Cloudflare for edge acceleration

= Edge Mode (Optional) =

If you use Cloudflare, you can enable edge mode to handle redirects at Cloudflare's edge network, before requests reach your WordPress server. This is completely optional and the plugin works perfectly without it.

Edge mode features:
* Faster redirects (handled at edge)
* Reduced server load
* Automatic fallback to WordPress if edge fails

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/edge-link-router/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to **Link Router > Links** to create your first redirect

== Frequently Asked Questions ==

= Do I need Cloudflare to use this plugin? =

No. The plugin works perfectly with just WordPress. Cloudflare integration is optional for users who want edge acceleration.

= What happens if Cloudflare edge fails? =

The plugin is designed with a "fail-open" approach. If edge has any issues, requests automatically fall back to WordPress handling. Your redirects keep working.

= Does this plugin collect user data? =

No personal data is collected. Statistics are aggregated (total clicks per day) with no IP addresses, user agents, or cookies stored.

== Changelog ==

= 1.0.0 =
* Initial release
* WP-only redirect handling
* Links CRUD management
* CSV import/export
* Aggregated click statistics
* Cloudflare edge integration
* Diagnostic tools

== Upgrade Notice ==

= 1.0.0 =
Initial release.
