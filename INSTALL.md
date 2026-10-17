# Installation and Usage

The MGD-Plattform-Builder can be used as documentation, as project rules, through its CLI and through optional AI-agent skills.

## Recommended: CLI

Requirements:

- Node.js 20 or newer
- Git

Clone the repository:

```bash
git clone https://github.com/MichaelGahnDESIGN/MGD-Plattform-Builder.git
cd MGD-Plattform-Builder
npm install
```

Link the CLI locally:

```bash
npm link
```

Now verify the installation:

```bash
mgd-platform --help
```

## Create a project

General platform:

```bash
mgd-platform init --preset general --target ../my-project
```

Other presets:

```bash
mgd-platform init --preset game --target ../my-game
mgd-platform init --preset community --target ../my-community
mgd-platform init --preset creator --target ../my-creator-platform
mgd-platform init --preset ecommerce --target ../my-store
```

The initializer prepares the project profile, agent rules, feature governance and the release-evidence directory without blindly overwriting existing files.

## Validate and audit

Inside the adopting project:

```bash
mgd-platform validate
mgd-platform doctor
mgd-platform audit --write
```

Before a release:

```bash
mgd-platform release-check
```

Create a module skeleton:

```bash
mgd-platform module create "Notifications"
```

Check the declared foundation version:

```bash
mgd-platform update
```

Full documentation:
https://github.com/MichaelGahnDESIGN/MGD-Plattform-Builder/wiki/18-CLI-Validator-und-Automatisierung

## Manual Level 1: Documentation only

The foundation can still be used without Node.js or the CLI.

Copy only the project profile:

```bash
cp templates/MGD_PLATFORM.example.yml /path/to/project/MGD_PLATFORM.yml
```

## Manual Level 2: Project rules

Also copy:

```bash
cp templates/AGENTS.md /path/to/project/AGENTS.md
cp templates/CLAUDE.md /path/to/project/CLAUDE.md
cp templates/FEATURE-GOVERNANCE.md /path/to/project/FEATURE-GOVERNANCE.md
```

## Agent skill

### ChatGPT Codex / Codex Desktop

Clone the repository and copy `platform/` into the local skills directory used by your Codex setup.

```bash
git clone https://github.com/MichaelGahnDESIGN/MGD-Plattform-Builder.git
mkdir -p ~/.codex/skills
cp -R MGD-Plattform-Builder/platform ~/.codex/skills/platform
mkdir -p ~/.codex/commands
cp MGD-Plattform-Builder/.codex/commands/platform.md ~/.codex/commands/
```

### Claude Code

```bash
git clone https://github.com/MichaelGahnDESIGN/MGD-Plattform-Builder.git
mkdir -p ~/.claude/skills
cp -R MGD-Plattform-Builder/platform ~/.claude/skills/platform
mkdir -p ~/.claude/commands
cp MGD-Plattform-Builder/.claude/commands/platform.md ~/.claude/commands/
```

## Standalone access skill: zugang

From the repository root, with Python 3.9 or newer:

```bash
python3 scripts/install-zugang.py --agent both
```

This installs only `platform/skills/zugang/`, including its references and MIT license, into
`~/.agents/skills/zugang` for Codex and `~/.claude/skills/zugang` for Claude Code.
Use `--agent codex` or `--agent claude` for one client. For a Codex setup using `~/.codex/skills`,
use `--agent codex-legacy` instead of the current Codex target; avoid duplicate installations.
Use `--project /path/to/existing-project` for project-scoped installation. On Windows, `py -3`
can replace `python3`. Existing destinations are never overwritten; review local customizations
and move the old installation outside discovery paths before a deliberate update.

Invoke `/zugang private/access.md` in Claude Code or `$zugang private/access.md` in Codex.
Restart the client/start a new chat if discovery needs refreshing. Local personal installation
does not automatically install a skill in cloud sessions. Plain ChatGPT without local execution
can provide instructions but cannot perform the file protection or access checks.

The workflow imports a supplied Markdown credential file, preserves existing env files, verifies
Git/upload exclusions and platform-specific file protection, and tests authentication without
changing application data or security settings. It does not create SSH keys or deploy by default.
The older standalone SSH access UI remains separate and does not inherit these protections.
See [the access guide](WIKI/Zugaenge-einrichten.md) for details and migration notes.

## Release evidence

Projects using release gates store machine-readable evidence under:

```text
.mgd/evidence/
```

For example:

```text
.mgd/evidence/privacy-reviewed.json
.mgd/evidence/security-reviewed.json
.mgd/evidence/restore-tested.json
```

Use `templates/evidence.example.json` as a reference.

## Updating

Pull the foundation repository and run:

```bash
mgd-platform update /path/to/project
```

Do not blindly replace project rules or change the declared foundation version. Review the gap report and migration impact first.

## No-skill agents

Any agent that can read Markdown and execute local commands can use the foundation.

Point it to:

- `AGENTS.md`
- `MGD_PLATFORM.yml`
- the public GitHub Wiki
- the relevant technical WIKI pages
- `mgd-platform doctor`
- `mgd-platform audit`

The core method does not depend on proprietary agent features.


## Runnable PHP/MariaDB reference

The reference platform is located in:

```text
reference/php-mariadb/
```

Local setup:

```bash
cd reference/php-mariadb
docker compose up -d db
composer install
cp config.example.php config.php
php scripts/migrate.php
```

Create the first administrator, then start:

```bash
php -S 127.0.0.1:8080 -t public
```

### Translation exchange

Import:

```bash
php scripts/import-translations.php translations-de.json
```

Export one locale:

```bash
php scripts/export-translations.php de > translations-de.json
```

Imported entries always become drafts and must pass the translation review workflow before publication.

### Background worker

```bash
php scripts/worker.php
```

The 0.5 reference supports atomic job claiming, retries, dead letters, stale-worker recovery, idempotency keys and a handler registry.

Full reference documentation:

https://github.com/MichaelGahnDESIGN/MGD-Plattform-Builder/wiki/22-Translation-Review-Agenten-Historie-und-Idempotente-Jobs
