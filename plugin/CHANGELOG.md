# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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

[Unreleased]: https://github.com/investblog/edge-link-router/compare/v1.0.4...HEAD
[1.0.4]: https://github.com/investblog/edge-link-router/compare/v1.0.3...v1.0.4
[1.0.3]: https://github.com/investblog/edge-link-router/compare/v1.0.2...v1.0.3
[1.0.2]: https://github.com/investblog/edge-link-router/compare/v1.0.1...v1.0.2
[1.0.1]: https://github.com/investblog/edge-link-router/compare/v1.0.0...v1.0.1
[1.0.0]: https://github.com/investblog/edge-link-router/releases/tag/v1.0.0
