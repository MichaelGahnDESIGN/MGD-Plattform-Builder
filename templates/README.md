# Templates

This folder contains two kinds of templates.

## Project files (copied by `mgd-platform init`)

| File | Purpose |
|---|---|
| `MGD_PLATFORM.example.yml` | complete project profile incl. all mandatory sections |
| `AGENTS.md`, `CLAUDE.md` | agent rules for the adopting project (mandatory features, briefing, versioning) |
| `FEATURE-GOVERNANCE.md` | feature check before implementation |
| `version.example.json` | becomes `version.json` – starts at `0.0.1 Pre-Alpha` |
| `release-notes.example.json` | becomes `release-notes.json` |
| `MODULE.example.json`, `evidence.example.json` | module manifest and release evidence examples |

## Starter templates (copied by `mgd-platform template create <id>`)

| Template | Stack | Minimum hosting | Variants |
|---|---|---|---|
| [`php-mysql-starter`](php-mysql-starter/README.md) | PHP 8.2+, MySQL/MariaDB, no Composer | PHP + FTP + 1 MySQL DB (optional 2nd DB for personal data) | light + dark |
| [`wordpress-plugin-starter`](wordpress-plugin-starter/README.md) | WordPress plugin, PHP 8.1+ | WordPress plugin ZIP or WP-CLI | local admin light + dark |
| [`woocommerce-extension-starter`](woocommerce-extension-starter/README.md) | WooCommerce extension, PHP 8.1+ | WordPress plugin ZIP or WP-CLI | local admin light + dark |
| [`jtl-shop5-plugin-starter`](jtl-shop5-plugin-starter/README.md) | JTL-Shop 5 plugin structure, PHP 8.1+ | JTL plugin management / ZIP | local admin light + dark |
| [`shopware6-plugin-starter`](shopware6-plugin-starter/README.md) | Shopware 6 plugin, PHP 8.2+ | plugin ZIP, Composer, `bin/console` | administration light + dark |
| [`shopify-app-starter`](shopify-app-starter/README.md) | server-side Shopify app, Node 20+ | Shopify CLI, app hosting, theme extension | local app light + dark |

Every starter template must contain `template.json`, a light **and** a dark variant,
`version.json`, `release-notes.json` and a `README.md`. `npm run check:templates` verifies this.

Contract and details: [WIKI/18-CMS/TEMPLATES.md](../WIKI/18-CMS/TEMPLATES.md).

The five shop starters are safe scaffolds, not marketplace-ready products. They contain no
credentials, customer data, tracking or remote assets. Before an installation, use the platform's
current official documentation to complete metadata, access policies, migrations and target-store tests.
