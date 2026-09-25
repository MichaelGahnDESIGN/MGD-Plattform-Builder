# 0.5.1: AI-CMS-Pflichtfunktionen

Mit Version **0.5.1 Pre-Alpha** wird das MGD Project Platform System zu einem **AI-Agenten-gesteuerten CMS**: Claude Code, ChatGPT Codex und andere Coding-Agenten erstellen damit Websites für Spiele, Projekte und Plattformen – aus einem Briefing, einem Starter-Template und festen Pflichtfunktionen.

Pflichtfunktionen sind Funktionen, die **jedes** Projekt immer hat. Der Agent fragt nie *ob*, sondern nur *wie* sie eingerichtet werden.

| Pflichtfunktion | Wo im Backoffice | Profil-Abschnitt |
|---|---|---|
| Versionsnummern | Einstellungen → Versionsnummern | `versioning` |
| Release Notes (Timeline) | Einstellungen → Release Notes | `versioning.release_notes` |
| Credits | Einstellungen → Credits | `credits` |
| Rechtstexte / CMS-Seiten | Seiten | `cms` |
| Durchsuchbare Einstellungen | Einstellungen (Suche + Filter) | `settings` |
| Light- und Dark-Mode | Einstellungen → Darstellung | `appearance` |
| Design-Farben | Einstellungen → Design | `appearance.design_tokens` |
| Dateispeicherorte | Einstellungen → Dateispeicherorte | `file_locations` |
| Code-Editoren (optional) | Einstellungen → Code-Editoren | `code_editors` |

Das Briefing, die Templates und die Empfehlungen sind auf [[24-Briefing-Templates-und-Empfehlungen]] beschrieben.

---

## 1. Versionierung

Jedes Projekt startet mit **`0.0.1 Pre-Alpha`**. Die Foundation selbst steht auf **`0.5.1 Pre-Alpha`**.

```text
1.2.3 STATUS
│ │ └─ Patch: Patches und Updates bestehender Funktionen (Spiel, Editor, Plattform)
│ └─── Minor: neue Funktionen im Spiel, Editor oder in der Plattform
└───── Major: Versionsnummer ab der Releaseversion
```

Status: `Pre-Alpha`, `Alpha`, `Beta`, `Pre-Release`, `Release`, `Stable`, `Staging`, `Hotfix`, `LTS`, `Deprecated`.

### Eine Datei für alles: `version.json`

```json
{
  "version": "0.0.1",
  "status": "pre-alpha",
  "released_at": "2026-09-25",
  "sync_targets": ["VERSION", "package.json"]
}
```

Anwendungen lesen die Version zur Laufzeit aus dieser Datei. Andere Dateien werden nur synchronisiert:

```bash
mgd-platform version                     # zeigt "0.0.1 Pre-Alpha"
mgd-platform version --bump patch        # 0.0.2 + Sync von VERSION, package.json, ...
mgd-platform version --bump minor --status alpha
mgd-platform version --check             # schlägt fehl, wenn eine Kopie abweicht (CI)
mgd-platform version --bump patch --note "Neue Rangliste" --audience frontend,backoffice
```

### Wo die Version angezeigt wird

Im Admin-Backoffice gibt es immer die Einstellung **Versionsnummern**. Dort wird festgelegt:

* Anzeige an / aus, Status anzeigen
* Sichtbarkeit: öffentlich, privat (eingeloggt) oder beides
* Orte: Login, Einstellungen / Spieleinstellungen, Backoffice-Footer, öffentlicher Footer oder Header, öffentliche oder private Landingpage, Über-Seite

Standard: **unter dem Login und in den (Spiel-)Einstellungen**. Der Validator warnt, wenn diese beiden Orte fehlen. Der KI-Agent fragt die Orte und die Sichtbarkeit im Briefing ab.

```yaml
versioning:
  current: "0.0.1"
  status: "pre-alpha"
  display:
    enabled: true
    show_status: true
    audience: "both"
    locations: ["login", "game_settings", "settings", "backoffice_footer"]
```

## 2. Release Notes

Unter **Einstellungen → Release Notes** gibt es eine **Timeline**: zu jeder Versionsnummer und jedem Deploy lassen sich die Versions-Notes nachlesen.

