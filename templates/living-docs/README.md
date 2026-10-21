# {{PROJECT_NAME}} – Living Documentation

Kanonische Projektdokumentation. Sie beschreibt den **belegbaren Ist-Zustand**, nicht den
Wunschzustand. Ein Build, ein lokaler Commit oder eine Planung sind keine Veröffentlichung, kein
erfolgreicher Live-Test und keine fertige Funktion.

Gepflegt mit dem Skill `living-documentation` (MGD Living Documentation). Aufgaben stehen in
`TODO/TODO.html` (Skill `todo`), die maschinell erzeugte Projektkarte in `graphify-out/`
(Graphify). Jede relevante Änderung aktualisiert alle drei – siehe „Arbeitszyklus“ in `AGENTS.md`.

Stand der Vorlage: {{DATE}}

## Aktueller Stand

Nur bestätigte Aussagen. Jede Zeile nennt den Beleg.

| Bereich | Stand | Beleg | Geprüft am |
| --- | --- | --- | --- |
| Projekt eingerichtet | bestätigt | `mgd-platform doctor` | {{DATE}} |

## Karte (Graphify)

Die Karte ist **abgeleitet** und wird nie von Hand bearbeitet. Sie beantwortet „was hängt woran?“,
sie ersetzt keine Entscheidung und keinen Statusbericht.

- Übersicht: `graphify-out/GRAPH_REPORT.md`, interaktiv: `graphify-out/graph.html`
- Fragen zuerst an den Graphen: `graphify query "<Frage>"`, `graphify path "<A>" "<B>"`, `graphify explain "<Begriff>"`
- Nach jeder relevanten Änderung: `graphify update .` (bei einer projektlokalen Installation: `.mgd/graphify-venv/bin/graphify update .`)
- Architekturaussagen in diesem Dokument nennen ihren Beleg, z. B. „`graphify explain "Login"`, {{DATE}}“

## Entscheidungen

| Datum | Entscheidung | Begründung | Rücknahme oder Änderungsbedingung |
| --- | --- | --- | --- |
| {{DATE}} | Living Documentation, Graphify und Todo sind Pflichtbegleiter | MGD-Plattform-Builder, Abschnitt „Companions“ | wird nicht zurückgenommen |

## Offene Punkte

| Thema | Warum offen | Benötigte Entscheidung oder Abhängigkeit | Nächster Schritt |
| --- | --- | --- | --- |
| Briefing abschließen | `briefing.completed` steht noch nicht auf `true` | Antworten des Auftraggebers | `mgd-platform briefing --write` |

## Risiken

| Risiko | Auswirkung | Minderung | Status |
| --- | --- | --- | --- |
| – | – | – | – |

## Verifikation

Prüfungen immer mit ihrem tatsächlichen Umfang festhalten:

```text
Geprüft: <Befehl, Test oder manueller Ablauf>
Ergebnis: <beobachtbares Resultat>
Nicht geprüft: <Grenze oder ausstehender Bereich>
```
