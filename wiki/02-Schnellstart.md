# Schnellstart

Diese Seite beschreibt den empfohlenen Einstieg für neue und bestehende Projekte.

## Neues Projekt

Kopiere zunächst das Projektprofil in dein Repository:

```bash
cp templates/MGD_PLATFORM.example.yml MGD_PLATFORM.yml
```

Übernimm anschließend die Agentenregeln:

```bash
cp templates/AGENTS.md ./AGENTS.md
cp templates/CLAUDE.md ./CLAUDE.md
```

Danach werden Zielmarkt, Sprachen, aktivierte Features, Infrastruktur und Release-Gates im Projektprofil festgelegt.

Ein sinnvoller erster Ablauf ist:

1. Projektprofil ausfüllen.
2. Passenden Domain Pack auswählen.
3. Daten, Rollen, externe Dienste und Risiken inventarisieren.
4. Architektur und Berechtigungen dokumentieren.
5. Backup- und Staging-Strategie festlegen.
6. Foundation-Audit durchführen.
7. Release-Gates definieren.
8. Erst dann die kleinste sinnvolle Funktion bauen.

## Bestehendes Projekt

Ein bestehendes Projekt wird nicht neu geschrieben, nur um der Foundation zu entsprechen. Zuerst wird inventarisiert, was bereits existiert.

Erfasse mindestens Tech-Stack, Daten, Rollen, Umgebungen, Backups, Deployment, Dokumentation, externe Dienste und bekannte Risiken. Danach werden Lücken priorisiert und schrittweise geschlossen.

Siehe dazu [[13-Migration-bestehender-Projekte]].

## Erster Agenten-Prompt

```text
Lies MGD_PLATFORM.yml, AGENTS.md und die Dokumentation des
MGD Project Platform Systems.

Ändere noch keinen Code.

Erstelle zuerst einen Gap-Report für:
Architektur, Daten, Berechtigungen, Datenschutz, Sicherheit,
Betrieb, Dokumentation und Release-Gates.

Bewerte jede Lücke als kritisch, hoch, mittel oder niedrig.
Begründe die Einstufung und nenne die betroffenen Dateien.
```

## Wichtige Regel

Die Foundation ist modular. Ein internes Tool braucht nicht automatisch Moderation, Billing oder öffentliche Profile. Umgekehrt benötigt eine öffentliche Plattform möglicherweise deutlich mehr Schutzmaßnahmen.

Weiter: [[03-Projektprofil-und-Konfiguration]] · [[10-Module-und-Domain-Packs]]