* **Frontend:** nur Einträge mit Zielgruppe `frontend`
* **Editor / Admin / Moderation:** alle Einträge (Plattform, Spiel, Editor, API, ...) mit Filter

Quelle ist `release-notes.json`:

```json
{
  "entries": [
    {
      "version": "0.1.0",
      "status": "alpha",
      "date": "2026-10-01",
      "audience": ["frontend", "backoffice"],
      "type": "feature",
      "title": "Neue Rangliste",
      "items": ["Wöchentliche Rangliste", "Filter nach Freunden"]
    }
  ]
}
```

Zielgruppen: `frontend`, `backoffice`, `editor`, `platform`, `game`, `api`. Typen: `feature`, `patch`, `fix`, `security`, `breaking`, `deploy`.

Das Starter-Template importiert die Datei per Knopf „Aus release-notes.json synchronisieren“ oder per `php scripts/sync-release-notes.php`.

## 3. Credits

Unter Einstellungen gibt es immer den Menüpunkt **Credits** – öffentlich sichtbar und im Editor frei bearbeitbar wie die Rechtstexte.

**Zuerst: alle beteiligten Personen und ihre Rollen.** Danach eine Liste aller verwendeten KI-Systeme, Tools, Werkzeuge, Plugins, Bibliotheken, Frameworks, Schriften, Icons, Bilder, Sounds und Dienste. Jeder Eintrag hat:

* Logo / Symbol (**nur lokal eingebettet**)
* Name und Kategorie
* Beschreibung (wofür im Projekt genutzt)
* Anbieter-Informationen, wenn diese genannt werden müssen
* Links (Anbieter, Website, GitHub, Lizenztext)
* Tags für Lizenz, kommerzielle Freigabe, Namensnennungspflicht, „Lokal eingebettet“
* kommerzielle Nutzung (ja / nein / eingeschränkt / unbekannt), Version, Hinweise

> [!IMPORTANT]
> Schriften, Icons und Bibliotheken werden **immer lokal eingebettet** – keine Google-Fonts- oder Icon-CDNs. Der Validator warnt bei `credits.local_assets_only: false`.

## 4. Einstellungen: immer durchsuchbar und filterbar

Alle Einstellungen stehen in einer Registry (Schlüssel, Kategorie, Bezeichnung, Beschreibung, Suchbegriffe, Typ, Standardwert). Die Einstellungsseite hat:

* Kategoriemenü
* Suchfeld über Bezeichnung, Beschreibung, Suchbegriffe und Schlüssel
* Kategoriefilter
* Server-Fallback über `?q=` und `?category=` – funktioniert auch ohne JavaScript

`settings.searchable: false` oder `settings.filterable: false` ist ein **Fehler**.

## 5. CMS-Seiten und Rechtstexte

Rechtliche Seiten sind normale CMS-Seiten vom Typ `legal` und damit **bearbeitbar, löschbar, neu anlegbar, mit Revisionen, Export und Import**:

Kontakt, Impressum, AGB, Datenschutz, EU-Cookies, Texte der Cookie-Box, Zahlung, Versand, Widerrufsbelehrung, Widerrufs-Button-Text, Jugendschutz, Barrierefreiheit, AI-Philosophie und Credits.

Welche Seiten Pflicht sind, hängt vom Profil ab:

| Immer | Bei Shop/Billing | Bei Spiel/Community/Creator |
|---|---|---|
| Kontakt, Impressum, Datenschutz, Cookies, Cookie-Box-Text, Barrierefreiheit, AI-Philosophie, Credits | AGB, Zahlung, Versand, Widerrufsbelehrung, Widerrufs-Button-Text | Jugendschutz |

Lebenszyklus:

```text
anlegen → Revision 1
bearbeiten → neue Revision (Autor, Zeit, Notiz)
Revision wiederherstellen → neue Revision mit altem Inhalt
löschen → Papierkorb → wiederherstellen oder endgültig löschen (nur Admin, mit Bestätigung, Audit)
```

