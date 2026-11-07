# Templates

`templates/` contains profile templates (`MGD_PLATFORM.example.yml`, `AGENTS.md`, `CLAUDE.md`,
`version.example.json`, `release-notes.example.json`, ...) and **starter templates** in
sub-folders with a `template.json` manifest ([schema](../../schema/template-manifest.schema.json)).

## Contract for every starter template

- `template.json` with requirements, databases, deployment and features
- **light and dark variant** (`variants.light`, `variants.dark` point to existing files)
- `version.json` (starts at `0.0.1 Pre-Alpha`) and `release-notes.json`
- `README.md` with installation, configuration and security notes
- all mandatory features from [Mandatory Features](MANDATORY-FEATURES.md)

`npm run check:templates` (`mgd-platform template check`) enforces the contract in CI.

## php-mysql-starter

Minimum hosting: **PHP, FTP, one MySQL/MariaDB database** that stores logins, settings and CMS
content. Optional **second MySQL database** for personal and sensitive data (profiles, addresses,
consents), so it can be secured, backed up and deleted separately.

```bash
mgd-platform template list
mgd-platform template create php-mysql-starter --target ./my-project
mgd-platform init --target ./my-project
mgd-platform briefing ./my-project --write
```

Details: [`templates/php-mysql-starter/README.md`](../../templates/php-mysql-starter/README.md).

## Plugin and app starters

The following starters establish a consistent MGD baseline for plugin and app work. They are deliberately
small: each has local light/dark tokens, version and release-note files, German agent rules, an unchanged
MGD license copy and a protected label asset. They do **not** claim marketplace approval, automatic updates
or productive compatibility.

| Starter | Use when | Important boundary |
| --- | --- | --- |
| `wordpress-plugin-starter` | a WordPress-only admin or frontend extension is needed | All writes require a nonce and server-side capability check. |
| `woocommerce-extension-starter` | store behavior extends WooCommerce | Guest orders and payment state need an explicit server-side policy. |
| `jtl-shop5-plugin-starter` | a JTL-Shop-5 plugin is planned | Verify the actual Shop 5 API and cache behavior before installation. |
| `shopware6-plugin-starter` | a Shopware 6 extension is planned | Keep migrations, ACL, administration and storefront responsibilities separate. |
| `shopify-app-starter` | Shopify needs protected state or webhooks | Theme is presentation only; HMAC validation and idempotency belong to the app. |

Create a project with the normal command, for example:

```bash
mgd-platform template create shopware6-plugin-starter --target ./my-shopware-plugin
```

Then run the project briefing and install the mandatory companions. Production deployment, app-store
publication and installation in a customer shop remain separate, manually verified delivery steps.

## Einheitliches MGD-Interface und Theme-Integration

Alle Shop-Starter folgen demselben Integrationsstandard: lokale MGD-Design-Tokens, Light/Dark,
zugängliche Zustände, eine nachvollziehbare Plugin-Empfehlungskarte und ein optionaler,
versionierter Adapter zu `theme.michael-gahn.de`. Er ersetzt keine Plattformberechtigungen und darf
keine API-Endpunkte, Tokens oder Datenformate erfinden. Die Kernfunktion muss ohne entfernten
Katalog- oder Theme-Dienst sicher weiterlaufen.

Kopierfertige Prompts für ChatGPT und Claude sowie die Abnahmekriterien stehen unter
[Prompts für Plugins und Apps](PLUGIN-APP-PROMPTS.md).
