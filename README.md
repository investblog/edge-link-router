# Edge Link Router

Simple redirect management with optional Cloudflare edge acceleration. Create `/go/your-slug` redirects — works immediately, no setup required.

[![WordPress](https://img.shields.io/badge/WordPress-6.0%2B-blue.svg)](https://wordpress.org/)
[![PHP](https://img.shields.io/badge/PHP-8.0%2B-purple.svg)](https://php.net/)
[![License](https://img.shields.io/badge/License-GPL%20v2-green.svg)](https://www.gnu.org/licenses/gpl-2.0.html)

## Features

- **Instant redirects** — Works right after activation, no configuration needed
- **Full control** — 301/302/307/308 status codes, UTM auto-append, query passthrough
- **Click statistics** — Aggregated daily stats (privacy-first, no PII)
- **CSV import/export** — Migrate links between sites in seconds
- **Dashboard widget** — Quick stats at a glance
- **Edge acceleration** — Optional Cloudflare Workers for sub-millisecond redirects
- **Fail-open design** — If edge fails, WordPress takes over seamlessly

## Quick Start

```bash
# 1. Install from Releases
Download ZIP → Plugins → Add New → Upload

# 2. Create a redirect
Link Router → Add New
Slug: promo
Target: https://example.com/landing

# 3. Done
https://yoursite.com/go/promo → redirects instantly
```

## Requirements

- WordPress 6.0+
- PHP 8.0+
- Cloudflare account (optional, for edge mode)

## Edge Mode

Enable Cloudflare Workers for edge-level redirects:

1. Go to **Link Router → Integrations**
2. Enter your Cloudflare API token
3. Click **Enable Edge Mode**

Redirects now happen at Cloudflare's edge — before requests reach WordPress.

## Testing

Use [Redirect Inspector](https://chromewebstore.google.com/detail/redirect-inspector/jkeijlkbgkdnhmejgofbbapdbhjljdgg) Chrome extension to verify redirects and see the full chain.

## Development

```bash
git clone https://github.com/investblog/edge-link-router.git
cd edge-link-router
composer install
npm install
npm run env:start   # Local WP environment
npm run lint:php    # PHPCS
```

## License

GPL-2.0+

---

Built by [301.st](https://301.st) with [Claude](https://claude.ai)
