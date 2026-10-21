# 0.7.0: Pflicht-Begleiter – Living Documentation, Graphify und Todo

Ab **0.7.0 Pre-Alpha** bringt jedes Projekt, das mit dem MGD-Plattform-Builder entsteht oder betreut wird, **drei Begleiter** mit. Sie werden zusammen mit dem Projekt installiert und danach bei **jeder Änderung** gepflegt – von Menschen und von KI-Agenten. Es geht nie um die Frage *ob*, sondern nur *wo* sie installiert werden.

## Kurzfassung

| Begleiter | Verantwortet | Ort im Projekt | Skill / Werkzeug |
|---|---|---|---|
| [Living Documentation](https://github.com/MichaelGahnDESIGN/MGD_Living-Documentation) | den **Stand**: bestätigte Fakten, Entscheidungen, offene Punkte, Risiken, Nachweise | `DOKUMENTATION/` | `living-documentation` |
| [Graphify](https://github.com/Graphify-Labs/graphify) | die **Karte**: was existiert und wovon es abhängt (maschinell erzeugt) | `graphify-out/` | `graphify` |
| [Todo](https://github.com/MichaelGahnDESIGN/MGD_Todo_SKILL) | die **Arbeit**: Aufgaben und ihr Status | `TODO/TODO.html` | `todo` |

Prinzip: **jede Aussage hat genau einen Besitzer.** Wer wissen will, *was gilt*, liest die Dokumentation. Wer wissen will, *was woran hängt*, fragt den Graphen. Wer wissen will, *was zu tun ist*, öffnet die Todo-Liste. Verlinken statt kopieren.

## Graphify ist Teil der Living Documentation

Die Living Documentation beschreibt den belegbaren Ist-Zustand. Graphify liefert dafür die Karte:

- Die Dokumentation hat einen Abschnitt **„Karte (Graphify)“** und verweist auf `graphify-out/GRAPH_REPORT.md` und `graphify-out/graph.html`.
- **Architekturaussagen nennen ihren Beleg**, zum Beispiel „`graphify explain "Login"`, 2026-10-22“.
- **Fragen gehen zuerst an den Graphen** (`graphify query`, `graphify path`, `graphify explain`), erst danach wird der Quellbaum durchsucht.
- **`graphify update .` ist fester Teil jedes Arbeitszyklus.** Optionale Git-Hooks (`--hooks`) erledigen es zusätzlich nach jedem Commit.
- Der Graph ist **abgeleitet**: nie von Hand ändern, nie als Entscheidung oder Statusbericht zitieren.

Einordnung nach den Rollen der Dokumentationsgovernance:

| Rolle | Wer füllt sie |
|---|---|
| Konstitution (Regeln) | `AGENTS.md`, `CLAUDE.md` |
| **Karte** (was existiert, wo) | **Graphify** (`graphify-out/`) und die Navigation in `DOKUMENTATION/README.md` |
| Status (Gesundheit, Blocker) | „Aktueller Stand“ in `DOKUMENTATION/` und `TODO/TODO.html` |
| Historie (Entscheidungen, Änderungen) | „Entscheidungen“ in `DOKUMENTATION/`, `CHANGELOG.md`, `release-notes.json` |

## Updates an einer Stelle

Du pflegst die Versionen dort, wo sie entstehen: Ein neues Release von Todo, Living Documentation oder Graphify im jeweiligen Repo genügt. Jedes danach angelegte Projekt installiert automatisch das neueste Release; im Builder musst du nichts anpassen (die Registry hält nur den getesteten Mindeststand). Vorhandene Installationen werden nie überschrieben: Hast du einen Skill lokal aktualisiert (zum Beispiel `~/.claude/skills/mgd-todo`), nutzt Claude Code in neuen Projekten genau diesen.

## Installation

```bash
mgd-platform companions status ./mein-projekt        # was ist installiert, was fehlt?
mgd-platform companions install ./mein-projekt       # Trockenlauf: zeigt den Plan, ändert nichts
mgd-platform companions install ./mein-projekt --scope project --graphify user --yes
```

Für ein neues Projekt in einem Lauf: `mgd-platform init --target ./mein-projekt --companions --scope project --graphify user`. Auch hier ist `--scope` Pflicht.

**Zwei Fragen an den Nutzer – ohne Antwort installiert der Befehl nichts:**

| Frage | Option | Bedeutung |
|---|---|---|
| Wo sollen die Skills liegen? | `--scope project` | `.claude/skills` und `.agents/skills` nur in diesem Projekt |
| | `--scope user` | `~/.claude/skills` und `~/.agents/skills` für alle Projekte |
| Wie wird die Graphify-CLI installiert? | `--graphify user` | `uv tool install` (oder `pipx`), überall verfügbar |
| | `--graphify project` | projektlokale Python-Umgebung `.mgd/graphify-venv/` (Python 3.10+) |
| | `--graphify skip` | später – `companions status`, `doctor` und `release-check` melden es weiter als fehlend |

Mit `--agent claude` oder `--agent codex` lässt sich ein Client ausschließen.

### Was der Befehl tut

1. Holt jeden Skill-Begleiter aus dem **neuesten Release** seines GitHub-Repos (höchster Tag `vX.Y.Z`, per `git ls-remote` beim Installieren ermittelt, nie unter dem getesteten Mindeststand der Registry) in einen temporären Ordner. Der Clone muss zum abgefragten Commit passen, sonst bricht die Installation ab. Kopiert werden nur reguläre Dateien (keine Symlinks, kein `.git`, keine `.env`-Dateien, Größenlimit). Mit `--pinned` wird stattdessen genau das getestete Release aus der Registry installiert (reproduzierbar, z. B. in CI). Der Trockenlauf fragt GitHub vorab, welches Release installiert würde, und zeigt es an.
2. Installiert die Skills für Claude Code und Codex. **Vorhandene Installationen werden nie überschrieben**, auch nicht, wenn sie unter dem alten Namen `mgd-todo` liegen.
3. Legt `DOKUMENTATION/README.md` (Gerüst mit Stand, Karte, Entscheidungen, offenen Punkten, Risiken, Verifikation) und `TODO/TODO.html` samt `TODO/.todo-config` an, sofern sie fehlen. Liegt die Dokumentation schon woanders (zum Beispiel `docs/`), `companions.living_documentation.path` setzen – dann entsteht kein zweiter Ordner.
4. Installiert bzw. aktualisiert die Graphify-CLI auf das neueste Release (`graphifyy>=0.9.65`; mit `--pinned` genau `graphifyy==0.9.93`; jede installierte Kopie ab 0.9.65 wird akzeptiert; Python 3.10+), schreibt `.graphifyignore` und `.gitignore`-Einträge und baut den ersten Graphen mit `graphify update .`.
5. **Optional, standardmäßig aus:** `--hooks` installiert Graphifys Post-Commit- und Post-Checkout-Hooks samt Merge-Treiber für `graph.json` (Git-Konfiguration und `.gitattributes`). Die Hooks führen nach Commits und Checkouts Python im Repository-Wurzelordner aus; ein ausgechecktes Branch könnte dort eine Datei mitbringen, die ein Python-Modul überlagert. Deshalb nur in Repositories einschalten, denen du vertraust.

Der optionale `/graphify`-Agent-Skill wird **nicht** installiert: `graphify install --platform claude` schreibt in die globale `~/.claude/CLAUDE.md`. Wer ihn möchte, führt den Befehl selbst aus.

### Was nicht in den Graphen gehört

Die installierten Agenten-Skills (`.claude/`, `.agents/`, `.codex/`) und `TODO/` stehen in `.graphifyignore`. Ohne diesen Eintrag bestanden in einem Test **256 von 283 Knoten** aus Skill-Dateien und nicht aus dem Projekt. `graphify-out/cache/` steht in `.gitignore` (reproduzierbar); `graph.json` und `GRAPH_REPORT.md` werden mit dem Projekt versioniert.

## Arbeitszyklus

Die Templates (`AGENTS.md`, `CLAUDE.md`) schreiben ihn verbindlich vor:

1. **Start.** `DOKUMENTATION/README.md` und die offenen Punkte in `TODO/TODO.html` lesen. Strukturfragen zuerst an den Graphen stellen.
2. **Arbeiten** in kleinen Schritten; jeden Schritt am echten Ziel prüfen, nicht nur mit einem Werkzeug.
3. **Nach jedem abgeschlossenen Teilziel:**
   - **Todo:** betroffene Einträge anlegen, ändern oder schließen (`/todo`).
   - **Living Documentation:** nur die geänderten Aussagen anpassen, jede als *bestätigt*, *Entscheidung*, *offener Punkt* oder *Risiko* kennzeichnen. Prüfungen mit tatsächlichem Umfang festhalten.
   - **Graphify:** `graphify update .` ausführen. Weigert sich Graphify, einen kleineren Graphen zu schreiben, die Ursache klären – niemals erzwingen.
4. **Auslieferung.** Version und Release Notes erhöhen, dann `mgd-platform doctor` und `mgd-platform release-check`.

Beispiel für einen Eintrag im „Aktuellen Stand“:

```text
Geprüft: npm test und graphify update .
Ergebnis: 52 Tests erfolgreich; Graph aktualisiert (27 Knoten)
Nicht geprüft: Staging-Deployment, echte Nutzerrollen
```

Wer `/autopilot` einsetzt: Der Autopilot-Skill zieht Todo-Liste und Graph im Schritt „Aufschreiben“ nach, und die Living Documentation bindet sich über ihre eigene Autopilot-Integration in den Lauf ein. Der Zyklus oben gilt trotzdem – er ist der gemeinsame Nenner für jeden Agenten.

## Durchsetzung

| Werkzeug | Verhalten |
|---|---|
| `mgd-platform companions status` | zeigt je Begleiter Installation (je Agent und Geltungsbereich) und Projektartefakt; Exit-Code 1, solange etwas fehlt |
| `mgd-platform doctor` | listet die Begleiter; fehlende sind Hinweise (kein Fehler) mit fertigem Installationsbefehl |
| `mgd-platform validate` | prüft nur das Profil: `companions.<begleiter>.enabled: false` ist ein **Fehler**; ein fehlender Abschnitt ist eine Warnung, damit ältere Profile gültig bleiben |
| `mgd-platform release-check` | **blockiert**, solange ein Begleiter oder sein Projektartefakt fehlt. Geprüft werden auch Skills im Benutzerordner (`~/.claude/skills`); ein CI-Runner braucht deshalb `--scope project` |

Profil in `MGD_PLATFORM.yml`:

```yaml
companions:
  living_documentation:
    enabled: true
    path: "DOKUMENTATION"
  graphify:
    enabled: true
    path: "graphify-out"      # fest: Graphify schreibt immer dorthin
  todo:
    enabled: true
    path: "TODO/TODO.html"
```

## Bestehende Projekte nachrüsten (0.6 → 0.7)

1. `companions`-Abschnitt in `MGD_PLATFORM.yml` ergänzen und `foundation_version` erst nach dem Audit anheben.
2. `mgd-platform companions install . --scope project --graphify user` (Trockenlauf), prüfen, dann mit `--yes`.
3. Vorhandene Dokumentation übernehmen statt ersetzen: `companions.living_documentation.path` auf den bestehenden Ordner setzen.
4. `mgd-platform doctor` und `release-check` ausführen.

## Grenzen und Sicherheit

- **Netzwerk:** Nur `companions install --yes` lädt etwas – die Skills aus den angepinnten GitHub-Releases und Graphify über `uv`/`pipx`/`pip`. `graphify update .` arbeitet lokal, ohne LLM und ohne Netzwerk (auch mit gesperrtem Netzwerk getestet).
- **Lieferkette:** Verfolgt werden nur Repositories deines GitHub-Kontos (Owner-Prüfung), nur Tags der Form `vX.Y.Z` (keine Vorabversionen) und nie etwas unter dem getesteten Mindeststand. Der Builder vertraut damit deinem GitHub-Konto zum Installationszeitpunkt; wer das nicht will, nutzt `--pinned`.
- **Agenten-Konfiguration:** Mit `--scope project` bleibt alles im Projekt, mit `--scope user` landen nur die Skill-Ordner im Benutzerverzeichnis. Die Installation ändert nie deine globale `CLAUDE.md`.
- **Git-Hooks:** Standardmäßig aus (`--hooks`). Sie sind lokal und werden nicht mit dem Repository geteilt, laufen aber bei jedem Commit und Checkout.
- **Keine fremden Programme:** Lesende Befehle (`status`, `doctor`, `release-check`, Trockenlauf) führen nichts aus, was im Projektordner liegt. Jeder Kindprozess startet mit einem PATH ohne relative oder projektinterne Einträge (verglichen nach echtem Pfad, Symlinks und Schreibweise ändern nichts). Installer (`git`, `uv`, `pipx`, `python`, `pip`) laufen in einem neutralen Verzeichnis. Die projektlokale Graphify-Umgebung wird nur im selben Lauf ausgeführt, in dem sie neu erzeugt wurde, und danach in `~/.cache/mgd-platform/venvs.json` (außerhalb des Projekts) vermerkt; spätere Läufe vertrauen nur diesem Vermerk und verweisen auf `.mgd/graphify-venv/bin/graphify update .`. Eine vorhandene Umgebung wird nie wiederverwendet.
- **Symlinks:** Kein Schreibzugriff folgt einem symbolischen Link im Projekt, auch `init` nicht.
- **Pfade aus dem Profil:** `MGD_PLATFORM.yml` ist nicht vertrauenswürdig. Pfade müssen schlichte Namen sein (kein `..`, keine Punkt-Ordner wie `.git`, keine Steuerzeichen).
- **Git beim Klonen:** Hooks, fsmonitor, Submodule und LFS sind abgeschaltet, geerbte `GIT_*`-Überschreibungen entfernt.
- **Graphify-Version:** Standard ist das neueste Release von PyPI (`>=0.9.65`), `--pinned` installiert exakt das getestete `0.9.93`. Hashes werden nicht geprüft.
- **Graph ≠ Beleg:** `graphify update .` erfasst Code und die Struktur von Markdown lokal. Semantische Beziehungen zwischen Dokumenten braucht `/graphify --update` mit einem Modell – optional und nicht Teil der Pflicht.
- **Keine Geheimnisse:** Weder Dokumentation noch TODO-Datei noch Graph enthalten Zugangsdaten oder personenbezogene Daten.

## Siehe auch

- [[09-AI-Agenten-und-Automation]]
- [[14-Governance-Dokumentation-und-Releases]]
- [[18-CLI-Validator-und-Automatisierung]]
- [[24-Briefing-Templates-und-Empfehlungen]]
