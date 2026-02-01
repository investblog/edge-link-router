# Edge Link Router - Development Guide

## Prerequisites

- **Docker** (Desktop or Engine)
- **Node.js** LTS (18+) + npm
- **Git**
- **Composer** (for PHPCS/WPCS linting)

## Quick Start

```bash
# Install dependencies
npm install
composer install

# Start WordPress environment
npm run env:start

# Activate the plugin
npm run wp:plugin:activate
```

## Access WordPress

- **URL**: http://localhost:8888
- **Admin**: http://localhost:8888/wp-admin
- **Username**: `admin`
- **Password**: `password`

## Available Commands

### Environment

| Command | Description |
|---------|-------------|
| `npm run env:start` | Start WordPress environment |
| `npm run env:stop` | Stop WordPress environment |
| `npm run env:destroy` | Destroy environment (removes containers and data) |
| `npm run env:logs` | View Docker container logs |

### WordPress CLI

| Command | Description |
|---------|-------------|
| `npm run wp:cli -- <command>` | Run any WP-CLI command |
| `npm run wp:plugin:activate` | Activate the plugin |
| `npm run wp:plugin:deactivate` | Deactivate the plugin |
| `npm run wp:rewrite:flush` | Flush rewrite rules |
| `npm run wp:db:reset` | Reset database to fresh state |
| `npm run wp:db:export` | Export database to dump.sql |

### Linting

| Command | Description |
|---------|-------------|
| `npm run lint:php` | Run PHP CodeSniffer |
| `npm run lint:php:fix` | Auto-fix PHP coding standards |

## Plugin Structure

```
plugin/
├── edge-link-router.php      # Main plugin file
├── src/
│   ├── Core/                 # Domain layer (no WP dependencies)
│   │   ├── Models/           # Value objects (Link, RedirectDecision)
│   │   ├── Contracts/        # Interfaces
│   │   ├── Resolver.php      # Slug resolution logic
│   │   └── Validator.php     # Validation rules
│   ├── WP/                   # WordPress adapter layer
│   │   ├── Admin/            # Admin pages and menu
│   │   ├── Repository/       # Database implementations
│   │   ├── REST/             # REST API controllers
│   │   ├── CSV/              # Import/Export
│   │   └── ...
│   └── Integrations/         # External services
│       ├── Cloudflare/       # CF Worker integration
│       └── ThreeOhOneSt/     # 301.st stub
├── assets/                   # CSS/JS
├── templates/                # Worker template
└── languages/                # i18n files
```

## Database Tables

The plugin creates three tables (prefix: `cfelr_`):

- `{wp_prefix}cfelr_links` - Redirect rules
- `{wp_prefix}cfelr_clicks_daily` - Aggregated click statistics
- `{wp_prefix}cfelr_integrations` - Integration states (Cloudflare, 301.st)

## Troubleshooting

### Port already in use

If port 8888 is busy, edit `.wp-env.json` and change `port` value.

### Rewrite rules not working

```bash
npm run wp:rewrite:flush
```

### Clean restart

```bash
npm run env:destroy
npm run env:start
npm run wp:plugin:activate
```

### View PHP errors

```bash
npm run env:logs
```

Or check `wp-content/debug.log` in the WordPress container.

### Permission issues on Windows

Run Docker Desktop as Administrator, or ensure your user has access to Docker socket.
