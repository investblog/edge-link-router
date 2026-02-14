# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.20] - 2026-02-14

### Added
- `== External services ==` section in readme.txt documenting Cloudflare API usage (WP.org review)

### Fixed
- Sanitize and validate CSV file upload (`$_FILES`) before passing to importer (WP.org review)
- Remove `JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE` flags from `wp_json_encode()` (WP.org review)
- ZIP filename in release workflow: `edge-link-router.zip` instead of versioned name (WP.org review)

### Changed
- Sample link target from `https://301.st` to `https://wordpress.org`

## [1.0.19] - 2026-02-10

### Fixed
- Uninstall: delete `cfelr_plugin_version` option (was left orphaned)

## [1.0.18] - 2026-02-10

### Fixed
- Edge mode detection: show "Health check pending" instead of "WP Only" when edge is enabled but cache is empty
- Smart cache invalidation on plugin update: schedule immediate health re-check instead of clearing cache

### Added
- Diagnostics: worker deployment check (verifies Worker script exists in Cloudflare)
- Diagnostics: route existence check (verifies route pattern points to correct worker)

## [1.0.17] - 2026-02-10

### Changed
- Move inline `<style>` blocks to enqueued admin.css (DashboardWidget, StatsPage, LogsPage, IntegrationsPage)
- Extract repeating inline styles to CSS classes with rem/em units
- Migrate all SQL queries to use `%i` identifier placeholder (WP 6.2+)
- Bump "Requires at least" from 6.0 to 6.2
- Replace banner assets with optimized JPG

### Fixed
- Plugin Check: zero errors and zero warnings
- Checkbox column styling now uses default WordPress table styles
- Clicks column alignment (left instead of right)

## [1.0.16] - 2026-02-05

### Fixed
- Clear health status cache on plugin update to prevent stale "Edge Degraded" status

## [1.0.15] - 2026-02-05

### Fixed
- Edge health check: use /settings API endpoint to detect worker (was returning script content instead of JSON)

## [1.0.14] - 2026-02-03

### Changed
- UI: move Links action buttons to toolbar row alongside search
- UI: hide Target URL column on screens narrower than 1200px
- UI: align inline form inputs and buttons to consistent height

### Fixed
- CI: build ZIP with correct directory structure for WordPress installer

## [1.0.13] - 2025-02-03

### Changed
- Updated "Tested up to" to WordPress 6.9

### Fixed
- Plugin Check: replaced fopen/fclose with manual CSV builder in Exporter
- Plugin Check: prefixed template variables in worker.js.php

## [1.0.11] - 2025-02-02

### Changed
- Readme: improved description, reduced tags to 5, added Privacy Policy section
- Admin: unified log badge styles (pill design)
- Admin: fixed table row hover on checkbox column

### Added
- WordPress.org assets: banners, icons (optimized with pngquant)

## [1.0.10] - 2025-02-02

### Added
- Worker: `X-CFELR-Snapshot-Version` header (schema version)
- Worker: `X-CFELR-Snapshot-Updated` header (publish timestamp)

## [1.0.9] - 2025-02-02

### Changed
- Worker: remove all trailing slashes (`/\/+$/` instead of `/\/$/`)
- Worker: trim target URL before validation

## [1.0.8] - 2025-02-02

### Changed
- Worker: use dynamic prefix from SNAPSHOT instead of hardcoded regex
- Worker: use startsWith() for faster path matching

### Security
- Worker: pre-decode length check (max 600 raw chars) to prevent DoS
- Worker: UTM key charset validation (`/^[a-zA-Z0-9_]+$/`)
- Worker: try-catch around `new URL(target)` with fail-open

## [1.0.7] - 2025-02-02

### Security
- Worker: slug length capped at 200 (matches WP Validator)
- Worker: UTM key max 50 chars, value max 200 chars
- Worker: removed 303 from status codes (not in WP UI)

### Documentation
- Added Edge Hardening Requirements in worker template header

## [1.0.6] - 2025-02-02

### Security
- Worker: validate target URL protocol (prevent open redirect)
- Worker: whitelist redirect status codes (301, 302, 303, 307, 308)

### Fixed
- Worker: normalize options object (handle undefined/null/array)
- Worker: try-catch around decodeURIComponent for malformed URLs

## [1.0.5] - 2025-02-02

### Fixed
- Correct API parameter for fail-open mode (`request_limit_fail_open` instead of `failure_mode`)

## [1.0.4] - 2025-02-02

### Fixed
- Constant redefinition warning in uninstall.php during plugin updates

## [1.0.3] - 2025-02-02

### Added
- Route pattern mismatch detection in health checks
- Warning on Integrations page when CF route doesn't match expected pattern
- "Fix Route Now" button to repair mismatched routes
- Auto-update CF route when prefix changes in Settings

