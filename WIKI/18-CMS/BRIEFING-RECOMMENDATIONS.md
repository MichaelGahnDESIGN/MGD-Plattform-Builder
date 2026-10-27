# Briefing and Recommendations

## Briefing

Before an agent builds anything it runs the briefing from
[`platform/BRIEFING.md`](../../platform/BRIEFING.md). Questions live in
[`registry/briefing.yml`](../../registry/briefing.yml); each maps to a path in `MGD_PLATFORM.yml`.

```bash
mgd-platform briefing ./my-project          # ✓ answered, ✗ open mandatory question
mgd-platform briefing ./my-project --write  # BRIEFING.md with checkboxes
```

Sections: project, template/hosting/databases, versioning & release notes, CMS/legal/credits,
backoffice & settings, design/light/dark, product experience, agents & recommendations.

Product-experience questions the agent must ask: **updater**, **loading screen
(Ladeüberbrückung)**, **SEO**, cookie box, maintenance mode, error pages, onboarding, search.
Hosting questions: PHP + FTP + one MySQL DB as minimum, optional second MySQL DB for personal data.
Editor questions: which editor and **local vs. CDN**. Backoffice: file locations, code editors.

## Recommendations

[`registry/recommendations.yml`](../../registry/recommendations.yml) lists public MGD skills, tools
and plugins with matching rules (`always`, project types, features, experience topics, staging,
docker, description keywords).

```bash
mgd-platform recommend ./my-project
```

- **MGD-DevOS** is recommended when AI agents, several projects or dashboards are involved
  (desktop project hub with dashboards in tabs and the `/projektstart` / `/dashboard` skill).
- Base recommendations: DEV, Todo, Living Documentation, Backup, ProjectClean.
- Conditional: Software Updater (updater), AI Project Updater (staging), Fragenkatalog (concept-heavy
  projects), CI Designmanual (brand/design), PlayTest, Bugreport, WordPress MCP, Divi 5,
  AI-Kennzeichnung plugins, JTL SEO, Claude-Codex MCP, Platform Builder (Docker).

Recommendations are suggestions. Accepted ones are stored in
`briefing.accepted_recommendations`; the CLI marks them with ✓.
