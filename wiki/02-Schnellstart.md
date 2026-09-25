# Schnellstart

Diese Seite beschreibt den empfohlenen Einstieg für neue und bestehende Projekte.

## Empfohlener Weg mit der CLI

Für neue Projekte ist die CLI der einfachste Einstieg.

Repository klonen und Abhängigkeiten installieren:

```bash
git clone https://github.com/MichaelGahnDESIGN/MGD-Plattform-Builder.git
cd MGD-Plattform-Builder
npm install
npm link
```

Danach kann ein neues Projekt vorbereitet werden:

```bash
mgd-platform init --preset general --target ../mein-projekt
```

Verfügbare Presets sind:

```text
general
game
community
creator
ecommerce
```

Anschließend:

```bash
cd ../mein-projekt
mgd-platform doctor
mgd-platform audit --write
```

Damit erhältst du sofort eine Grundstruktur und einen ersten Gap Report.

Die komplette CLI-Dokumentation findest du unter [[18-CLI-Validator-und-Automatisierung]].

## Was `init` vorbereitet

Die CLI legt je nach Projekt unter anderem folgende Foundation-Dateien an:

```text
MGD_PLATFORM.yml
AGENTS.md
CLAUDE.md
FEATURE-GOVERNANCE.md
.mgd/evidence/
```

Das Projektprofil wird anschließend an das echte Projekt angepasst.

## Manueller Einstieg ohne CLI

Die Foundation kann weiterhin vollständig manuell verwendet werden.

Kopiere zunächst das Projektprofil:

```bash
cp templates/MGD_PLATFORM.example.yml MGD_PLATFORM.yml
```

Übernimm anschließend die Agentenregeln:

```bash
cp templates/AGENTS.md ./AGENTS.md
cp templates/CLAUDE.md ./CLAUDE.md
```

Danach werden Zielmarkt, Sprachen, aktivierte Features, Infrastruktur und Release-Gates im Projektprofil festgelegt.

## Empfohlener Ablauf

1. Projektprofil erstellen oder mit `mgd-platform init` erzeugen.
2. Passenden Domain Pack auswählen.
3. `mgd-platform validate` ausführen.
4. `mgd-platform doctor` ausführen.
5. Daten, Rollen, externe Dienste und Risiken inventarisieren.
6. Architektur und Berechtigungen dokumentieren.
7. Backup- und Staging-Strategie festlegen.
8. `mgd-platform audit --write` ausführen.
9. Release-Gates definieren.
10. Erst dann die kleinste sinnvolle Funktion bauen.

## Vor einem Release

Die Foundation kann Release-Nachweise maschinell kontrollieren:

```bash
mgd-platform validate
mgd-platform audit
mgd-platform release-check
```

Erst wenn die notwendigen Evidence-Dateien für die im Projektprofil definierten Gates vorliegen, meldet der Release Check den Zustand `READY`.

## Bestehendes Projekt

Ein bestehendes Projekt wird nicht neu geschrieben, nur um der Foundation zu entsprechen. Zuerst wird inventarisiert, was bereits existiert.

Erfasse mindestens Tech-Stack, Daten, Rollen, Umgebungen, Backups, Deployment, Dokumentation, externe Dienste und bekannte Risiken. Danach werden Lücken priorisiert und schrittweise geschlossen.

Siehe dazu [[13-Migration-bestehender-Projekte]].

## Erster Agenten-Prompt

```text
Lies MGD_PLATFORM.yml und AGENTS.md.

Führe mgd-platform doctor und mgd-platform audit aus.
Ändere noch keinen Code.

Erstelle aus den Findings einen priorisierten Plan.
Nenne besonders alle CRITICAL- und HIGH-Findings
sowie alle Release-Blocker.
```

## Wichtige Regel

Die Foundation ist modular. Ein internes Tool braucht nicht automatisch Moderation, Billing oder öffentliche Profile. Umgekehrt benötigt eine öffentliche Plattform möglicherweise deutlich mehr Schutzmaßnahmen.

Weiter: [[03-Projektprofil-und-Konfiguration]] · [[18-CLI-Validator-und-Automatisierung]] · [[10-Module-und-Domain-Packs]]
