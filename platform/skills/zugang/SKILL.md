---
name: zugang
description: Richtet Zugänge für Kundenprojekte sicher ein, egal ob Server (SSH/SFTP), Shop (JTL, Shopware, Shopify, WooCommerce), WordPress, Datenbank oder Hosting-Panel. Fragt zuerst, wie der Zugang abläuft, schützt die Zugangsdaten per .gitignore, erzeugt eine lokale env und, wo möglich, einen projekteigenen Key, den der Nutzer einträgt; danach testet die KI den Zugang selbst. Ausloesen mit „/zugang", bei „ich gebe dir Zugangsdaten", „SSH einrichten", „Zugang zum Server/Shop/WordPress".
---

# /zugang — Zugang zu Server, Shop oder WordPress sicher einrichten

Der Nutzer legt eine Markdown-Datei mit Zugangsdaten ins Projekt. Die KI schützt sie, macht daraus
eine lokale Arbeitsumgebung und nutzt, wo immer möglich, **Key- oder Token-Zugang statt Passwort**.
Passwörter bleiben in der Quelldatei und werden nie in Chat, Logs, Commits oder Memory wiederholt.

## Aufruf

```
/zugang                     # fragt, was für ein Zugang eingerichtet werden soll
/zugang <datei.md>          # bestimmte Quelldatei verwenden
```

## Schritt 0: Zugangsart klären (immer zuerst fragen)

Mit `AskUserQuestion` (Mehrfachauswahl) abfragen, **was** eingerichtet werden soll und **wie** der Login abläuft.
Nichts annehmen, was die Quelldatei nicht belegt.

| Zugangsart | Frage dazu | Bevorzugter Weg |
|---|---|---|
| Server per SSH | Host, User, Webroot bekannt? Key schon hinterlegt? | projekteigener ed25519-Key |
| Server per SFTP/FTP | Nur Dateien nötig? SSH nicht verfügbar? | SFTP mit Key, sonst Nutzer lädt hoch |
| Shop-Backend (JTL, Shopware, Shopify, WooCommerce) | Backend-URL, Rolle, 2FA? | Zugriff über Server/DB/CLI; Browser-Login nur durch den Nutzer |
| WordPress | wp-admin, WP-CLI per SSH oder REST? | WP-CLI per SSH, sonst Application Password |
| Datenbank | Direkt, per SSH-Tunnel oder phpMyAdmin? | Zugriff vom Server aus, Passwort nur per Umgebung |
| Hosting-Panel (KAS, Plesk, cPanel) | Was soll dort passieren? | Nutzer erledigt Panel-Schritte, KI gibt Anleitung |
| API/Token | Scope, Ablaufdatum, Testmodus? | Token mit minimalen Rechten, nur lesend zum Start |

Außerdem klären: Läuft 2FA? Gibt es eine Staging-Umgebung? Darf auf dem Live-System geschrieben werden?

## Gemeinsamer Ablauf

### 1. Quelldatei lesen
- Host, User, DB, Domain-Routing, Fingerprints, Shop-/WP-Login erfassen.
- **Werte nie im Chat wiederholen.** Nur Namen und Zeilennummern nennen.

### 2. Zuerst schützen, dann ablegen
- `.gitignore` anlegen oder ergänzen, **bevor** neue Dateien mit Geheimnissen entstehen:
  Quelldatei, `.access.local.md`, `.env.access.local`, `*.local.md`, `.env*`, `.ssh/`, `*.pem`, `*.key`.
- Git-Repo: `git check-ignore -v <datei>`; ist eine Datei schon getrackt, warnen und `git rm --cached`
  vorschlagen, nicht still ausführen.
- Kein Repo: Hinweis, dass die Regeln erst nach `git init` greifen.
- Rechte: Dateien `600`, Ordner `700` (`umask 077`).
- In öffentlichen Repos und Vorlagen stehen **keine** echten Hosts, User, Pfade oder Passwörter.

### 3. Lokale Arbeitsdateien erzeugen
- `.env.access.local` (600): `KEY=VALUE` für alle gewählten Zugangsarten (Host, SSH-User, DB-Werte,
  Admin-URL und -User, API-Token). Daraus liest die KI später.
- `.access.local.md` (600): Überblick **ohne Passwörter** — Projekt, Webroot, bekanntes Problem,
  Verweis auf die env-Datei, Regeln.

### 4. Zugang je nach Art einrichten

