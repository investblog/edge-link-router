# Edge Link Router

Simple redirect management WordPress plugin with optional Cloudflare edge acceleration.

## Features

- **WP-only mode** — Works immediately without external services
- **Custom redirects** — Create short links with 301/302/307/308 status codes
- **UTM tracking** — Automatically append UTM parameters to target URLs
- **Click statistics** — Track clicks with 30-day retention
- **CSV import/export** — Bulk manage your redirects
- **Dashboard widget** — Quick stats at a glance
- **REST API** — Programmatic access to all features
- **Cloudflare Workers** — Optional edge-level redirects for maximum performance

## Requirements

- WordPress 6.0+
- PHP 8.0+

## Installation

1. Download the latest release ZIP from [Releases](https://github.com/investblog/edge-link-router/releases)
2. Go to **Plugins → Add New → Upload Plugin**
3. Upload the ZIP file and activate

## Usage

After activation, find **Link Router** in your admin menu.

### Creating a redirect

1. Go to **Link Router → Add New**
2. Enter a slug (e.g., `promo`)
3. Enter the target URL
4. Choose redirect type (301 permanent or 302 temporary)
5. Optionally add UTM parameters
6. Save

Your redirect will be available at `https://yoursite.com/go/promo`

### Changing the prefix

By default, redirects use the `/go/` prefix. Change it in **Link Router → Settings**.

## Development

```bash
# Clone the repo
git clone https://github.com/investblog/edge-link-router.git
cd edge-link-router

# Install dependencies
npm install
composer install

# Start local WordPress environment
npm run env:start

# Run linting
npm run lint:php

# Build release ZIP
npm run build
```

## License

GPL-2.0+
