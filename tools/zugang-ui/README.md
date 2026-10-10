# Zugang-UI

Kleine lokale Weboberfläche, um Zugänge für Kundenprojekte sicher einzurichten. Nur Python 3, keine Zusatzpakete.

## Start

```bash
./start.command                       # Wurzelordner = zwei Ebenen über diesem Ordner (z. B. KUNDENPROJEKTE)
./start.command --root /pfad/zu/Projekten --port 8765
```

Der Browser öffnet sich mit einem Zufalls-Token in der URL. Ohne Token ist alles gesperrt.

## Was sie macht

1. Optional: Werte aus deiner Zugangs-`.md` vorausfüllen (Host, SSH-User, Fingerprint, Domain, DB).
2. Schützt per `.gitignore` (inkl. der Quell-`.md`), legt `.env.access.local` (600) und `.access.local.md` (600, ohne Passwörter) an.
3. Erzeugt einen projekteigenen ed25519-Schlüssel unter `.ssh/` und zeigt den öffentlichen Teil zum Eintragen beim Hoster.
4. Testet Host-Fingerprint und Key-Login (`BatchMode`, ohne Passwort).
5. Zeigt SSH-Alias und Berechtigungsregeln für Claude Code und trägt den Alias auf Wunsch in `~/.ssh/config` ein.

## Sicherheit

- Lauscht nur auf `127.0.0.1`, prüft den Host-Header gegen DNS-Rebinding, verlangt ein Token.
- Schreibt nur in Projektordner unterhalb des Wurzelordners.
- Vorhandene `.env.access.local` wird nicht überschrieben, privater Schlüssel nie angezeigt.
- Kein Passwort-Login: Zugriff läuft über den Schlüssel. Passwörter stehen nur in der lokalen env-Datei.
