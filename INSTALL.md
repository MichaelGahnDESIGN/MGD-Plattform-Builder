# Installation and Usage

The MGD Project Platform System can be used as documentation, as project rules, through its CLI and through optional AI-agent skills.

## Recommended: CLI

Requirements:

- Node.js 20 or newer
- Git

Clone the repository:

```bash
git clone https://github.com/MichaelGahnDESIGN/Projekt-Plattform-System.git
cd Projekt-Plattform-System
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
https://github.com/MichaelGahnDESIGN/Projekt-Plattform-System/wiki/18-CLI-Validator-und-Automatisierung

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
git clone https://github.com/MichaelGahnDESIGN/Projekt-Plattform-System.git
mkdir -p ~/.codex/skills
cp -R Projekt-Plattform-System/platform ~/.codex/skills/platform
mkdir -p ~/.codex/commands
cp Projekt-Plattform-System/.codex/commands/platform.md ~/.codex/commands/
```

### Claude Code

```bash
git clone https://github.com/MichaelGahnDESIGN/Projekt-Plattform-System.git
mkdir -p ~/.claude/skills
cp -R Projekt-Plattform-System/platform ~/.claude/skills/platform
mkdir -p ~/.claude/commands
cp Projekt-Plattform-System/.claude/commands/platform.md ~/.claude/commands/
```

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
