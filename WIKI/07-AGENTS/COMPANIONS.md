# Mandatory Companions

Since 0.7.0 every project built or managed with the MGD-Plattform-Builder carries three mandatory
companions. The German user guide is [27 – Begleiter](../../wiki/27-Begleiter-Living-Documentation-Graphify-Todo.md);
this page is the technical reference.

| Companion | Kind | Owns | Project artifact |
|---|---|---|---|
| Living documentation (`MGD_Living-Documentation`) | skill | the **state**: facts, decisions, open points, risks, evidence | `DOKUMENTATION/` |
| Graphify (`graphifyy`) | tool | the **map**: machine-generated project graph | `graphify-out/` |
| Todo (`MGD_Todo_SKILL`) | skill | the **work**: tasks and status | `TODO/TODO.html` |

Companions differ from the optional recommendations in [`registry/recommendations.yml`](../../registry/recommendations.yml):
they are installed, verified and enforced. The entries there carry `mandatory: true` only so that
`mgd-platform recommend` labels them.

## Registry

[`registry/companions.yml`](../../registry/companions.yml), validated by
[`schema/companion-registry.schema.json`](../../schema/companion-registry.schema.json) in `npm run check:schemas`.

| Field | Meaning |
|---|---|
| `source.repo`, `source.track`, `source.ref`, `source.commit` | skill companions only; a GitHub repository of the registry owner. `track: latest-release` installs the newest `vX.Y.Z` tag (resolved at install time). `ref` and `commit` (tag and 40-character SHA) are the **tested floor** and the `--pinned` pin |
| `skills[]` | `name` (must match the `name:` in `SKILL.md`), `path` inside the repository, optional `include` allowlist, `required` |
| `aliases` | other install folder names that count as installed (for example `mgd-todo`) |
| `package` | tool companions only; pip package, `min_version` (floor, installed as `>=`), `install_version` (the `--pinned` pin), `requires_python`, command name |
| `project` | `profile_key` and `default_path` in `MGD_PLATFORM.yml`, `marker` that proves the artifact exists, `scaffold`/`scaffold_from_source`, Graphify `refresh` and `hook` commands |

Shipping a new release of a companion needs **no change here**: new projects get the newest release. Change `ref` and `commit`
together (one pull request) only to raise the floor, for example when a release needs a newer layout.

## Commands

```text
mgd-platform companions status  [PATH] [--home DIR]
mgd-platform companions install [PATH] [--scope project|user] [--agent claude|codex|both]
                                [--graphify user|project|skip] [--yes] [--home DIR]
mgd-platform init [--target DIR] --companions --scope project|user [--graphify user|project|skip]
```

`--home` replaces the user directory for sandboxed runs and tests. Without `--yes`, `install` prints the
plan and the exact commands and changes nothing. With `--yes`, `--scope` is required and `--graphify`
is required while the CLI is missing.

Exit codes: `status` and `install --yes` return `1` while a mandatory companion is missing or a step
failed; a dry run returns `0`.

## Install pipeline

1. **Inspect.** For each skill, look for `<base>/<dir>/skills/<name or alias>/SKILL.md` with base
   `project` and `user`, dir `.claude` (Claude Code) and `.agents` or `.codex` (Codex). Graphify counts
   as installed with a version of at least `package.min_version` on `PATH`, in `~/.local/bin` or in
   `.mgd/graphify-venv`.
2. **Plan.** Pure data: `skill`, `scaffold-docs`, `scaffold-todo`, `graphify-cli`, `graphify-config`,
   `graphify-refresh`, `graphify-hook` (only in a Git repository, and `skipped` unless `--hooks` is given).
   The dry run lists every command and every `git clone` that would run.
   Each action is `install`, `keep` or `skipped`.
3. **Fetch.** The release is resolved first: `git ls-remote --tags` lists the tags, the highest plain `vX.Y.Z` at or above
   `source.ref` wins (pre-release tags never match; the peeled commit of annotated tags is used), and only repositories of
   the registry owner are tracked. With `--pinned` the registry pin is used and the network is not asked. Then
   `git clone --no-checkout --depth 1 --branch <tag>` into a temp folder; `HEAD` must equal the resolved commit, otherwise the install aborts and the folder is removed; only then is the tree checked out,
   with LFS filters off and `GIT_DIR`, `GIT_WORK_TREE`, `GIT_INDEX_FILE`, `GIT_CONFIG*` removed from the
   environment. Repository, tag and commit are pattern-checked and passed as arguments (no shell).
4. **Validate in memory.** Only regular files; symbolic links, `.git`, `.github`, `node_modules` and
   `.env*` are refused or skipped; at most 500 files and 8 MB; `SKILL.md` must declare the expected name.
5. **Write.** Files are created exclusively (`wx`); an existing destination is `kept`. A skill folder is written
   next to its destination and renamed into place, so an interrupted run leaves no half-written skill; a folder
   without `SKILL.md` is reported, not treated as installed. No write follows a symbolic link below the project
   root. One failed action is reported and does not stop the others; a failed fetch fails only that companion.
