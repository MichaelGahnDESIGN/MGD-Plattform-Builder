---
name: platform
description: Nutze den MGD-Plattform-Builder als Basis für dein Projekt — einfache Fragen, fertige Antworten, sofort starten.
---

# /platform — Projekt auf sicherer Basis starten

Der Plattform-Builder ist eine Vorlage mit allen Grundlagen, die jedes Projekt braucht: Versionierung, Release-Noten, Sicherheit, Datenschutz, Design-Systemm, Einstellungen.

**Du antwortest auf ein paar Fragen, der Builder macht den Rest.**

## Die 5 Schritte

### 1. `/platform briefing`

Kurze Fragen zu deinem Projekt:
- Was wird gebaut? (Spiel, Website, Shop, App?)
- Für wen?
- In welchen Ländern?
- Welche Sprachen?
- Welcher Tech-Stack? (PHP/MySQL, Node, Python, ...)

**Dauer:** 10 Minuten. Keine Programmier-Kenntnisse nötig.

**Ergebnis:** `MGD_PLATFORM.yml` — dein Projekt-Profil.

### 2. `/platform template`

Wähle eine fertige Projekt-Vorlage:
- `php-mysql-starter` (Websites, Shops)
- `nodejs-express-starter` (Web-Apps)
- `python-django-starter` (Datenbank-Apps)

Der Builder setzt alles auf, du schreibst nur noch Business-Code.

### 3. `/platform init`

Erstelle die Projekt-Grundlagen:
- `AGENTS.md` — wer macht was (du, Claude, Codex?)
- `CLAUDE.md` — Regeln für Claude Code
- Todo-Liste
- Dokumentations-Ordner

**Dann:** Du kannst sofort anfangen zu bauen.

### 4. `/platform audit`

Prüfung vor dem Release (lesbar nur, keine Änderungen):
- ✓ Alle Sicherheits-Features aktiv?
- ✓ Datenschutz in Ordnung?
- ✓ Version und Release-Noten aktuell?
- ✓ Design konsistent (Light & Dark)?

**Wenn rot:** Der Builder zeigt dir, was noch fehlt.

### 5. `/platform release`

Version hochfahren und Release-Notizen schreiben:

```bash
/platform release patch
# oder
/platform release minor
```

Das war's. Live-Deploy macht ein anderer Befehl.

## Companion Skills

Der Builder erkennt automatisch:
- **Todo** — deine Aufgaben verwalten
- **Fragenkatalog** — Design-Fragen mit Experten beantworten
- **DevOS** — mehrere Projekte gemeinsam managen

Du wirst gefragt, ob du sie installieren willst.

## Praktisches Beispiel

```
Michael: Ich will eine E-Commerce-Website bauen, PHP + MySQL, Deutschland + Schweiz

Claude startet /platform briefing
  → Fragt 8 Fragen (5 Min)
  → Speichert in MGD_PLATFORM.yml

/platform template
  → Zeigt "php-mysql-starter" vor
  → Michael: OK
  → Builder setzt alles auf

/platform init
  → Erstellt AGENTS.md, CLAUDE.md, Ordner
  → Michael kann sofort Code schreiben

6 Wochen später:
/platform audit
  → Alle grün? Ja ✓

/platform release minor
  → Version 0.1.0 → 0.2.0
  → Release-Noten geschrieben
  → Live
```

## Häufige Fragen

**F: Brauche ich Programmier-Erfahrung?**  
A: Nein. Die Fragen sind für jeden verständlich. Den technischen Teil macht der Builder.

**F: Kann ich das später ändern?**  
A: Ja. `/platform audit` zeigt dir, was konfigurierbar ist. Dann `/platform init` nochmal — überschreibt nicht, fragt nach.

**F: Was kostet das?**  
A: Nichts. Der Builder ist kostenlos, nur deine Entwicklung kostet (Tools, Hosting).
