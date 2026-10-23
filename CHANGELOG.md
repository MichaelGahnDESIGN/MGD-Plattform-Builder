# Changelog

All notable changes to this project will be documented in this file.

The format follows the spirit of Keep a Changelog and semantic versioning.

## [Unreleased]

### Added

- Optionaler Skill `/code-kommentare` für kurze deutsche Kommentare in Michaels Stil,
  mit Codex-/Claude-Installer, Schutz vor Überschreiben und Symlinks sowie Beispielen
  und Hinweisen zur getrennten Divi-CSS-/JavaScript-/Plugin-Ablage.

### Planned

- Node/PostgreSQL reference implementation
- stronger reusable backoffice components
- translation CSV workflows and review queues
- compliance automation
- richer agent integration

## [0.8.2 Pre-Alpha] - 2026-10-24

### Sicherheit

- Indirekte Abhängigkeit fast-uri von 3.1.8 auf den korrigierten Stand 3.1.9 aktualisiert. Der erste Ressourcenlauf hatte vier aktuelle OSV-Meldungen für diese Bibliothek erkannt; der Sicherheitspatch bleibt innerhalb derselben Versionslinie.
- Die Quellenbezeichnung „moderate“ wird als mittlere Schwere („medium“) eingeordnet, statt eine vorhandene Einstufung als unbekannt auszugeben.

## [0.8.1 Pre-Alpha] - 2026-10-24

### Behoben

- Beobachtungsdateien aus Beispiel- und Testunterordnern bestätigen keine tatsächliche Projektumgebung. Nur die ausdrücklich in der Projektwurzel hinterlegte Datei wird ausgewertet; Regressionstest ergänzt.

## [0.8.0 Pre-Alpha] - 2026-10-24

### Hinzugefügt

- System- und Abhängigkeitsmanager mit begrenzter, rein lesender Erfassung mehrerer Paketökosysteme, CMS/Erweiterungen, Container, CI sowie ausdrücklich hinterlegter System- und Dienstversionen.
- Aktuelle OSV-/GitHub-Abfragen mit vollständiger Pagination, Drittanbieter-Supportdaten, privaten JSON-/HTML-Berichten, Suche, Filtern und Hell/Dunkel. Fehlende Quellen, nicht erreichbare Ordner und ungeprüfte Bereiche bleiben sichtbar.
- Private Projektregistrierung, Änderungsvergleich für stille Folgeprüfungen und sichere Dependabot-Konfigurations-PRs mit Aktivierungsnachweis. Kein automatischer Merge, Serverupdate oder Neustart.
- Wiederverwendbare Skills `mgd-system-resources` und `mgd-plugin-builder` einschließlich Plugin-Paketprüfung, Kartenbildern, Icons und getrenntem WordPress.org-Vertrieb.
- Sicherheitstests für Parser, Netzwerk- und Dateigrenzen, private Quellen, bestehende Konfigurationen und Update-Policy; Browserprüfung des lokalen Dashboards.

### Geändert

- `mgd-platform init` legt eine lokale Ressourcen-Konfiguration an; neue Projekte können ohne Überschreiben registriert werden.
- Foundation-CI verwendet die neue npm-Lockdatei und `npm ci --ignore-scripts`.

## [0.7.0 Pre-Alpha] - 2026-10-22

### Added

