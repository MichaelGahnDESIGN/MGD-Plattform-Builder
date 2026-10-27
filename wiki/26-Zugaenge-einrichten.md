# Zugänge sicher übernehmen und testen

Der allgemeine Skill **zugang** funktioniert in ChatGPT Codex und Claude Code, unabhängig von
WordPress, Hostinganbieter, Shop oder Programmiersprache. Du legst eine lokale Markdown-Datei mit
Zugangsdaten ins neue oder vorhandene Projekt und beauftragst den Ablauf mit dieser Datei.

## Installation

Repository klonen, dann aus dessen Hauptordner mit Python 3.9 oder neuer:

```sh
python3 scripts/install-zugang.py --agent both
```

Das installiert den eigenständigen Skill für alle lokalen Projekte nach `~/.agents/skills/zugang`
(Codex) und `~/.claude/skills/zugang` (Claude Code). Die Plattform-CLI ist dafür nicht erforderlich.
Für Codex-Installationen, die weiterhin `~/.codex/skills` verwenden, stattdessen
`python3 scripts/install-zugang.py --agent codex-legacy` ausführen. Nicht beide Codex-Pfade zugleich
installieren, wenn der Client beide durchsucht: sonst kann der Skill doppelt erscheinen.

Nur für ein bestimmtes vorhandenes Projekt:

```sh
python3 scripts/install-zugang.py --agent both --project /pfad/zum/projekt
```

Unter Windows gegebenenfalls `py -3` statt `python3` verwenden. Der Installer kopiert nur die
festgelegten öffentlichen Skill-Dateien, keine Projektzugangsdaten. Vorhandene Installationen
werden nicht überschrieben. Für Updates zuerst eigene Anpassungen vergleichen und die alte
Installation bewusst außerhalb der Skill-Suchpfade sichern; dann neu installieren. Rücknahme:
ausschließlich die installierten `zugang`-Ordner entfernen. Keine Konten oder Berechtigungen
werden durch die Installation eingerichtet oder verändert.

Nach Installation gegebenenfalls einen neuen Chat starten bzw. den Client neu laden.

## Aufruf

Claude Code:

```text
/zugang private/zugaenge.md
```

Codex:

```text
$zugang private/zugaenge.md
```

Alternativ den Skill im jeweiligen Selektor auswählen. `/zugang` ist der Claude-Befehl;
Codex verwendet die Skill-Auswahl bzw. `$zugang`. Alle Anleitungen sind normale Markdown-Dateien,
keine proprietären APIs sind für den Ablauf erforderlich. Eine gewöhnliche ChatGPT-Sitzung ohne
lokalen Dateizugriff und Ausführungswerkzeuge kann nur anleiten, keinen lokalen Test ausführen.
Persönlich installierte Skills sind nicht automatisch in Cloud-Sitzungen verfügbar.

Die dokumentierten Installationsorte und Aufrufe folgen den offiziellen Anleitungen von
[OpenAI](https://developers.openai.com/codex/skills) und
[Claude Code](https://code.claude.com/docs/en/skills) (geprüft am 17. Oktober 2026).

## Ablauf

1. Quelle, Projektregeln und vorhandene Schutz-/Deployment-Verfahren prüfen. Nur fehlende Angaben erfragen.
2. Quelldatei und Ziel aus Git sowie dem vorhandenen Upload-/Build-Prozess ausschließen.
3. `.env` sicher erstellen. Existiert sie, unverändert bewahren und `.env.access.local` verwenden;
   bestehen beide, notwendige Aktualisierung klären. Kein automatisches Überschreiben.
4. Original erhalten, Zugriff beschränken und Schreib-/Löschschutz abhängig von Betriebssystem und
   Dateisystem setzen sowie tatsächlich prüfen. Eine gezielte Entsperranleitung hinterlassen.
5. Minimalen authentifizierten Zugriff testen, Sitzungen aufräumen und Ergebnisse ohne Geheimnisse melden.

Der Agent liest Kennwörter direkt im lokalen Prozess ein, ohne sie in Tool-Aufrufen, Chat oder
Logs auszugeben. Er legt keine neuen SSH-Schlüssel, Tokens oder Konten ungefragt an. MFA/Captcha
übernimmst du selbst. Eine Wartungsseite wird nicht freigegeben, ein Sicherheitsplugin nicht deaktiviert.

## Schutzwirkung und Grenzen

| Schutz | Was er leistet |
|---|---|
| `.gitignore` | Verhindert normales Hinzufügen zu Git, entfernt aber keine bereits versionierten Daten |
| Upload-/Build-Regeln | Schützen nur den konkret eingebundenen und geprüften Übertragungsweg |
| Dateirechte und Sperren | Erschweren versehentliches Bearbeiten/Löschen; Wirkung unter macOS, Linux, Windows und NAS unterschiedlich |
| Verschlüsselte Sicherung/Passwortmanager | Separate Maßnahme zur Wiederherstellung; nicht durch Git oder Dateisperren ersetzt |

Lokale Klartextdateien sind nicht verschlüsselt. Der Skill erstellt keine zusätzlichen Klartext-Backups.
Manuelle FTP-Uploads, ZIP-Dateien und Cloud-Synchronisierung beachten `.gitignore` nicht automatisch.
Eine genaue Grenze ist hilfreicher als die Behauptung, die Dateien seien gegen alles geschützt.

Ein erfolgreiches HTTP-Ergebnis beweist keinen Login. Der Bericht unterscheidet Anmeldung,
API-Zugang und öffentliche Erreichbarkeit. Beispielsweise kann ein Admin-Login funktionieren,
während ein Sicherheitsplugin die API blockiert. Dann bleibt das API-Kennwort unbestätigt.

## Bestehende Installationen und Zugang-UI

Der Skill bleibt unter `platform/skills/zugang/`. Bisherige `.env.access.local`-Dateien bleiben gültig;
es gibt keine automatische Datenmigration. Die neue Anleitung ersetzt den früheren obligatorischen
Fragenkatalog, die automatische SSH-Key-Erzeugung und den allgemeinen Serverlog-Lesedurchlauf
zugunsten des begrenzten Zugangstests.

Die separate [Zugang-UI](https://github.com/MichaelGahnDESIGN/MGD-Plattform-Builder/tree/main/tools/zugang-ui) bleibt ein Werkzeug für den bisherigen
SSH-Key-Workflow. Sie wurde nicht auf diesen allgemeinen Skill-Ablauf umgebaut und bietet nicht
automatisch dessen zusätzlichen Schreib-/Lösch- oder Upload-Schutz. Der Skill setzt sie nicht voraus.

## Weitergabe und Lizenz

Der Skill einschließlich seiner Referenzen und des Installers ist MIT-lizenziert und darf auch
von anderen und in eigenen Projekten genutzt werden. Beim Kopieren den beiliegenden Lizenztext
mitnehmen. Echte Zugangsdateien gehören niemals in das öffentliche Skill-Repository.
