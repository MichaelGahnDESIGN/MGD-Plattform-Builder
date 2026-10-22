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

The initializer prepares the project profile, agent rules, feature governance and the release-evidence directory without blindly overwriting existing files. Install the mandatory companions next (see below).

## Mandatory companions: living documentation, Graphify, Todo

Every project carries three mandatory companions: the **living documentation** (state, decisions, open
points, risks, evidence), **Graphify** (the machine-generated map of that documentation) and the **Todo**
skill (tasks). Install them together with the project:

```bash
mgd-platform companions status .                  # what is installed, what is missing
mgd-platform companions install .                 # dry run: prints the plan, changes nothing
mgd-platform companions install . --scope project --graphify user --yes
```

Ask the user two things first – the command refuses to install without explicit answers:

| Question | Option | Meaning |
|---|---|---|
| Where should the skills live? | `--scope project` | `.claude/skills` and `.agents/skills` in this project only |
| | `--scope user` | `~/.claude/skills` and `~/.agents/skills` for all projects |
| How should the Graphify CLI be installed? | `--graphify user` | `uv tool install` (or `pipx`) – available everywhere |
| | `--graphify project` | project-local virtual environment in `.mgd/graphify-venv/` (needs Python 3.10+) |
| | `--graphify skip` | not now – `companions status`, `doctor` and `release-check` keep reporting it as missing |

What the command does:

1. Clones the **newest release** of each skill companion's own GitHub repository (the highest `vX.Y.Z` tag, resolved with
   `git ls-remote` at install time; never below the tested floor in `registry/companions.yml`) into a temporary folder. The clone must
   match the commit that `ls-remote` reported, otherwise the install aborts. Only regular files are copied (no symlinks, no `.git`, no
   dotenv files). `--pinned` installs exactly the tested release from the registry instead (reproducible installs, CI).
2. Installs the skills for Claude Code (`.claude/skills`) and Codex (`.agents/skills`, legacy `.codex/skills`
   is recognised). Use `--agent claude` or `--agent codex` for one client. Existing installs – also under the
   old name `mgd-todo` – are never overwritten.
3. Creates `DOKUMENTATION/README.md` (documentation skeleton) and `TODO/TODO.html` plus `TODO/.todo-config`
   when they do not exist. Set `companions.living_documentation.path` in `MGD_PLATFORM.yml` to adopt an
   existing documentation folder (for example `docs`) instead.
4. Installs or upgrades the Graphify CLI to the newest release (`graphifyy>=0.9.65`; with `--pinned` exactly `graphifyy==0.9.93`; any
   installed copy from 0.9.65 on is accepted; Python 3.10+) as chosen, writes `.graphifyignore` (agent skills and `TODO/` stay out of the graph) and `.gitignore` entries, and builds the graph with
   `graphify update .` (local AST analysis, no LLM, no network).
5. **Optional, off by default:** `--hooks` installs Graphify's post-commit and post-checkout hooks and a merge driver for `graph.json`
   (Git config and `.gitattributes`). The hooks run Python in the repository root after commits and checkouts, so enable them only in
   repositories you trust: a checked-out branch could ship a file that shadows a Python module. Without them, `graphify update .` stays an
   explicit step of the work cycle.

The optional `/graphify` agent skill is **not** installed: `graphify install --platform claude` writes into your global `~/.claude/CLAUDE.md`,
so run it yourself if you want it.

Safety rules of the installer: it never runs a program that lives inside the project (read-only commands look only in your own program folders;
every child process gets a PATH without relative or in-project entries, compared by real path); installers (`git`, `uv`, `pipx`, `python`,
`pip`) run in a neutral directory; a project-local Graphify environment is created fresh (an existing `.mgd/graphify-venv` is refused) and is
the only project program that runs, in that same run; it is remembered in `~/.cache/mgd-platform/venvs.json` (outside the project, because
anything inside a repository can be forged), and later runs tell you to run `.mgd/graphify-venv/bin/graphify update .` yourself; no write
(including `init`) follows a symbolic link inside the project; paths from `MGD_PLATFORM.yml` must be plain project-relative names; the clone
is verified against the commit it was resolved to before it is checked out, with Git hooks, fsmonitor, submodules and LFS switched off.
The dry run (`companions install` without `--yes`) also asks GitHub which release would be installed and prints it, so you can see it before you
confirm. Trade-off of the newest-release default: you trust your own GitHub account (and PyPI for Graphify) at install time; use `--pinned` to
install exactly the tested releases instead.

Shortcut for a new project: `mgd-platform init --target ../my-project --companions --scope project --graphify user` (add `--hooks` to opt in to the Git hooks). Like `install`, it refuses to run without an explicit `--scope`.

**Updates happen in one place.** Release a new Todo, Living Documentation or Graphify version in its own repository and every new project
installed afterwards uses it; the registry here only holds the floor and the `--pinned` pin and rarely changes. Existing installs are never
overwritten and their release is not checked (the plan says "remove the folder to reinstall"; this also applies to `--pinned`), so a skill you updated locally (for example `~/.claude/skills/mgd-todo`) is what new projects use.

The companions are reported by `mgd-platform doctor` (a hint, not a failure), `mgd-platform validate` rejects `enabled: false` in the profile, and
`mgd-platform release-check` is blocked while one is missing. `release-check` also counts skills installed for the user (`~/.claude/skills`), so a
CI runner needs `--scope project` (committed or installed in the job) to see them. Requirements: Git, and for Graphify either `uv`,
`pipx` or Python 3.10+. Offline or in CI, run `mgd-platform companions status` instead of installing.

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

After installing the `platform` skill, let the agent run `mgd-platform companions status` in the project: the
platform skill asks the user the two install questions above and then installs the companions.

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

## Optional comment style: code-kommentare

```sh
python3 scripts/install-code-kommentare.py --agent codex
```

Use `--agent claude`, `--agent both`, `--agent codex-legacy` or
`--project /path/to/existing-project` as appropriate. The installer preserves existing
files and copies only the public skill bundle. It does not change global agent rules.
Invoke `/code-kommentare` in Claude Code or `$code-kommentare` in Codex.
See [style, examples and installation details](WIKI/Code-Kommentare.md).

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