- **Mandatory companions**: living documentation (`living-documentation` skill, `DOKUMENTATION/`), Graphify (`graphify-out/`) and the Todo skill (`TODO/TODO.html`) are part of every project. Graphify is the machine-generated map of the living documentation.
- `mgd-platform companions status|install`: installs the skills from the **newest release** of each companion's own repository (resolved with `git ls-remote` at install time, never below the tested floor, verified against the looked-up commit; `--pinned` installs the tested release instead), only regular files, never overwrites existing installs (also recognises the alias `mgd-todo`), creates `DOKUMENTATION/README.md` and `TODO/TODO.html`, installs the newest Graphify (`graphifyy>=0.9.65`) via `uv`/`pipx` or a project-local venv and builds the first graph. Without `--yes` it only prints the plan and every command; `--scope` and `--graphify` must be chosen explicitly. Graphify's git hooks are opt-in (`--hooks`).
- Hardened install (found by an independent security review): read-only commands never execute a program from inside the project, no write follows a symbolic link in the project, `git`/`uv`/`pipx`/`python`/`pip` run in a neutral directory, the clone is verified against the looked-up commit before checkout (LFS and `GIT_*` overrides off), a project-local Graphify environment is created fresh and never reused or executed by later runs, skill folders appear atomically, companion paths from the profile are validated at runtime (plain names only), every child process starts with a PATH free of relative and in-project entries, the clone runs with hooks, fsmonitor, submodules and LFS off, `init` no longer writes through symlinks, and a project-local Graphify environment is trusted only if this CLI recorded it in the user's cache (forged metadata inside a repository proves nothing). The dry run shows which release would be installed.
- `mgd-platform init --companions --scope project|user` sets up a project and its companions in one run; like `companions install` it needs an explicit scope.
- **Updates in one place:** a new Todo, Living Documentation or Graphify release is used by every project installed afterwards, without any change in this repository. `registry/companions.yml` only holds the tested floor and the `--pinned` pin.
- `registry/companions.yml` and `schema/companion-registry.schema.json`; `npm run check:schemas` validates the registry.
- `companions` section in `MGD_PLATFORM.yml` (schema, examples, template): `validate` rejects `enabled: false` and warns when the section is missing, so older profiles stay valid.
- `doctor` reports the companions; `release-check` is **blocked** while one is missing.
- Work cycle in the project templates (`templates/AGENTS.md`, `templates/CLAUDE.md`, starter `AGENTS.md`/`CLAUDE.md`): ask the graph first, then update todo list, living documentation and `graphify update .` after every finished sub-goal.
- `templates/living-docs/README.md`: documentation skeleton with state, map (Graphify), decisions, open points, risks and verification.
- `.graphifyignore` and `.gitignore` entries so installed agent skills and `TODO/` stay out of the graph and the extraction cache stays out of Git.
- Installer `scripts/install-zugang.py` with a fixed list of public files, protection of existing installs and isolated tests; the MIT license and references travel with the skill. Documented limits for NAS, Windows, Linux, macOS, MFA, API locks and local versus cloud installs. The separate access UI stays unchanged.
- `tools/zugang-ui/`: local zero-dependency web UI to set up server access safely (gitignore, local env, project SSH key, test, permission rules).
- Skill `platform/skills/zugang`: secure access setup workflow (server, shop, WordPress, database) with local env and project SSH key.
- Wiki: [27 – Begleiter: Living Documentation, Graphify und Todo](wiki/27-Begleiter-Living-Documentation-Graphify-Todo.md) and `WIKI/07-AGENTS/COMPANIONS.md`.

### Changed

- `zugang` is now a standalone, project-neutral skill for Codex and Claude Code: import Markdown credentials, verify Git/upload exclusions and file protection, and test existing access minimally. No mandatory question catalogue, no automatic SSH key creation or server-log analysis any more. Existing dotenv files are preserved; earlier `.env.access.local` installations need no migration.
- `registry/recommendations.yml`: Todo and Living Documentation are marked `mandatory`, Graphify is added; `mgd-platform recommend` labels them as mandatory.
- `platform/SKILL.md`, `platform/BRIEFING.md` and the `/platform` command files: new mode `companions`; init, template and update check and install the companions after asking for scope.
- Example profiles and `templates/MGD_PLATFORM.example.yml` declare the companions and `foundation_version: "0.7.0"`.

## [0.6.0 Pre-Alpha] - 2026-09-25

### Changed

- repository renamed from `Projekt-Plattform-System` to **MGD-Plattform-Builder**; the former `MGD_Platform-Builder_TOOL` is now `MGD_Docker_Projektbuilder`
- **dual licensing** (`LICENSING.md`): CLI, validator, schemas, registries, scripts and docs stay MIT; starter templates, the reference platform, the "powered by" label and logos are under the new **MGD-Lizenz** (`MGD-Lizenz.md`, `LicenseRef-MGD`). Versions up to 0.5.1 remain MIT

### Added

- mandatory label "powered by: Michael Gahn DESIGN" (local logo light/dark, link to michael-gahn.de in a new tab) in public footer, public and private landing pages, login, backoffice footer and settings; only alignment is configurable
- starter page **Settings › License** with label, full license text, domain, white-label and integrity status
- `LicenseIntegrity` (SHA-256 of license text, label code, label CSS, logos) with backoffice warning; `mgd-platform template check` fails on modified license files; `scripts/update-license-hashes.js` for maintainers
- Ed25519-signed license keys (`MGD1.<payload>.<signature>`) for white-label (EUR 500 one-time per project/domain) and paid modules, bound to domains
- generic starter module interface (`modules/<id>/module.json`, entry, migrations, menu, free/paid), **Settings › Modules**, `schema/module-package.schema.json`
- starter user management (`/admin/users`), **My account**, forced password change, optional e-mail password reset (`mail()` or SMTP, hashed single-use tokens, rate limited)
- starter media library (`/admin/media`) with extension+MIME allowlist, random file names, GD re-encoding without metadata, usage check and picker for logos/OG image
- GrapesJS saves HTML, scoped and sanitized CSS (`CssSanitizer`) and project data incl. revisions and export/import (migration 004)
- updater sends the release channel and supports manifests with `channels`
- reference platform shows the label on login and in the footer
- private repository `MGD-Plattform-Builder-Marketplace` for paid modules/templates and the license signing tool