Export als JSON (alle oder ausgewählte Seiten, optional mit Revisionen), Import legt neue Seiten oder neue Revisionen an. Importiertes HTML wird **immer** serverseitig bereinigt.

> [!CAUTION]
> Mitgelieferte Rechtstexte sind Platzhalter („Platzhalter – rechtlich prüfen lassen“). Die Foundation ist keine Rechtsberatung.

### Editor-Abfrage: welcher Editor und lokal oder CDN?

Bei der Einrichtung fragt der Agent den Editor ab (TinyMCE, GrapesJS, Quill, Editor.js, Markdown, reines Textfeld) **und ob er lokal in der App eingebunden oder von einem CDN geladen wird**.

* **lokal (empfohlen):** Dateien unter `public/assets/vendor/<editor>/`, keine Drittanbieter-Anfrage
* **CDN:** Anbieter muss in Datenschutzerklärung und Credits stehen – der Validator warnt

Lizenzhinweis: TinyMCE 7 steht unter GPL-2.0-or-later oder kommerzieller Lizenz; GrapesJS und Quill unter BSD-3-Clause.

## 6. Dateispeicherorte und Code-Editoren

**Dateispeicherorte:** Einstellungsseite mit allen wichtigen Pfaden (Webroot, Uploads, Backups, Logs, eigene Assets, `version.json`, `release-notes.json`, Konfigurationsdatei, lokale Editor-Dateien) inklusive Status „vorhanden / beschreibbar“. Die Pfade stehen in der Konfigurationsdatei und sind **nicht** über das Web änderbar.

**Code-Editoren:** Der Assistent fragt, ob es im Admin-Backoffice Editoren für CSS, JS, PHP usw. geben soll.

| Sprache | Datei | Schutz |
|---|---|---|
| CSS / JS | `assets/custom/custom.css`, `custom.js` | nur Admin, CSRF, Backup vor dem Speichern, Audit |
| PHP | eine Hook-Datei | zusätzlich `allow_php_editor = true` in der Konfigurationsdatei |

PHP-Bearbeitung ohne `code_editors.php_requires_config_flag: true` ist ein **Fehler**.

## 7. Light- und Dark-Mode

Jedes Template hat **immer eine Light- und eine Dark-Version**. Unter **Einstellungen → Darstellung**:

* Umschalter anzeigen: ja / nein
* Wo anzeigen: Header, Footer, Login, Einstellungen, schwebend
* Standard: System, Light oder Dark
* Nutzerwahl erlauben (wird im Browser gespeichert)

`appearance.modes` muss `light` **und** `dark` enthalten.

## 8. Design

Unter **Einstellungen → Design** lassen sich die Farben einfach anpassen – getrennt für Light und Dark:

`primary`, `primary_contrast`, `secondary`, `accent`, `success`, `warning`, `danger`, `info`, `background`, `surface`, `text`, `muted`, `border`, dazu Eckenradius und eine lokal eingebettete Schrift. Mit Vorschau und „Auf Standard zurücksetzen“. Farben werden streng als `#rrggbb` geprüft und als CSS-Variablen ausgegeben.

---

## So prüft der Validator

| Situation | Ergebnis |
|---|---|
| Abschnitt fehlt (z. B. `credits`) | Warnung – ältere Profile bleiben gültig |
| Pflichtfunktion ausdrücklich deaktiviert | Fehler |
| Nur Light oder nur Dark | Fehler |
| Editor per CDN, Assets nicht lokal | Warnung |
| PHP-Editor ohne Konfigurationsschalter | Fehler |
| Rechtsseiten fehlen | Warnung mit Liste |
| Briefing nicht abgeschlossen | Warnung |

```bash
mgd-platform validate
mgd-platform doctor
mgd-platform audit --write
```

## Neue Capabilities

```text
cms.pages.read          cms.pages.manage        cms.pages.delete
cms.pages.import        cms.pages.export        release-notes.manage
credits.manage          settings.read           settings.manage
design.manage           code-editor.assets.manage
code-editor.server.manage
```

Technische Tiefendokumentation (Englisch): [WIKI/18-CMS](https://github.com/MichaelGahnDESIGN/Projekt-Plattform-System/tree/main/WIKI/18-CMS)
