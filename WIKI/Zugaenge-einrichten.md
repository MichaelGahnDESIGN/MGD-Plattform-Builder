# Zugänge sicher einrichten

Diese Anleitung zeigt, wie du einem KI-Agenten (Claude Code) **sicher** Zugang zu Server, Shop, WordPress oder Datenbank gibst,
damit er selbstständig testen, analysieren und deployen kann – ohne dass du Passwörter in den Chat kopierst oder ständig Befehle tippst.

## Das Prinzip

1. Du legst eine **Zugangs-Datei (.md)** ins Projekt. Sie bleibt lokal und steht in der `.gitignore`.
2. Der Agent erzeugt daraus eine **lokale env-Datei** (`.env.access.local`, Rechte `600`).
3. Er erstellt einen **projekteigenen SSH-Schlüssel**. Du trägst nur den *öffentlichen* Teil beim Hoster ein.
4. Der Agent **testet den Zugang selbst** und arbeitet danach per Schlüssel statt per Passwort.

## Drei Wege

| Weg | Wann | Start |
|---|---|---|
| **Skill `/zugang`** | Du arbeitest im Chat mit Claude Code | `/zugang` bzw. „ich gebe dir Zugangsdaten" |
| **Zugang-UI** | Du willst klicken statt tippen | `tools/zugang-ui/start.command` |
| **Berechtigungsregeln** | Der Agent soll nicht dauernd nachfragen | siehe unten |

Der Skill liegt unter `platform/skills/zugang/SKILL.md`, die Oberfläche unter `tools/zugang-ui`.

## Schritt für Schritt

### 1. Zugangsdaten ablegen
Lege eine `.md` in den Projektordner, zum Beispiel mit Host, SSH-User, Fingerprints, Datenbank und Shop-Login.
Die Datei darf **nicht** committet werden. Der Skill und die Oberfläche tragen sie automatisch in die `.gitignore` ein.

### 2. Zugangsart klären
Der Skill fragt zuerst, was eingerichtet werden soll: SSH/SFTP, Shop-Backend (JTL, Shopware, Shopify, WooCommerce), WordPress,
Datenbank, Hosting-Panel oder API-Token. Für jede Art gibt es einen eigenen, möglichst passwortfreien Weg.

### 3. Schlüssel eintragen
Der Agent gibt dir eine Zeile aus, die mit `ssh-ed25519 …` beginnt. Trage sie beim Hoster ein:

- **all-inkl/KAS:** *Tools → SSH-Schlüssel*, beim SSH-User, speichern.
- **Andere Hoster:** in `~/.ssh/authorized_keys` des SSH-Users.

Der öffentliche Schlüssel ist ungefährlich. Der private Schlüssel bleibt im Projektordner unter `.ssh/`.

### 4. Zugang testen
Der Agent prüft zuerst den **Host-Fingerprint** gegen deine Datei und meldet sich dann mit dem Schlüssel an
(`BatchMode`, ohne Passwortabfrage). Danach liest er nur Logs und Konfiguration.

### 5. Selbstständig arbeiten
Damit der Agent nicht bei jedem Befehl fragt, kannst du in `.claude/settings.json` des Projekts erlauben, was er ohne Rückfrage darf:

```json
{
  "permissions": {
    "allow": [
      "Bash(ssh <alias>:*)",
      "Bash(scp:*)",
      "Bash(rsync:*)",
      "Bash(git status:*)",
      "Bash(git diff:*)",
      "Bash(git log:*)"
    ]
  }
}
```

`<alias>` ist der SSH-Alias aus `~/.ssh/config`. Die Oberfläche zeigt dir den passenden Block und trägt den Alias auf Wunsch ein.
Diese Regeln setzt du bewusst selbst – sie sind Sicherheitseinstellungen.

## Was bewusst bestätigungspflichtig bleibt

- **Löschen** von Dateien oder Tabellen und **Massenänderungen** am Live-System.
- **Schreibzugriffe auf die Datenbank** – immer mit vorherigem Backup und im kleinsten Umfang.
- **Passwörter eintippen** in Logins oder Terminal-Abfragen. Backend-Logins mit Passwort und 2FA machst du selbst.

## Sicherheitsregeln

- Keine Zugangsdaten in Chat, Logs, Commits, Issues oder öffentlichen Repos.
- Quelldatei, `.env.access.local`, `.access.local.md` und `.ssh/` stehen in der `.gitignore`.
- Bei jedem Fingerprint-Unterschied wird abgebrochen. Er ist kein Hinweis zum Wegklicken.
- Wurden Passwörter im Klartext abgelegt oder geteilt: danach ändern.
- Nach Projektende den öffentlichen Schlüssel beim Hoster wieder entfernen.

## Fehlerbehebung

| Meldung | Ursache | Lösung |
|---|---|---|
| `Permission denied (publickey)` | Schlüssel beim Hoster nicht eingetragen oder beim falschen User | Schlüssel beim SSH-User eintragen, ggf. einige Minuten warten |
| Fingerprint stimmt nicht | falscher Host oder Man-in-the-Middle | Host prüfen, nicht fortfahren |
| `Connection timed out` | Port oder Firewall | Port prüfen, Hoster fragen |
| Der Agent fragt ständig nach | keine Berechtigungsregeln gesetzt | Abschnitt „Selbstständig arbeiten" |
| Eine Löschung wird abgelehnt | Schutzmechanismus von Claude Code | feste, kleine Liste freigeben oder selbst ausführen |
