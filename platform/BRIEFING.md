# Agent Briefing · MGD-Plattform-Builder

This is the interview a coding agent (Claude Code, ChatGPT Codex, ...) runs **before** it builds a
project on the MGD-Plattform-Builder. The questions live in
[`registry/briefing.yml`](../registry/briefing.yml); answers are written into `MGD_PLATFORM.yml`.

```bash
mgd-platform briefing ./mein-projekt          # shows answered ✓ / open mandatory ✗ questions
mgd-platform briefing ./mein-projekt --write  # writes BRIEFING.md with checkboxes
mgd-platform recommend ./mein-projekt         # suggests MGD skills, tools and plugins
```

## Flow

1. **Understand the project.** Ask for a short description, type, markets and languages.
2. **Ask every mandatory question** from `registry/briefing.yml`. Ask in small groups (max. 3–5
   questions per message), offer sensible defaults and explain trade-offs in one sentence each.
3. **Write the answers** into `MGD_PLATFORM.yml` (sections `versioning`, `cms`, `credits`,
   `settings`, `appearance`, `file_locations`, `code_editors`, `experience`, `template`).
4. **Recommend tools.** Run `mgd-platform recommend` (or read `registry/recommendations.yml`) and
   suggest only entries that match the project. Recommend **MGD-DevOS** when several projects,
   dashboards or agents are managed in parallel. Store accepted entries in
   `briefing.accepted_recommendations`.
5. **Close the briefing.** Set `briefing.completed: true` and the date, list remaining
   questions in `briefing.open_questions`, then run `mgd-platform validate` and `doctor`.

## Mandatory features – never ask *whether*, only *how*

These are always part of every project. The briefing only clarifies their configuration:

| Feature | What the agent must clarify |
|---|---|
| Versioning | Start version (default `0.0.1 Pre-Alpha`), where it is shown (login, settings/game settings, footers, public/private landing pages) and for whom (public, private, both) |
| Release notes | Settings menu item with a timeline; which audiences the frontend shows (default: `frontend` only) |
| Credits | People and roles first; then AI systems, tools, plugins, libraries, fonts and icons with logo, name, description, provider info, links and license tags |
| Legal/CMS pages | Contact, imprint, terms, privacy, EU cookies, cookie-box texts, payment, shipping, withdrawal, withdrawal button text, youth protection, accessibility, AI philosophy – edit, delete, add, revisions, export/import |
| Settings | Always searchable and filterable |
| Light/Dark | Both variants exist; ask for default mode, whether the toggle is shown and where |
| Design | Color tokens (primary, secondary, accent, ...) editable under Settings → Design |

## Topics the agent must ask about

- **CMS editor:** TinyMCE, GrapesJS, Quill, Editor.js, Markdown or plain – and whether the editor is
  **embedded locally in the app or loaded from a CDN** (recommendation: local; CDN must be listed in
  privacy policy and credits).
- **Template and hosting:** minimum PHP + FTP + one MySQL database for logins; optional second MySQL
  database for personal/sensitive data.
- **File locations:** show important paths in the backoffice?
- **Code editors:** backoffice editors for CSS, JS, PHP, HTML, JSON? PHP only with an extra config
  flag, backup before save and audit log.
- **Updater, loading screen (Ladeüberbrückung), SEO, cookie box, maintenance mode, error pages,
  onboarding, search.**
- **Agents:** which agents work on the project and whether production changes need approval.

## Rules

- Never invent legal texts as final. Seed placeholders and mark them *"rechtlich prüfen lassen"*.
- Never hotlink fonts, icons or libraries; embed them locally and list them in the credits.
- Every delivery increases the version number and adds a release note (`mgd-platform version
  --bump patch --note "..." --audience frontend`).
- Ask, don't assume, for anything that has legal, privacy or cost impact.