**SSH/SFTP**
```bash
mkdir -p .ssh && chmod 700 .ssh
ssh-keygen -t ed25519 -N "" -C "claude-<projekt>" -f .ssh/id_ed25519_<projekt> -q
chmod 600 .ssh/id_ed25519_<projekt>
cat .ssh/id_ed25519_<projekt>.pub
```
Öffentlichen Schlüssel in einem Codeblock ausgeben und sagen, wo er eingetragen wird
(KAS/all-inkl: *Tools → SSH-Schlüssel* beim SSH-User; sonst `~/.ssh/authorized_keys`).
**Dann warten**, bis der Nutzer „erledigt" meldet. Kein `sshpass`, kein Passwort in Kommandozeilen.

**WordPress**: bevorzugt WP-CLI über den SSH-Zugang (`wp --path=<webroot> option get siteurl`).
Ohne SSH: Nutzer legt unter *Benutzer → Profil → Anwendungspasswörter* ein Application Password an,
Nutzung nur für die REST-API, ablegen in der env-Datei.

**Shop-Backend (JTL, Shopware, Shopify, WooCommerce)**: Backend-Login macht der Nutzer selbst
(2FA, Captcha). Die KI arbeitet über Server, Datenbank und Shop-CLI (z. B. `php cli list` bei JTL 5,
`bin/console` bei Shopware, Shopify-Admin-Token mit Minimalrechten).

**Datenbank**: Zugang vom Server aus. Zugangsdaten aus der Shop-Konfiguration lesen und per
`MYSQL_PWD` in der Umgebung übergeben, **nie** als Argument. Geheimnisse in Ausgaben maskieren.

**Hosting-Panel / Konto-Logins**: Nutzer erledigt die Schritte im Panel selbst; die KI liefert die genaue Anleitung.

### 5. Zugang selbst testen
1. Host-Key-Fingerprint **vor** dem ersten Vertrauen prüfen:
   ```bash
   ssh-keygen -lf <(ssh-keyscan -t ed25519 <host> 2>/dev/null)
   ```
   Mit der Quelldatei vergleichen. Nur bei Übereinstimmung weiter.
2. Verbindung:
   ```bash
   ssh -i .ssh/id_ed25519_<projekt> -o IdentitiesOnly=yes -o BatchMode=yes \
       -o StrictHostKeyChecking=accept-new -o ConnectTimeout=15 <user>@<host> 'echo OK; pwd'
   ```
3. Webroot finden (oft in einer Jail): `ls /www`, `find /www -maxdepth 6 -name <datei>`.

### 6. Erster Lesedurchlauf (nur lesend)
- Fehler- und Slow-Logs (z. B. `logs/php-fpm_slow.log`), Shop-Logs.
- Häufigste Skripte und Frames auswerten, `curl_exec`-Hänger und externe Hosts erkennen.
- Auffälligkeiten melden, auch wenn sie nicht zur Aufgabe gehören (Virenfunde, unbekannte PHP-Dateien).

## Leitplanken

- **Nur lesen**, bis der Nutzer ausdrücklich zustimmt. Änderungen am Live-System (Plugin deaktivieren,
  DB-Schreibzugriffe, Löschen) nur nach Rückfrage, **mit vorherigem Backup** und im kleinsten Umfang.
- Eine Freigabe gilt für genau diese Aktion. Weitere Schreibzugriffe (z. B. Cache- oder Tabellen leeren)
  neu erfragen; wird eine Aktion abgelehnt, nicht umgehen, sondern erklären und warten.
- Keine Passwörter oder Schlüssel in Chat, Logs, Commits, Memory, Issues oder öffentlichen Repos.
- Nie ein Passwort in ein Formular, Login oder SSH-Prompt eintippen. Zugang läuft über Key oder Token.
- Wurden Passwörter im Klartext in Datei oder Chat gelegt: am Ende empfehlen, sie zu ändern.
- Der Key gehört zum Projekt. Nach Projektende den öffentlichen Schlüssel beim Hoster entfernen.

## Typische Fallstricke

- zsh: `S="ssh …"; $S` scheitert. Den `ssh`-Befehl jeweils ausschreiben.
- Remote-Befehle in einfache Anführungszeichen; lokale Variablen vorher setzen.
- Die Quelldatei muss in `.gitignore` stehen, sonst landet sie im Commit.
- Ein Fingerprint, der nicht passt, ist ein Abbruchgrund.
- JTL 5: deaktivierte Plugins fehlen in der Hook-Liste (nur `nStatus = 2` ist aktiv); danach
  `php cli cache:clear` und `php cli cache:tpl:delete`. Fremde Suchserver (z. B. JTL Search) können
  mit festen 60 s Timeout den ganzen Shop bremsen.

## Abschlussbericht

Kurz, ohne Geheimnisse: eingerichtete Dateien und Rechte, Testergebnis, Befunde des Lesedurchlaufs,
welche Änderungen Freigabe brauchen.