- agent rules in the starter (`AGENTS.md`, `CLAUDE.md`) and project templates: AI agents must not remove or bypass the license label, even on user request, and point to the white-label license instead; enforced by `template check`
- UX pass (ui-ux-pro-max checklist): 44 px touch targets, collapsible mobile backoffice menu, smoother hover states, landing page seed with hero, feature cards and call to action

### Fixed

- review fixes: license host check uses `app.url`; `sodium` and `fileinfo` are required extensions (GD optional); images are rejected instead of silently keeping EXIF data when GD is missing; module `requires.starter` is enforced against `template.json`; GrapesJS respects an explicitly chosen content format; release notes extraction matches versioned headings
- starter database migrations, the code-editor backup folder and the editor vendor README were excluded by the root `.gitignore` (`*.sql`, `backups/`, `vendor/`) and never reached the repository; targeted exceptions added
- CI and wiki/release workflows run on GitHub-hosted runners (`ubuntu-latest`); no self-hosted runner was registered, so required checks never ran
- local PHP dev server (`php -S … public/index.php`) now serves CSS, JS and images of the starter
- starter smoke test: reset throttling expectation (max. 3 requests per e-mail and hour)

## [0.5.1 Pre-Alpha] - 2026-09-25

The foundation becomes an **AI-agent driven CMS**: mandatory features every project must provide,
an agent briefing, recommendations and a first starter template.

### Added

- `version.json` as single source of truth for version number and status (`pre-alpha` … `deprecated`), `schema/version.schema.json`
- `mgd-platform version` (show, `--bump major|minor|patch`, `--status`, `--sync`, `--check`, `--note` with audience and type)
- `release-notes.json` + `schema/release-notes.schema.json`; frontend shows only `frontend` entries, backoffice shows all
- profile sections `versioning`, `cms`, `credits`, `settings`, `appearance`, `file_locations`, `code_editors`, `experience`, `template`, `briefing`
- mandatory-feature rules in the validator (`src/lib/mandatory-rules.js`): versioning, release notes, credits, legal pages with revisions and export/import, searchable/filterable settings, light **and** dark mode, PHP editor safeguard
- agent briefing: `platform/BRIEFING.md`, `registry/briefing.yml`, `mgd-platform briefing [--write]` (editor + local/CDN, version display, updater, loading screen, SEO, code editors, file locations, databases, ...)
- recommendations: `registry/recommendations.yml`, `mgd-platform recommend` incl. MGD-DevOS and public MGD skills/plugins
- starter templates: `templates/php-mysql-starter` (PHP, FTP, one MySQL DB, optional private DB, light and dark variant), `schema/template-manifest.schema.json`, `mgd-platform template list|check|create`
- `templates/version.example.json` and `templates/release-notes.example.json` copied by `mgd-platform init`
- CMS capabilities in `registry/capabilities.yml` (`cms.pages.*`, `release-notes.manage`, `credits.manage`, `settings.*`, `design.manage`, `code-editor.*`)
- PHP/MariaDB reference shows the version on the login page and in the footer (`Core/Support/Version`)
- role-based backoffice view model for admin, moderation, support, privacy/compliance, translation/editorial, operations/security and AI supervision
- machine-readable `backoffice.role_views` project profile schema
- agent guidance for server-side role/capability enforcement and field-level data minimization
- `/platform backoffice` audit/design mode
- documentation: `WIKI/18-CMS/*`, GitHub wiki pages 23 and 24, `/platform briefing|template|release` skill modes

### Fixed

- capability registry schema now accepts hyphenated domains such as `service-principals.read` (`npm run check:schemas` failed before)

### Changed

- `npm run check` additionally runs `check:version` and `check:templates`
- `doctor` recommends `version.json` and `release-notes.json`

## [0.5.0] - 2026-09-20

### Added

- translation draft, review, published and rejected workflow
- separate `translations.review`, `translations.import` and `translations.export` capabilities
- translator starter role
- JSON translation import
- JSON translation export
- reusable `mgd-translations-v1` exchange format
- CLI translation import and export scripts
- translation review actions in the backoffice
- service-principal detail pages
- dedicated `service_principal_events` lifecycle history
- creation, rotation and revocation history records
- job idempotency hashes and unique database constraint
- `enqueueIdempotent()` duplicate protection
- explicit `JobHandlerRegistry`
- dedicated demonstration job-handler class
- database migration policy documentation
- expanded integration tests for review, import/export, agent history, token rotation, idempotency and handler dispatch
- complete public Wiki guide for Foundation 0.5

