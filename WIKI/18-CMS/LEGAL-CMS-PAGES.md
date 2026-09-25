# Legal and CMS Pages

Legal pages are ordinary CMS pages with the type `legal`. They can be **edited, deleted, added,
restored from revisions, exported and imported** – nothing is hardcoded in templates.

## Mandatory set

| Profile id | German page | Required when |
|---|---|---|
| `contact` | Kontakt | always |
| `imprint` | Impressum | always |
| `privacy` | Datenschutz | always |
| `cookies` | EU-Cookies | always |
| `cookie_banner` | Texte der Cookie-Box (snippet) | always |
| `accessibility` | Barrierefreiheit | always |
| `ai_philosophy` | AI-Philosophie | always |
| `credits` | Credits (intro) | always |
| `terms` | AGB | billing or store |
| `payment` | Zahlung | billing or store |
| `shipping` | Versand | billing or store |
| `withdrawal` | Widerrufsbelehrung | billing or store |
| `withdrawal_button` | Widerrufs-Button-Text (snippet) | billing or store |
| `youth_protection` | Jugendschutz | game, community, creator |

Seeded texts are placeholders and must be marked *"Platzhalter – rechtlich prüfen lassen"*. The
foundation never claims legal compliance.

## Page types

- `page` – normal content page
- `legal` – legal page, linked in the footer
- `snippet` – text block without its own URL (cookie-box text, withdrawal button text)

## Lifecycle

```text
create → revision 1
edit   → revision n+1 (author, time, note)
restore revision k → revision n+1 with content of k
delete → trash (soft delete) → restore
                             → permanent delete (admin, confirmation, audit)
```

## Export / import

- Export: JSON of all or selected pages, optionally including revisions.
- Import: JSON upload or paste; existing slugs get a **new revision**, new slugs become new pages.
- Imported HTML is **never trusted**: it is sanitized server-side like every save.

## Editor choice (briefing question)

| `cms.editor` | Notes |
|---|---|
| `tinymce` | classic WYSIWYG. TinyMCE 7 is GPL-2.0-or-later or commercial – check the license |
| `grapesjs` | visual page builder (BSD-3-Clause) |
| `quill` | lightweight rich text (BSD-3-Clause) |
| `editorjs` | block editor (Apache-2.0) |
| `markdown` | Markdown with preview |
| `plain` | plain textarea |

`cms.editor_delivery` is **always asked**:

- `local` (recommended) – editor files are embedded in the app (e.g. `public/assets/vendor/<editor>/`),
  no third-party request, works offline.
- `cdn` – loaded from a CDN; the provider must be listed in the privacy policy and the credits.
  The validator warns.

Whatever the editor produces, the server sanitizes HTML with an allowlist (no `<script>`, no `on*`
attributes, no `javascript:` URLs).

## Capabilities

`cms.pages.read`, `cms.pages.manage`, `cms.pages.delete`, `cms.pages.import`, `cms.pages.export`
(see [`registry/capabilities.yml`](../../registry/capabilities.yml)).
