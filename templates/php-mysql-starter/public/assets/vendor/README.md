# Editor-Bibliotheken (lokal eingebettet)

Hier werden CMS-Editoren **lokal** abgelegt. Die Bibliotheken sind **nicht** im Starter enthalten –
bitte selbst von der offiziellen Quelle herunterladen, Lizenz prüfen und per FTP hochladen.
Lokale Einbindung ist datenschutzfreundlicher als ein CDN (keine IP-Übertragung an Dritte).

Auswahl im Backoffice: **Einstellungen → CMS-Editor** (`cms_editor.editor`, `cms_editor.delivery`).
Fehlen die Dateien, fällt das Backoffice automatisch auf ein einfaches Textfeld zurück.
HTML wird **immer** serverseitig bereinigt – unabhängig vom Editor.

| Editor   | Erwartete Pfade (relativ zu `public/assets/`)                         | Lizenz |
|----------|------------------------------------------------------------------------|--------|
| TinyMCE  | `vendor/tinymce/tinymce.min.js` (inkl. Ordner `skins/`, `themes/`, `icons/`, `models/`, `plugins/`) | TinyMCE 7: GPL-2.0-or-later **oder** kommerzielle Lizenz. Der Starter setzt `license_key: 'gpl'` – bei proprietärer Nutzung kommerzielle Lizenz erwerben. |
| GrapesJS | `vendor/grapesjs/grapes.min.js`, `vendor/grapesjs/css/grapes.min.css`  | BSD-3-Clause |
| Quill    | `vendor/quill/quill.js`, `vendor/quill/quill.snow.css`                  | BSD-3-Clause |
| Markdown | keine Dateien nötig (serverseitiger Renderer)                          | – |
| Textfeld | keine Dateien nötig                                                     | – |

## Hinweise

- Nach dem Einbinden einen Eintrag unter **Credits → Komponenten** anlegen (Lizenz, Anbieter, Links, „Lokal eingebettet“).
- CDN-Auslieferung nur mit HTTPS-URL; die Herkunft wird automatisch in die Content-Security-Policy des Editors aufgenommen.
- GrapesJS: CSS-Stile aus dem Editor werden nicht gespeichert (der Sanitizer entfernt `style`); gestalte Inhalte über Klassen in `custom.css`.
- Ordner `vendor/*` ist per `.gitignore` ausgeschlossen, damit keine fremden Bibliotheken versehentlich versioniert werden.