### Changed

- imported translations always enter draft state
- exported translation JSON can be imported again
- workers dispatch through registered handlers instead of a hard-coded switch
- job enqueue now returns the created job ID
- service-principal lifecycle information is available independently of the global audit log
- capability registry version aligned with Foundation 0.5

### Security

- translation import cannot bypass publication review
- old service-principal tokens become unusable after rotation
- revoked service principals remain unusable
- repeated business requests can deduplicate jobs through idempotency keys
- idempotency keys are stored only as SHA-256 derived hashes

## [0.4.0] - 2026-09-20

### Added

- checksum-aware MariaDB migration runner
- `schema_migrations` history with immutable migration checks
- adoption path for existing 0.3 baseline databases
- translation-key registry
- locale-specific draft and published translations
- translation backoffice and audit events
- service-principal management backoffice
- registered-scope validation for service principals
- service-principal token rotation and revocation
- service-principal last-used tracking
- one-time token display in the backoffice
- jobs-specific read/manage capabilities
- atomic database job claiming with worker identity
- stale processing-job recovery after abandoned worker locks
- exponential retry delays
- per-job maximum attempt counts
- dead-letter state and manual retry
- reusable backoffice UI helpers for tables, badges and notices
- extended integration tests covering migrations, translations, service principals and dead letters
- full public Wiki guide for 0.4

### Changed

- new reference installations now use `php scripts/migrate.php`
- Docker MariaDB setup no longer auto-imports a mutable schema snapshot
- CLI-created service principals now validate requested scopes against registered capabilities
- jobs no longer use the security-audit capability as their operational permission
- revoked service principals cannot be accidentally reactivated by token rotation
- service-principal create/rotate/revoke actions and their audit events are transactional

### Security

- migration checksums detect modified historical migrations
- arbitrary service-principal scope names are rejected
- expired or revoked service principals cannot rotate tokens
- token usage updates `last_used_at`
- dead-letter failures remain visible instead of disappearing from the worker loop

## [0.3.0] - 2026-09-20

### Added

- runnable PHP 8.3 / MariaDB reference platform
- account login with password hashing
- secure session-cookie defaults and session rotation
- CSRF protection
- account roles and capability resolution from MariaDB
- capability-aware backoffice navigation
- server-side endpoint authorization
- account suspension example with audit logging
- audit-event backoffice view
- security-event backoffice view
- failed-login security-event example
- service principals for AI agents and automation
- hashed Bearer tokens and `/api/me` identity endpoint
- database-backed jobs/outbox implementation
- CLI worker example
- first-admin creation script without default credentials
- service-principal creation script with one-time token output
- local MariaDB Docker Compose development profile
- PHP linting and Composer validation in Foundation Checks
- real MariaDB integration smoke test in GitHub Actions
- public Wiki guide for the runnable PHP/MariaDB reference platform

### Security

- no default administrator password is shipped
- automation tokens are stored hashed
- UI visibility is not used as an authorization boundary
- failed-login logs use an email hash instead of raw email
- self-suspension is blocked in the reference backoffice

## [0.2.0] - 2026-09-20

### Added

- `mgd-platform` Node.js CLI
- project initialization with general, game, community, creator and ecommerce presets
- schema and cross-field validation for project profiles
- `doctor` project structure checks
- automated foundation gap audit
- Markdown audit reports
- machine-readable release evidence
- release readiness checks
- module skeleton generator
- foundation version check
- evidence JSON schema
- central capability registry and validation schema
- automated schema, example and documentation checks
- GitHub Actions Foundation Checks
- CLI bootstrap smoke tests
- framework-neutral PHP/MariaDB starter reference
- local capability-driven backoffice demo
- complete public Wiki documentation for CLI and automation

### Changed

- public GitHub Wiki is prominently linked from the README
- CLI is the recommended quick-start path
- project profile schema enforces additional security relationships
- Wiki publishing workflow uses the clearer `publish-wiki.yml` filename

## [0.1.0] - 2026-09-20

### Added

- initial public project foundation
- German and English README
- agent instructions for Claude Code and ChatGPT Codex
- project profile schema and example
- privacy, security and compliance architecture
- modular backend and backoffice concepts
- Docker, staging, backup and monitoring guidance
- domain packs
- governance, security and contribution documentation
- optional platform agent skill
