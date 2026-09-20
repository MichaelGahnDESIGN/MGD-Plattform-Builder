# Installation and Usage

The foundation can be used at three levels.

## Level 1: Documentation only

Reference the repository and copy only the project profile.

```bash
cp templates/MGD_PLATFORM.example.yml /path/to/project/MGD_PLATFORM.yml
```

## Level 2: Project rules

Also copy:

```bash
cp templates/AGENTS.md /path/to/project/AGENTS.md
cp templates/CLAUDE.md /path/to/project/CLAUDE.md
```

## Level 3: Agent skill

### ChatGPT Codex / Codex Desktop

Clone the repository and copy `platform/` into the local skills directory used by your Codex setup.

Example:

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

## Updating

Pull the repository again and replace the copied skill directory.

Before applying a new foundation version to an existing project, run `/platform update` and review the migration plan.

## No-skill agents

Any agent that can read Markdown can use the foundation.

Point it to:

- AGENTS.md
- MGD_PLATFORM.yml
- the relevant WIKI pages

The core method never depends on proprietary agent features.
