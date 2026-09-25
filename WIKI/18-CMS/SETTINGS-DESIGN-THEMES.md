# Settings, Design, Light/Dark, File Locations and Code Editors

## Settings are always searchable and filterable

Settings are defined in a registry (key, category, label, description, keywords, type, default).
The backoffice settings page offers:

- a category menu (Allgemein, Versionsnummern, Release Notes, Credits, Rechtliches & CMS-Seiten,
  CMS-Editor, Design, Darstellung, SEO, Ladebildschirm, Updater, Cookie-Box, Dateispeicherorte,
  Code-Editoren, Wartungsmodus)
- a search field across label, description, keywords and key
- a category filter
- a server-side fallback (`?q=` and `?category=`) that works without JavaScript

Profile: `settings.searchable: true`, `settings.filterable: true` (disabling either is an error).

## Light and dark mode

Every template ships a light **and** a dark variant. Backoffice → Settings → Darstellung:

| Setting | Values |
|---|---|
| Show toggle | yes / no |
| Toggle locations | header, footer, login, settings, floating |
| Default mode | system, light, dark |
| Allow user choice | yes / no (stored per browser) |

Implementation notes: use a `data-theme` attribute on `<html>`, set it before first paint with a
tiny nonce-protected inline script, wrap `localStorage` in `try/catch`, respect
`prefers-color-scheme` for `system`.

## Design

Backoffice → Settings → Design edits color tokens separately for light and dark:
`primary`, `primary_contrast`, `secondary`, `accent`, `success`, `warning`, `danger`, `info`,
`background`, `surface`, `text`, `muted`, `border`, plus radius and a **local** font family.
Values are validated strictly (`#rrggbb`) and emitted as CSS custom properties. A preview and
"reset to defaults" are part of the page.

## File locations

Backoffice → Settings → Dateispeicherorte lists important paths with *exists* / *writable*
state: web root, uploads, backups, logs, custom assets, `version.json`, `release-notes.json`,
configuration file (path only), local editor vendor folder. **Paths are defined in the config file,
not editable from the web.**

## Code editors (optional, asked in the briefing)

| Language | Scope | Safeguards |
|---|---|---|
| CSS / JS | `public/assets/custom/custom.css`, `custom.js` | admin only, CSRF, backup before save, audit |
| PHP | one hook file (e.g. `custom/hooks.php`) | additionally requires `allow_php_editor = true` **in the config file** |

Paths are resolved with `realpath` against an allowlist. Profile: `code_editors.enabled`,
`languages`, `php_requires_config_flag` (must be true when PHP is enabled), `backup_before_save`.
Capabilities: `code-editor.assets.manage`, `code-editor.server.manage` (both require
re-authentication in production).
