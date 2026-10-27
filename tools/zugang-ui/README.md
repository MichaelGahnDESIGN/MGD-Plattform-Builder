# Zugang-UI – einfach erklärt

Eine kleine Webseite, die **nur auf deinem Rechner** läuft. Sie hilft dir, einen **SSH-Schlüssel** für deinen Server zu erzeugen und die Verbindung zu testen. Du musst keine Befehle tippen.

## Was brauchst du?

- Python 3. Test im Terminal: `python3 --version`. Zeigt es eine Zahl wie `3.11`, passt alles.
- Einen Projektordner.

Weitere Programme musst du nicht installieren.

## Starten

Doppelklick auf `start.command` (Mac). Oder im Terminal:

```bash
./start.command
```

Dein Browser öffnet sich mit einer Adresse, die `127.0.0.1` enthält und am Ende einen langen Zufallscode hat. Diesen Link brauchst du, sonst kommt die Seite nicht. Schließe das Terminal-Fenster nicht, solange du die Seite benutzt. Beenden: im Terminal `Strg + C`.

## So geht es Schritt für Schritt

1. **Projekt:** Trage den Pfad deines Projektordners ein. Hast du eine Zugangs-Datei (`.md`), trage auch diesen Pfad ein und klicke auf „Aus .md übernehmen“. Viele Felder füllen sich dann von selbst.
2. **Zugang:** Prüfe die Felder. Klicke auf „Einrichten & Schlüssel erzeugen“.
3. **Schlüssel eintragen:** Kopiere die Zeile mit `ssh-ed25519` und trage sie bei deinem Hoster ein (bei all-inkl: *Tools → SSH-Schlüssel*).
4. **Testen:** Klicke auf „Jetzt testen“. Grüne Haken heißen: alles gut.
5. **Selbstständig arbeiten (optional):** Die Seite zeigt dir einen Kurznamen für den Server und Regeln für Claude Code.

## Was die Seite für dich anlegt

| Datei oder Ordner | Wofür |
|---|---|
| `.gitignore` (ergänzt) | Schützt die geheimen Dateien vor GitHub |
| `.env.access.local` | Deine Zugangswerte, nur für dich lesbar |
| `.access.local.md` | Kurze Notiz ohne Passwörter |
| `.ssh/` | Hier liegt dein privater Schlüssel. **Niemals weitergeben** |

Eine schon vorhandene `.env.access.local` überschreibt die Seite **nicht**.

## Ist das sicher?

- Die Seite ist nur auf deinem Rechner erreichbar, nicht im Internet.
- Ohne den Zufallscode im Link kommt niemand hinein.
- Sie schreibt nur in Ordner unterhalb des erlaubten Wurzelordners.
- Passwörter zeigt sie dir nie an.
- Sie meldet sich **nicht** mit Passwort an, nur mit dem Schlüssel.

## Wenn etwas nicht klappt

| Du siehst | Das hilft |
|---|---|
| „Kein gültiges Token“ | Öffne den Link aus dem Terminal noch einmal |
| „Der Ordner muss unterhalb von … liegen“ | Starte mit `./start.command --root /dein/projekte-ordner` |
| Test rot bei „SSH-Login“ | Ist der Schlüssel beim Hoster eingetragen? Beim richtigen SSH-Benutzer? |
| Test rot bei „Fingerprint“ | **Nicht weitermachen.** Server-Adresse prüfen |
| `python3: command not found` | Python 3 installieren |

## Aufräumen

Wenn das Projekt fertig ist: Öffentlichen Schlüssel beim Hoster wieder entfernen.
