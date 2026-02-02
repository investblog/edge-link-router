=== Edge Link Router ===
Contributors: 301st
Donate link: https://301.st
Tags: redirect, links, shortlinks, cloudflare, edge, 301, 302, utm
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 1.0.8
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Simple redirect management with optional Cloudflare edge acceleration. Create short links, track clicks, add UTM parameters.

== Description ==

Edge Link Router is a WordPress plugin for managing redirect links (`/go/your-slug`) with optional edge acceleration via Cloudflare Workers.

Built by [301.st](https://301.st) — your redirect management experts.

= Features =

* **Works immediately** — Redirects work right after activation, no configuration required
* **Simple management** — Create, edit, and manage redirects from WordPress admin
* **UTM tracking** — Automatically append UTM parameters to your target URLs
* **Click statistics** — Track clicks with 30-day retention (privacy-focused, no PII)
* **CSV import/export** — Bulk manage your redirects
* **REST API** — Full programmatic access for developers
* **Dashboard widget** — Quick stats at a glance
* **Optional edge mode** — Accelerate redirects with Cloudflare Workers

= How It Works =

1. Install and activate the plugin
2. Create redirect rules in **Link Router > Links**
3. Your redirects work immediately at `/go/your-slug`
4. (Optional) Connect Cloudflare for edge acceleration

= Testing Your Redirects =

Use our free [Redirect Inspector](https://chromewebstore.google.com/detail/redirect-inspector/jkeijlkbgkdnhmejgofbbapdbhjljdgg) Chrome extension to verify your redirects are working correctly and see the full redirect chain.

= Edge Mode (Optional) =

If you use Cloudflare, you can enable edge mode to handle redirects at Cloudflare's edge network, before requests reach your WordPress server. This is completely optional and the plugin works perfectly without it.

Edge mode features:

* Faster redirects (handled at edge)
* Reduced server load
* Automatic fallback to WordPress if edge fails

= Links =

* [Project Home](https://301.st)
* [Documentation](https://301.st/docs)
* [Redirect Inspector Extension](https://chromewebstore.google.com/detail/redirect-inspector/jkeijlkbgkdnhmejgofbbapdbhjljdgg)
* [GitHub Repository](https://github.com/investblog/edge-link-router)

== Installation ==

1. Upload the plugin files to `/wp-content/plugins/edge-link-router/`
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Go to **Link Router > Links** to create your first redirect

== Frequently Asked Questions ==

= Do I need Cloudflare to use this plugin? =

No. The plugin works perfectly with just WordPress. Cloudflare integration is optional for users who want edge acceleration.

= What redirect types are supported? =

The plugin supports 301 (permanent), 302 (temporary), 307 (temporary, preserve method), and 308 (permanent, preserve method) redirects.

= Can I add UTM parameters automatically? =

Yes! Each redirect can have its own UTM parameters (source, medium, campaign) that are automatically appended to the target URL.

= What happens if Cloudflare edge fails? =

The plugin is designed with a "fail-open" approach. If edge has any issues, requests automatically fall back to WordPress handling. Your redirects keep working.

= Does this plugin collect user data? =

No personal data is collected. Statistics are aggregated (total clicks per day) with no IP addresses, user agents, or cookies stored.

= How can I verify my redirects work correctly? =

Use our free [Redirect Inspector](https://chromewebstore.google.com/detail/redirect-inspector/jkeijlkbgkdnhmejgofbbapdbhjljdgg) Chrome extension to see the full redirect chain and HTTP status codes.

== Screenshots ==

1. Links management screen
2. Edit link with UTM parameters
3. Click statistics dashboard
4. Settings page
5. Cloudflare integration

== Changelog ==

= 1.0.8 =
* Worker: dynamic prefix from SNAPSHOT (not hardcoded in regex)
* Worker: pre-decode length check to prevent DoS
* Worker: UTM key charset validation (alphanumeric + underscore)
* Worker: try-catch around new URL() for malformed targets

= 1.0.7 =
* Complete edge hardening: slug length limit, UTM validation, synced status codes with WP

= 1.0.6 =
* Worker hardening: protocol validation, options normalization, status code whitelist
* Better error handling for malformed URL slugs

= 1.0.5 =
* Fixed fail-open mode API parameter (request_limit_fail_open)

= 1.0.4 =
* Fixed constant redefinition warning during updates

= 1.0.3 =
* Route pattern mismatch detection and warning
* "Fix Route Now" button to repair mismatched routes
* Auto-update CF route when prefix changes
* Routes now use fail_open mode (true fail-open design)

= 1.0.2 =
* Simplified Cloudflare API token setup (Custom Token with 4 permissions)
* Added edge mode notice on Stats page with link to Cloudflare metrics
* Fixed Cloudflare Worker upload multipart format
* Added Git Updater support

= 1.0.0 =
* Initial release
* WP-only redirect handling
* Links CRUD management
* CSV import/export
* UTM parameter support
* Aggregated click statistics
* Dashboard widget
* Cloudflare edge integration
* Diagnostic tools
* REST API

== Upgrade Notice ==

= 1.0.8 =
Worker improvements: dynamic prefix, better validation.

= 1.0.7 =
Complete edge hardening with documented requirements.

= 1.0.6 =
Worker security hardening.

= 1.0.5 =
Fixed fail-open mode for Cloudflare routes.

= 1.0.4 =
Fixed constant redefinition warning during updates.

= 1.0.3 =
Route mismatch detection and fail-open mode for better reliability.

= 1.0.2 =
Improved Cloudflare setup instructions and edge statistics notice.

= 1.0.0 =
Initial release. Welcome to Edge Link Router!
