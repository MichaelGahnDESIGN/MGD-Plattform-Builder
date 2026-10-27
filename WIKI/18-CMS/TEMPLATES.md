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
