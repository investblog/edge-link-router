=== Edge Link Router ===
Contributors: 301st
Donate link: https://301.st
Tags: redirect, shortlinks, cloudflare, 301, utm
Requires at least: 6.0
Tested up to: 6.9
Requires PHP: 8.0
Stable tag: 1.0.14
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Simple redirect management with optional Cloudflare edge acceleration. Create short links, track clicks, add UTM parameters.

== Description ==

**Simple redirect management that works immediately.** Create `/go/your-slug` redirects in WordPress admin — no configuration required. Optionally accelerate with Cloudflare Workers.

Built by [301.st](https://301.st) — your redirect management experts.

= What It Does =

* **Instant redirects** — Works right after activation. No setup, no external services needed.
* **Full control** — 301/302/307/308 codes, UTM auto-append, query passthrough.
* **CSV import/export** — Migrate affiliate links between sites in seconds.

= How It Works =

1. **WordPress handles everything by default** — Redirects work via WP rewrite rules
2. **Enable edge mode (optional)** — Connect Cloudflare for sub-millisecond redirects
3. **Fail-open design** — If edge fails, WordPress takes over. Redirects never break.

= Privacy First =

* Aggregated click stats only (daily totals)
* No IP addresses, no cookies, no User-Agent
* GDPR compliant — no consent required

= Testing Your Redirects =

Use our free [Redirect Inspector](https://chromewebstore.google.com/detail/redirect-inspector/jkeijlkbgkdnhmejgofbbapdbhjljdgg) Chrome extension to verify your redirects are working correctly and see the full redirect chain.

= Links =

* [Project Home](https://301.st)
* [GitHub](https://github.com/investblog/edge-link-router)
* [Redirect Inspector](https://chromewebstore.google.com/detail/redirect-inspector/jkeijlkbgkdnhmejgofbbapdbhjljdgg) — free Chrome extension

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

= 1.0.14 =
* UI: hide Target URL column on narrow screens (< 1200px)
* UI: move action buttons to toolbar row with search
* UI: align inline form inputs with buttons
* Fix: CI build ZIP structure for WordPress installer

= 1.0.13 =
* Updated "Tested up to" to WordPress 6.9
* Fixed Plugin Check errors (fopen/fclose, variable prefixes)

= 1.0.11 =
* Improved readme for WordPress.org submission
* Unified admin badge styles
* Added plugin assets (banners, icons)

= 1.0.10 =
* Worker: add snapshot version headers for debugging (X-CFELR-Snapshot-Version, X-CFELR-Snapshot-Updated)

= 1.0.9 =
* Worker: remove all trailing slashes, trim target URL

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

= 1.0.14 =
UI polish: responsive table, toolbar layout, form alignment.

= 1.0.13 =
WordPress 6.9 compatible, Plugin Check fixes.

= 1.0.11 =
Ready for WordPress.org: improved readme, plugin assets.

= 1.0.10 =
Added snapshot version headers for debugging.

= 1.0.9 =
Minor polish: slash normalization, target trim.

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

== Privacy Policy ==

This plugin optionally connects to Cloudflare API (api.cloudflare.com) when edge mode is enabled. Your Cloudflare API token is stored encrypted (libsodium/AES-256). No user data is transmitted to external services. Click statistics are aggregated daily totals only — no IP addresses, cookies, or personal data collected.