### Fixed
- Routes now created with `failure_mode: fail_open` for true fail-open design

## [1.0.2] - 2025-02-02

### Changed
- Simplified Cloudflare API token setup instructions (use Custom Token with 4 permissions)

### Added
- Edge mode notice on Stats page with link to Cloudflare metrics

## [1.0.1] - 2025-02-02

### Added
- Git Updater support for easy updates from GitHub

### Fixed
- Cloudflare Worker upload now uses correct multipart format for ES modules
- ZIP archive structure fixed for WordPress plugin installer

## [1.0.0] - 2025-01-XX

### Added

#### Core Features
- Redirect management with customizable URL prefix (default: `/go/`)
- Support for HTTP status codes: 301, 302, 307, 308
- Enable/disable individual redirects
- Query string passthrough option
- UTM parameters auto-append
- Internal notes for each redirect

#### Admin Interface
- Links management page with WP List Table (sort, search, bulk actions)
- Add/Edit link forms with validation
- Statistics page with 7/30/90 day views
- Top links ranking with click share percentage
- Integrations page for Cloudflare setup
- Tools page (flush rewrite, test redirect, force republish)
- Settings page with configurable prefix
- Logs page for system events
- Dashboard widget with status overview

#### Cloudflare Edge Integration
- Optional edge acceleration via Cloudflare Workers
- Automatic Worker deployment and route management
- Inline snapshot publishing (no KV required)
- Fail-open design: WP fallback on any edge error
- Health monitoring with status indicators (WP Only / Edge Active / Edge Degraded)
- Public diagnostics (NS, headers, HTTPS, prefix conflicts)
- Authorized diagnostics (zone match, DNS proxy, permissions, route conflicts)
- Encrypted API token storage (libsodium/OpenSSL)
- Hourly reconcile job with automatic health checks

#### Data Management
- CSV import with validation and error reporting
- CSV export for backup and migration
- Aggregate click statistics (daily rollup)
- 90-day stats retention with automatic cleanup

#### Developer Features
- Configurable prefix via `CFELR_PREFIX` constant or `cfelr_prefix` filter
- Debug mode for admins (`?cfelr_debug=1`)
- REST API endpoints (admin-only)
- PSR-4 autoloading
- Modular architecture (Core / WP / Integrations layers)

#### Internationalization
- Full i18n support with `edge-link-router` text domain
- Translation template (.pot) with 236 strings

#### Security
- Capability checks (`manage_options`) on all admin pages
- Nonce verification on all form submissions
- Input sanitization and validation
- Encrypted secrets storage
- No public REST endpoints

### Technical Details
- Minimum PHP: 8.0
- Minimum WordPress: 6.0
- Database tables: `cfelr_links`, `cfelr_clicks_daily`, `cfelr_integrations`
- Rewrite handler with fallback for subdirectory installs

[Unreleased]: https://github.com/investblog/edge-link-router/compare/v1.0.19...HEAD
[1.0.19]: https://github.com/investblog/edge-link-router/compare/v1.0.18...v1.0.19
[1.0.18]: https://github.com/investblog/edge-link-router/compare/v1.0.17...v1.0.18
[1.0.17]: https://github.com/investblog/edge-link-router/compare/v1.0.16...v1.0.17
[1.0.16]: https://github.com/investblog/edge-link-router/compare/v1.0.15...v1.0.16
[1.0.15]: https://github.com/investblog/edge-link-router/compare/v1.0.14...v1.0.15
[1.0.14]: https://github.com/investblog/edge-link-router/compare/v1.0.13...v1.0.14
[1.0.13]: https://github.com/investblog/edge-link-router/compare/v1.0.11...v1.0.13
[1.0.11]: https://github.com/investblog/edge-link-router/compare/v1.0.10...v1.0.11
[1.0.10]: https://github.com/investblog/edge-link-router/compare/v1.0.9...v1.0.10
[1.0.9]: https://github.com/investblog/edge-link-router/compare/v1.0.8...v1.0.9
[1.0.8]: https://github.com/investblog/edge-link-router/compare/v1.0.7...v1.0.8
[1.0.7]: https://github.com/investblog/edge-link-router/compare/v1.0.6...v1.0.7
[1.0.6]: https://github.com/investblog/edge-link-router/compare/v1.0.5...v1.0.6
[1.0.5]: https://github.com/investblog/edge-link-router/compare/v1.0.4...v1.0.5
[1.0.4]: https://github.com/investblog/edge-link-router/compare/v1.0.3...v1.0.4
[1.0.3]: https://github.com/investblog/edge-link-router/compare/v1.0.2...v1.0.3
[1.0.2]: https://github.com/investblog/edge-link-router/compare/v1.0.1...v1.0.2
[1.0.1]: https://github.com/investblog/edge-link-router/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/investblog/edge-link-router/releases/tag/v1.0.0
