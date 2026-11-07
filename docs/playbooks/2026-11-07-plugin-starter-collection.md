# Playbook · MGD Plugin Starter Collection

## Ziel und Grenzen

- Ergebnis: Fünf per CLI kopierbare Starter für WordPress, WooCommerce, JTL-Shop 5, Shopware 6 und Shopify; jeder beschreibt Architektur, Datenschutz, sichere Standardgrenzen sowie lokale Light-/Dark-Varianten.
- Umfang / Freigaben: Beauftragte lokale Foundation-Änderung. Kein Marketplace-Upload, keine Shop-Installation, kein GitHub-Release und keine Live-Bereitstellung.
- Geschützt: Bestehender PHP/MySQL-Starter, Lizenzschutz, Versionierungsprozess und bestehende Projektdateien.
- Vorhandene Todo-/Doku-Verweise: Keine `TODO.html`/`.todo-config` im Repository. `WIKI/18-CMS/TEMPLATES.md` ist die kanonische Template-Dokumentation.

## Grundlinie

- Datum, Repository, Branch und Ausgangscommit: 2026-11-07, `MGD-Plattform-Builder`, `feat/notification-center-skill`, `99cf416`.
- Betroffene Funktion / Tests: `mgd-platform template list|check|create`; `npm run check:templates`, gezielte CLI-Kopierprüfung und Dokumentationslinks.
- Lokal / GitHub / Release / Live: Lokaler Stand geprüft; GitHub-, Release- und Live-Stand sind nicht Teil dieses Auftrags.
- Rückfallpunkt: Commit `99cf416` vor der Änderung.

## Ausführung in Reihenfolge

| ID | Schritt und betroffene Dateien | Voraussetzung | Erfolgskriterium und Prüfung | Rückfall | Status | Beleg |
| --- | --- | --- | --- | --- | --- | --- |
| P1 | Starter-Metadaten und Schutzgrenzen definieren: `templates/*/template.json`, `README.md`, `AGENTS.md`, `docs/*` | — | Fünf klar zuordenbare Plattform-Starter ohne Secrets oder Produktionsdaten | Git-Rückkehr zu `99cf416` | geprüft | Direkte Vertragsprüfung und Kopie jedes Starters erfolgreich |
| P2 | CLI-/Template-Vertrag und Dokumentation erweitern: `src/lib/templates.js`, `test/templates.test.js`, `templates/README.md`, `WIKI/18-CMS/TEMPLATES.md` | P1 | `template check` akzeptiert nur vollständige Plattformstarter und CLI listet sie | Git-Rückkehr zu `99cf416` | geprüft | `node ./bin/mgd-platform.js template check`: 6 Templates grün |
| P3 | Sicherheits- und Funktionsprüfungen, Wissen aktualisieren | P1, P2 | gezielte Tests, Beispielkopien, Linkprüfung; Grenzen dokumentiert | Revert des lokalen Commits | geprüft | Template-CLI, Kopiervorgang, Vertrags-/Subset-Test, PHP-/Node-Syntax, Version und Markdown-Links grün. Die volle Schema-/Gesamtsuite ist wegen unvollständig extrahierter lokaler npm-Abhängigkeiten nicht gelaufen. |
| P4 | Commit und Pull Request | P3 | nachvollziehbarer Commit und PR, sofern Remote-Zugriff verfügbar | Commit-Revert | geprüft | Commit `6a14c04`, Branch `codex/plugin-starter-collection-2026-11-07`, Pull Request #30. GitHub meldet derzeit keine konfigurierten Checks für den Branch. |

## Planänderungen

| Datum | Betroffene IDs | Neuer Weg und Grund | Auswirkung auf Kriterien, Reihenfolge und Freigaben |
| --- | --- | --- | --- |
| — | — | Noch keine | — |

## Fortsetzung und Abschluss

- Aktueller Schritt / nächster ausführbarer Schritt: Kein weiterer Umsetzungsschritt; PR-Review oder Marketplace-Integration nur bei neuem Auftrag.
- Abgleich: Version auf `0.8.0 Pre-Alpha` über den CLI-Befehl aktualisiert; Änderungen betreffen nur die Template-Foundation.
- Lokal geprüft: Regeln, CLI-Vertrag, existierender Template-Standard und E-Commerce-/Security-Leitlinien gelesen; gezielte Prüfungen dokumentiert.
- GitHub / Release / Live: Pull Request #30 offen; kein Release und keine Live-Bereitstellung beauftragt oder durchgeführt.
- Restpunkte / Blockaden: Marketplace-spezifische Installationstests brauchen später echte Zielumgebungen. Die vollständige Node-Test-/Schema-Suite muss auf einer intakten lokalen Abhängigkeitsinstallation oder in CI wiederholt werden.
- Gesamtergebnis: Offen bis P1–P4 gegen die unveränderten Kriterien geprüft sind.
