---
name: mgd-system-resources
description: Erfasst und überwacht die technische Basis von MGD-Projekten, prüft Abhängigkeiten und Supportstände und bereitet kontrollierte Updates vor. Für Ressourcen-Updater, Software-Stack, Sicherheitsmeldungen, neue Projektregistrierung und die geplante tägliche Prüfung verwenden.
---

# MGD System- und Abhängigkeitsmanager

Nutze den vorhandenen Manager statt Paketmanager oder Schwachstellenabfragen neu zu bauen. CLI: `mgd-resources` beziehungsweise `mgd-platform resources`. Die privaten lokalen Wurzeln stehen standardmäßig unter `~/.config/mgd/resources.json`; diese Datei und Berichte nie in öffentliche Repositories übernehmen.

## Regelbetrieb

Neue, beauftragte MGD-Projekte mit `register <Projektordner>` aufnehmen. Unter einem bereits registrierten Projektordner werden neue Repositories automatisch entdeckt. Andere Kunden- und Privatordner nicht ohne Auftrag hinzufügen.

`scan --config <Registrierung> --output <privater Berichtordner> --online --github --host --track` führt die lesende Prüfung aus. Exitcode 2 bedeutet Handlungsbedarf und ist kein Grund, Prüfungen zu umgehen. JSON-Bericht und sichtbare Abdeckung auswerten; bei `changed: false` und unveränderter Fehlerlage bleibt eine geplante Prüfung still. Berichte ohne vorherige Änderungserkennung oder mit unbekannten Quellen sind keine Freigabe für Updates.

## Auswertung

- Deklarationen, Lockdateien, Scannerimporte und beobachtete Laufzeiten unterscheiden. Keine produktive Installation aus einem Docker-Tag oder einer Composer-Anforderung ableiten.
- OSV prüft nur bestätigte öffentliche Repositories und Pakete mit öffentlichem Herkunftsnachweis. Private Inventare werden bei eingerichteter, per SHA-256 festgelegter OSV-Laufzeit ausschließlich offline gegen separat aktualisierte öffentliche Datenbanken geprüft. Bei Composer muss die Herkunft zuvor aus öffentlichen Metadaten bestätigt sein; unbekannte Quellen bleiben ungeprüft. Niemals private Paketnamen zur Herkunftsklärung extern abfragen. Datenbanken nach 24 Stunden prüfen; über 72 Stunden alte oder unvollständige Stände bestätigen keinen aktuellen Befund. GitHub-Sicherheitsmeldungen beziehen sich auf den Default-Branch, nicht automatisch auf jede lokale Kopie.
- Supportdaten von endoflife.date sind ein Drittanbieter-Aggregat. Eine neuere Referenzversion beweist keine offene Lücke in einem Provider-Backport. Fehlende, abgelaufene oder nicht passende Beobachtungen offen nennen.
- Zusätzliche Ökosysteme und Infrastruktur über CycloneDX-SBOM, Trivy-Berichte und datensparsame `resources.observations.json` integrieren. Keine `.env`, Tokens, Kundendaten oder Container-Umgebungsvariablen einsammeln. Scanimporte sind Nachweise aus einer anderen Quelle, keine selbst ausgeführten Scans.

## Updatevorbereitung

`github` zeigt zunächst einen Plan; `--apply` ist nur innerhalb des beauftragten Eigentümers erlaubt. Bestehende Dependabot-Regeln und Secret-Referenzen erhalten. Fehlende GitHub-Rechte über die normale Anmeldung klären, nicht umgehen. Nach jeder tatsächlich erstellten PR deren URL am aktuellen Codex-Chat anhängen, sofern das entsprechende Werkzeug verfügbar ist.

Die Policy erlaubt keine pauschale Installation. Ein automatisierbares Sicherheitspatch braucht eine explizite Repository-Freigabe, vertrauenswürdige Quelle, Lockdatei, verpflichtende erfolgreiche Tests am aktuellen Commit, Sicherung und geprüften Rückfall. Majorwechsel, Server-/Datenbankänderungen, Neustarts, Zahlungen und Authentifizierung sind getrennte Vorgänge. Keine Projekt-Skripte oder Installationshaken zur bloßen Bestandsaufnahme ausführen.

Die tägliche Automation darf Prüfungen und freigegebene Vorbereitung erledigen. Sie erteilt keine neue Live-Freigabe, aktiviert keine Plugins und installiert keine Serverpakete. Nur neue relevante Funde, geänderte Prüflücken, fehlgeschlagene Tests oder nötige Entscheidungen melden; keine täglichen „alles unverändert“-Nachrichten.

Die technische Quelle liegt im MGD-Plattform-Builder unter `src/resources/`; Bedienung und Grenzen stehen in `WIKI/06-OPERATIONS/RESOURCE-MANAGER.md` desselben Repositorys.