6. **Re-inspect** and print the status.

## Graphify integration

- `graphify-config` appends to `.graphifyignore` (`.claude/`, `.agents/`, `.codex/`,
  `.mgd/graphify-venv/`, `graphify-out/`, `TODO/`) and to `.gitignore` (`graphify-out/cache/`,
  `graphify-out/.graphify_python`, `graphify-out/.graphify_root`, the dated backup folders
  `graphify-out/YYYY-MM-DD/` that `graphify update` can create, `.mgd/graphify-venv/`). Existing lines and
  their order stay untouched.
- `graphify-refresh` runs `graphify update .`: local AST analysis plus Markdown structure, no LLM, no
  network. Graphify refuses to write a smaller graph; agents must investigate instead of forcing it.
- `graphify-hook` (opt-in with `--hooks`) runs `graphify hook install`: post-commit and post-checkout hooks, plus
  a merge driver for `graph.json` in the Git config and `.gitattributes`. The hooks run Python in the
  repository root after commits and checkouts, which is why they are off by default.
- The `/graphify` agent skill is deliberately **not** installed: `graphify install --platform claude` writes a
  registration block into the user's global `~/.claude/CLAUDE.md`.
- The documentation skeleton `templates/living-docs/README.md` has a "Karte (Graphify)" section, and
  architecture statements are expected to cite `graphify query|path|explain`.

## Trust boundary

The CLI is run inside projects that may not be trusted (client repositories, pull-request branches), often by an
agent that was told to run `companions status` first. Therefore:

- Read-only commands (`status`, `doctor`, `release-check`, the dry run) never execute a program from inside the
  project. Programs are looked up only in the absolute PATH entries, the folders uv and pipx install into
  (`~/.local/bin`, `UV_TOOL_BIN_DIR`, `XDG_BIN_HOME`, `PIPX_BIN_DIR`) and nothing else. Entries that reach into the
  project are dropped however they are spelled: the comparison uses real paths, so a symlinked spelling, `/var`
  versus `/private/var` and letter case make no difference.
- Every child process (git, uv, pipx, python, pip, graphify) starts with a PATH that has no relative, empty or
  in-project entries, so a bare `git` or `python3` cannot resolve to a program inside a cloned repository.
  `git`, `uv`, `pipx`, `python -I` and `pip` also run in the system temp directory, never in the project, and on
  Windows `NoDefaultCurrentDirectoryInExePath` is set for this process and for the children.
- A project-local Graphify environment (`.mgd/graphify-venv`) is created fresh (an existing one is refused, a
  symbolic link anywhere on the way is refused) and is the only program inside the project that the CLI runs, in
  that same run. The CLI then writes a random token into it and records the environment (real project path, hash of
  the token, version) in `~/.cache/mgd-platform/venvs.json`, outside the project. A later run trusts the record only if
  the folder still holds that token; a replaced or forged folder cannot know it (inode numbers are not used, Linux reuses them). Later runs trust only a matching record, report the
  recorded version, and never read the environment's own metadata or execute it: a repository can forge anything
  inside the project, but it cannot write to your cache.
- `git clone` and `git checkout` run with hooks, fsmonitor and submodules switched off and without inherited
  `GIT_*` repository overrides, so neither your global Git configuration nor the cloned repository can make them
  run anything.
- The profile is untrusted input. Companion paths are validated at runtime, not only by the schema: every segment
  must be a plain name (letters, digits, `_`, `-`, and `.` inside a name), so no `..`, no dot folders such as `.git`
  or `.claude`, no empty segments, no control characters and no `node_modules`. Values from untrusted files are
  made printable before they reach the terminal, the Todo file location is HTML-escaped, and the project name is
  flattened to one short line before it is written into generated files.
- `init` refuses to write through a symbolic link (also a dangling one) below the target folder, with or without
  `--force`.
- Accepted trade-offs: by default the CLI trusts the registry owner's GitHub account at install time (`--pinned` avoids
  that); existing skill folders are trusted as installed without verification; Graphify is not verified by hash; the user's own pip/uv configuration and `PATH` order are used as they are.

## Enforcement

| Check | Behaviour |
|---|---|
| `doctor` | lists each companion; missing ones are hints with the install command |
| `validate` | profile only: `companions.<key>.enabled: false` is an error; a missing section or key is a warning |
| `release-check` | blocked while a companion is not installed or its project artifact is missing |
| `release_gates` | unchanged; companions are an additional built-in gate |

## Not covered

- An existing skill folder is kept as it is: its release is not checked against the floor or against `--pinned`. Remove the
  folder to reinstall. This is deliberate (a skill you updated locally must win) and is shown in the plan.
- Semantic extraction (`/graphify --update`) needs a model and stays optional.
- The install cannot verify that agents follow the work cycle; the rules live in `AGENTS.md` and
  `CLAUDE.md`, and `release-check` only proves the artifacts exist.
- The project-local virtual environment uses `Scripts\\` on Windows; that path is implemented but not tested on Windows.
