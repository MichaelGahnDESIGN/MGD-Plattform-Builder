# Code-Kommentare in Michaels Stil

Der optionale Skill [`code-kommentare`](../platform/skills/code-kommentare/SKILL.md)
formuliert kurze, praktische deutsche Kommentare: Bereich benennen, echte Gründe
und nötige Hinweise ergänzen. Zum Beispiel `/* Glass-Effekt Header */` oder
`// PIN nur als Hash speichern, nie zurück ans Frontend`.

Keine wiederholten Erklärungen offensichtlicher Zeilen, keine künstlichen
KI-Formulierungen. Kleine natürliche Tippfehler sind nur im freien Kommentartext
zulässig; sie werden nicht systematisch eingebaut. Code, Pfade, Direktiven,
Dokumentation und UI-Texte bleiben korrekt. Sicherheits- und Lizenzhinweise bleiben
vollständig. Die Kommentarsyntax richtet sich nach der Sprache: CSS erlaubt kein `//`.

## Installieren und aufrufen

Aus dem geklonten Repository, Python 3.9 oder neuer:

```sh
python3 scripts/install-code-kommentare.py --agent codex
python3 scripts/install-code-kommentare.py --agent claude
```

`--agent both` installiert für beide Clients, ohne vorhandene Ziele zu überschreiben.
Codex verwendet `~/.agents/skills/code-kommentare`, Claude Code
`~/.claude/skills/code-kommentare`. Für Codex-Installationen mit `~/.codex/skills`
stattdessen `--agent codex-legacy` wählen. Doppelte Installationen vermeiden.
`--project /pfad/zum/vorhandenen-projekt` installiert in diesem Projekt statt global.
Der Installer kopiert ausschließlich SKILL.md, agents/openai.yaml und LICENSE,
keine Projektdateien, keine Zugangsdaten und keine globalen Agentenregeln.
Bestehende Ziele oder Symlinks in den Zielordnern werden abgelehnt; bei E/A-Fehlern
kann eine Teilinstallation bestehen bleiben, die man gezielt prüfen muss.

- Claude Code: `/code-kommentare [Datei oder Bereich]`
- Codex: `$code-kommentare [Datei oder Bereich]` oder Skill-Selektor

Nach der Installation gegebenenfalls einen neuen Chat starten. Die Installation gilt
für den jeweiligen lokalen Client, nicht automatisch für Cloud-Sitzungen.
Der Stil ist optional und kann durch den Nutzer projektweit vorgegeben werden.
Der Skill verändert weder Programmlogik noch fremde Dateien ungefragt.

## Divi

Native Divi-Funktionen zuerst prüfen. Eine im Projekt vereinbarte Code-Box unter
**Divi → Theme-Optionen → Individuelles CSS** ist ausschließlich für zusätzliches
Gestaltungs-CSS vorgesehen. JavaScript und PHP haben eigene Ablageorte; Plugin-Assets
bleiben beim Modul. Projektspezifische Vorgaben gehören in die jeweilige Projektdoku.
Die Nutzung des Skills ist kein Auftrag für eine Live-Änderung oder CSS-Migration.

## Prüfung

```sh
python3 -m unittest discover -s test -p 'test_code_kommentare_install.py' -v
npm run check
```

Der Skill und sein MIT-Lizenztext sind unabhängig vom Plattform-Starter nutzbar.
