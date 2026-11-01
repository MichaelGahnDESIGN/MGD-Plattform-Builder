# 30 · Backup-Modul (Starter-Modul `backup`)

Das Modul `backup` liegt im PHP/MySQL-Starter unter `templates/php-mysql-starter/modules/backup/` und sichert **Datenbank und ausgewählte Dateien verschlüsselt**, nach Zeitplan oder auf Knopfdruck. Es läuft auf einfachem Shared Hosting: reines PHP, eine MySQL/MariaDB-Datenbank, **kein** Node, **kein** Composer, **kein** `mysqldump`, **kein** `exec()`, **kein** Queue-Worker. Ein Cronjob treibt die Läufe in kleinen Zeitscheiben voran. Englische Fassung: [BACKUP-MODULE](18-CMS/BACKUP-MODULE.md).

> [!IMPORTANT]
> Ein Backup gilt erst als belegt, wenn eine Wiederherstellung getestet wurde ([Backup & Restore](06-OPERATIONS/BACKUP-RESTORE.md)). Der private Schlüssel gehört auf **zwei getrennte Medien** außerhalb des Servers. Ohne ihn sind alle Backups unlesbar.

## Was das Modul kann – und was nicht

| Kann | Kann (bewusst) nicht |
|---|---|
| Datenbank per PDO sichern (Keyset-Paginierung, NDJSON, gzip), konsistent per Snapshot, wenn ein Aufruf reicht | Wiederherstellung im Browser (nur Kommandozeile) |
| Dateien aus **freigegebenen Bereichen** sichern, mit Ausschlussmustern | beliebige Pfade aus dem Backoffice eintragen |
| Public-Key-Verschlüsselung (libsodium), signiertes Manifest | den privaten Schlüssel erzeugen oder speichern |
| Ziele: **lokal** (mit authentifiziertem Download) und **FTPS** über `ext-curl` | Klartext-FTP, SFTP, Dropbox, Google Drive und andere Dienste (kommen später als Marketplace-Module über das Treiber-Interface) |
| Intervall, Aufbewahrung, Höchstalter, Speicherplatzprüfung, Abbruch, Wiederaufnahme | inkrementelle Datei-Backups (jedes `full` sichert alle gewählten Dateien) |

## Voraussetzungen

- PHP ≥ 8.2 mit `sodium`, `zlib`, `pdo_mysql`; für FTPS zusätzlich `curl`.
- Ein Cronjob beim Hoster (HTTP-URL **oder** PHP-CLI).
- Schreibrechte auf `storage/backups/` (der Ordner ist per `.htaccess` für das Web gesperrt; bei Nginx o. Ä. den Ordner `storage/` selbst sperren – die Teile sind zwar verschlüsselt, gehören aber nicht ins Web).

## Einrichtung in sieben Schritten

1. **Modul aktivieren:** Backoffice › Einstellungen › Module › „Backup“ › Aktivieren. Die Migration legt sechs `backup_*`-Tabellen an.
2. **Schlüsselpaar auf dem eigenen Rechner erzeugen** (nicht auf dem Server):
   ```bash
   php modules/backup/tools/mgd-backup.php keygen
   ```
   Der Befehl fragt eine Passphrase ab (mindestens 12 Zeichen, Argon2id) und schreibt den privaten Schlüssel nach `~/.mgd-backup-keys/primary.key` (Rechte 0600). Ausgegeben werden nur der **Fingerabdruck** und die Zeile des **öffentlichen Schlüssels** (`mgdbk-pk1:…`). Optional einen zweiten Schlüssel als Notfallschlüssel erzeugen (`--name=emergency`). Beide privaten Dateien an getrennten Orten sichern.
3. **Öffentlichen Schlüssel eintragen:** Backup › Schlüssel. Die Eingabe wird mit Format und Prüfsumme geprüft und verlangt Ihr aktuelles Passwort.
4. **Signatur-Fingerabdruck notieren:** Der Server erzeugt beim ersten Lauf einen Ed25519-Signaturschlüssel (verschlüsselt in der Datenbank). Sein Fingerabdruck steht unter Backup › Schlüssel. Notieren Sie ihn – damit prüfen Sie später, dass ein Backup wirklich von Ihrem Server stammt.
5. **Ziel anlegen:** Backup › Ziele.
   - *Lokal:* legt Teile unter `storage/backups/mgdbk/` ab; Download im Backoffice.
   - *FTPS:* Hostname, Port 21 (explizites TLS) oder 990 (implizit), Benutzer, Passwort, Zielordner. Das Passwort wird verschlüsselt gespeichert und nie angezeigt. Ein entferntes Ziel wird erst aktiv, nachdem **„Verbindung testen“** erfolgreich war.
6. **Zeitplan, Aufbewahrung und Dateibereiche** festlegen (Backup › Zeitplan und Aufbewahrung).
7. **Cronjob einrichten** (nächster Abschnitt) und einen ersten Lauf mit „Jetzt sichern“ prüfen. Danach **Wiederherstellung üben**.

## Cronjob

Jeder Aufruf arbeitet höchstens `backup.tick_seconds` (Standard 100 s, begrenzt durch `max_execution_time`) und setzt einen laufenden Lauf fort. Rhythmus 5 bis 15 Minuten genügt.

**CLI (bevorzugt, kein Geheimnis in einer URL):**

```text
php /pfad/zum/projekt/modules/backup/tools/cron.php
```

Weitere Schalter: `--status` (Zustand zeigen), `--enqueue=db|full` (zusätzlich einen Lauf anlegen).

**HTTP (z. B. Cronjobs per URL, wenn der Hoster kein PHP-CLI erlaubt):** Backup › Cronjob › „Neues Token erzeugen“. Das Token wird **einmal** angezeigt und nur als SHA-256 gespeichert. Die URL lautet `https://IHRE-DOMAIN/backup-cron/<TOKEN>`. Falsche Tokens liefern 404; nach 10 Fehlversuchen in 15 Minuten antwortet der Endpunkt mit 429. Ohne gesetztes Token existiert der Endpunkt nicht. Beachten Sie: URLs landen in Zugriffsprotokollen des Hosters – tauschen Sie das Token bei Verdacht aus.

Die Statusseite zeigt den letzten Cron-Aufruf und warnt, wenn er länger als einen Tag zurückliegt.

## Ablauf eines Laufs

`queued → dumping_db → (full: scanning_files → packing_files) → finalizing → uploading → completing`

- Der Lauf wird angelegt (manuell, Zeitplan oder CLI) und von den Cron-Aufrufen Stück für Stück abgearbeitet. Der Zustand steht in der Datenbank.
- **Teile:** Datenbank und Dateien werden in verschlüsselte Teile (Standard 64 MB, einstellbar 8–512 MB) geschrieben. Bricht ein Aufruf mitten in einem Teil ab, wird die halbe Teildatei verworfen und ab dem gespeicherten Cursor neu geschrieben.
- **Konsistenz:** Reicht ein Aufruf für die ganze Datenbank, wird sie in einer konsistenten Lesesicht (`START TRANSACTION WITH CONSISTENT SNAPSHOT`, eigene zweite Verbindung) gesichert (`snapshot`). Sonst nur tabellenweise (`per_tick`, Hinweis im Lauf).
- **Parallelschutz:** Dateisystem-Sperre (`flock`) plus eindeutiger `active_slot` in der Datenbank – es läuft immer höchstens eine Sicherung.
- **Speicherplatz:** Vor dem Start (Datenbank) und nach dem Dateiscan (inkl. Dateigröße) wird der freie Platz geprüft (`min_free_bytes`).
- **Uploads:** Entfernte Ziele erhalten die Teile der Reihe nach, das Manifest zuletzt. FTPS lädt in Abschnitten hoch und macht nach einem Fehler dort weiter. Pro Teil höchstens 5 Versuche mit Wartezeit (5 min · 2ⁿ, höchstens 2 h); danach gilt das Ziel für diesen Lauf als fehlgeschlagen und bereits hochgeladene Teile werden aufgeräumt. Bei reinen Remote-Zielen wird ein Teil lokal gelöscht, sobald er überall liegt.
- **Abschluss:** `completed`, `partial` (z. B. übersprungene Dateien oder ein Ziel ausgefallen) oder `failed`. Fehler werden nur als **Katalogcode** gespeichert (Tabelle unten), nie als Roh-Meldung.

### Aufbewahrung

Je Ziel bleiben die neuesten `keep_count` Sicherungen **je Art** (`db`/`full`). Zusätzlich gilt ein globales Höchstalter (7–365 Tage). **Die jüngste erfolgreiche Sicherung je Ziel und Art bleibt immer**, auch über das Höchstalter hinaus. Löschen geschieht ausschließlich über **gespeicherte Referenzen** (Pfad/Dateiname aus der Datenbank), nie über ein Verzeichnislisting, und nur für Namen nach dem Muster `mgdbk_…`; fremde Dateien im Zielordner werden nie angefasst. Fehlgeschlagene Löschungen werden mit Wartezeit wiederholt.

## Download und Prüfung

Backup › Verlauf › Details zeigt jeden Teil. Der Download ist ein **authentifizierter Endpunkt** (Rolle Administration): Der Dateiname kommt nie aus der Anfrage, sondern aus der Datenbank und wird gegen die Namensregel geprüft; der Pfad muss unter dem Backup-Ordner liegen (kein Symlink, kein `..`); die Antwort trägt `Content-Disposition: attachment`, `X-Content-Type-Options: nosniff`, `Cache-Control: no-store, private` und `Content-Security-Policy: sandbox`.

Prüfen und entschlüsseln Sie auf dem **eigenen Rechner**:

```bash
php modules/backup/tools/mgd-backup.php verify  <ordner-mit-teilen> --key-file=<schluessel> --signer=<fingerabdruck>
php modules/backup/tools/mgd-backup.php decrypt <ordner-mit-teilen> --key-file=<schluessel> --out=<neuer-ordner> --signer=<fingerabdruck>
```

`verify` entschlüsselt streamend und prüft jeden Block (Manipulation, Abschneiden, angehängte Bytes), die Manifest-Signatur, Größe und SHA-256 jedes Teils sowie Zeilen- und Dateizahlen. Ohne `--signer` (oder `--accept-unverified-signer`) verweigert das Werkzeug die Arbeit, damit ein gefälschtes Backup mit fremdem Signaturschlüssel auffällt. `decrypt` schreibt `db.ndjson.gz` und den Dateibaum im Klartext (Rechte 0600/0700).

## Wiederherstellung (nur Kommandozeile)

1. Teile vom Ziel holen bzw. herunterladen; auf dem eigenen Rechner mit `decrypt` entschlüsseln (siehe oben). **Der private Schlüssel verlässt den eigenen Rechner nie.**
2. Den Klartext-Ordner auf den Server bringen (z. B. per FTPS) und den **Wartungsmodus** einschalten.
3. Trockenlauf – schreibt nichts, zeigt Tabellen, Zeilen, Bereiche und den Bestätigungscode:
   ```bash
   php modules/backup/tools/restore.php <klartext-ordner>
   ```
4. Einspielen:
   ```bash
   php modules/backup/tools/restore.php <klartext-ordner> --confirm=<code> [--only=db|files]
   ```

Schutz: Der Trockenlauf liest den gesamten Dump durch, bevor irgendetwas geschrieben wird (Struktur, Zeilenzahlen, jede Tabelle und Spalte muss im Ziel existieren, `backup_*`-Tabellen dürfen nicht vorkommen, die Migrationen in der Sicherung müssen zur Installation passen). Die Datenbank wird in **einer Transaktion** ersetzt (bei Fehler Rollback); die `backup_*`-Tabellen bleiben erhalten. Dateibereiche werden zuerst in einen Staging-Ordner neben dem Bereich kopiert (nur Whitelist-Pfade, keine Symlinks) und dann per `rename` getauscht; der bisherige Ordner bleibt als `<bereich>.pre-restore-<zeit>` liegen und wird nie automatisch gelöscht. Ohne Wartungsmodus (`--no-maintenance-check` hebt nur diese Prüfung auf) oder bei laufender Sicherung wird nicht geschrieben.

> [!NOTE]
> Nach einem Restore sind der Stand von Datenbank *und* Sitzungen/Passwort-Resets zurückgesetzt. Löschungen nach dem Backup-Zeitpunkt (Betroffenenrechte, Art. 17 DSGVO) müssen nachgezogen werden – siehe Datenschutz.

## Sicherheitsmodell

| Thema | Umsetzung |
|---|---|
| Zugriff | nur Rolle **Administration**, serverseitig in jeder Route (`requireRole`); alle POST-Aktionen mit CSRF-Token |
| Step-up | Schlüssel, Ziele, Zeitplan/Aufbewahrung, Cron-Token und Löschen verlangen das **aktuelle Passwort**; 5 Fehlversuche je Stunde sperren die Bestätigung (auch bei richtigem Passwort) |
| Verschlüsselung | `crypto_box_seal` für den Dateischlüssel je Teil (bis zu 4 Empfänger), `secretstream` (XChaCha20-Poly1305) in 64-KiB-Blöcken, AAD = SHA-256 des Kopfes: Abschneiden, Anhängen, Tauschen und Kopfänderungen werden erkannt |
| Authentizität | Ed25519-Signatur über das Manifest; der erwartete Signer wird beim Prüfen angegeben |
| Server kennt | nur **öffentliche** Empfängerschlüssel; den Signaturschlüssel und Zielpasswörter nur **verschlüsselt** (libsodium-secretbox, Schlüssel aus `app.key`) |
| Pfade | Dateibereiche nur aus einer Whitelist in der `config.php`; `config/`, `storage/`, `src/` u. a. nie erlaubt; `config.php`, `.env`, `.git` nie im Backup; Pfade im Paket nach fester Regex (Zip-Slip-Schutz); Backup-Ordner `0700`, Teile `0600`; `realpath`/Symlink-Prüfung |
| FTPS | TLS ≥ 1.2, Zertifikats- **und** Hostnamenprüfung, kein Klartext-FTP, `CURLOPT_FTP_SKIP_PASV_IP`, nur FTP/FTPS-Protokolle, keine Weiterleitungen, Port-Allowlist (21, 990), Pfad ohne `..`/Anführungszeichen/Steuerzeichen (keine QUOTE-Injektion) |
| SSRF | Zielhost nur öffentliche Adressen (kein localhost, Privatnetz, Link-Local/Metadaten); die geprüften IPs werden per `CURLOPT_RESOLVE` festgeschrieben (kein Rebinding). Ziele im eigenen Netz nur bewusst per `backup.allow_private_hosts` in der `config.php` |
| Fehler | nur Kataloge: keine Pfade, Hostnamen, Zugangsdaten oder curl-Texte in Datenbank, Oberfläche, Audit oder Log |
| Audit | `backup.requested/started/completed/failed/cancelled`, `backup.settings_changed`, `backup.key_changed`, `backup.destination_*`, `backup.cron_token_created`, `backup.downloaded`, `backup.deleted`, `backup.retention_deleted`, `backup.restored`, `backup.stepup_failed` – nur IDs, Zähler, Fingerabdrücke |

**Nicht** enthalten (bewusst, siehe Grenzen): eine Abkühlphase für neue Schlüssel/Ziele und Sicherheitsmails bei Änderungen. Wer die Sitzung eines Administrators samt Passwort hat, kann einen eigenen öffentlichen Schlüssel eintragen. Schützen Sie Administrationskonten (starkes Passwort, kurze Sitzungen) und beobachten Sie das Audit-Log.

## Datenschutz (DSGVO)

- Backups enthalten **personenbezogene Daten** (Konten, Inhalte, ggf. private Profile). Sie sind verschlüsselt, bleiben aber personenbezogene Daten im Sinne der DSGVO.
- **Aufbewahrung gehört ins Löschkonzept:** Daten, die Sie löschen, bleiben bis zum Ablauf der Aufbewahrung (Anzahl × Intervall, höchstens das Höchstalter) in älteren Backups. Dokumentieren Sie diese Frist.
- **Betroffenenrechte nach einem Restore:** Löschungen und Auskünfte, die nach dem Backup-Zeitpunkt erfolgten, müssen nachgezogen werden.
- **Externe Ziele** (Hosting-Konto, Rechenzentrum, später Cloud-Dienste): Auftragsverarbeitungsvertrag (AVV) und Standort klären. Die Verschlüsselung mit Ihrem öffentlichen Schlüssel senkt das Risiko, ersetzt aber keine rechtliche Prüfung.
- Das Modul erhebt selbst keine neuen personenbezogenen Daten; es speichert Zeitpunkte, Zähler, Fingerabdrücke und Fehlercodes sowie die Audit-Einträge (Wer hat wann was ausgelöst).

## Konfiguration (`config/config.php`, Schlüssel `backup`)

Alles optional; nur per Dateizugriff änderbar. Vorlage in `config/config.example.php`.

| Schlüssel | Standard | Bedeutung |
|---|---|---|
| `areas` | `uploads`, `custom-assets` | freigegebene Dateibereiche (Name ⇒ Pfad) |
| `exclude_tables` | `[]` | zusätzlich nie gesicherte Tabellen (immer ausgeschlossen: `backup_*`, `login_attempts`, `password_resets`, `password_reset_requests`) |
| `tick_seconds` | 100 | Arbeitszeit je Cron-Aufruf (20–600 s) |
| `min_free_bytes` | 256 MiB | freizuhaltender Speicherplatz |
| `max_run_hours` | 24 | Höchstdauer eines Laufs |
| `max_attempts` | 5 | Versuche je Upload/Löschung |
| `ftp_allowed_ports` | 21, 990 | erlaubte FTPS-Ports |
| `upload_chunk_bytes` | 8 MiB | Abschnitt je Upload-Schritt |
| `allow_private_hosts` | `false` | Ziele im privaten Netz erlauben (nur bewusst) |

## Paketformat MGDB1

Verschlüsselte Teile `mgdbk_<JJJJMMTT>T<HHMMSS>Z_<db|full>_<uuid8>_<nnn>_<db|files|manifest>.mgdb` in einem Ordner gleichen Namens. Kopf: Kennung `MGDB1`, Version, Teilart, Lauf-UUID, Teilnummer, Empfänger (Fingerabdruck + versiegelter Schlüssel), Stream-Header; danach Blöcke `[u32 Länge][Chiffre]`. Teil `manifest`: `{"manifest":"<base64 JSON>","sig":"<base64 Ed25519>"}` mit App-Version, Migrationsliste, Tabellen und Zeilen, Dateibereichen, Empfängern, Teilen (Größe, SHA-256) und Signer.

**Kompatibilität:** Das Format ist dasselbe wie im MGD-Support-Hub (libsodium `secretstream` + `crypto_box_seal`, Ed25519-signiertes Manifest). Datenbank-Backups (`db`) des Builders lassen sich mit dem Werkzeug dieses Projekts prüfen und entschlüsseln (im Test belegt). **Abweichung:** Datei-Backups (`full`) verwenden freie Bereichsnamen und Pfade (Obermenge der Hub-Pfadregel `(uploads|erp)/<zahl>/<zahl>/<uuid>`); das Hub-Werkzeug weist solche Pakete mit `bad_path` ab, das mitgelieferte Werkzeug liest beide Formen. Ein Schlüssel-Teil (`keys`) wird vom Builder nicht geschrieben (`keys.included = false`); `config.php` und der `app.key` werden nie gesichert.

## Erweiterung: weitere Ziele

Das Interface `MGD\Backup\Destinations\DestinationDriver` (`test`, `beginRun`, `upload`, `verify`, `delete`, `deleteFolder`) ist die einzige Schnittstelle zu einem Ziel. Weitere Treiber melden sich über `DriverFactory::register('typ', fn (array $destination) => …)` an (Typkennung `^[a-z0-9]{2,8}$`, eingebaute Typen sind nicht überschreibbar). Externe Dienste (Cloud-Speicher mit OAuth) gehören **nicht** in dieses Repository, sondern als Module ins Marketplace-Repository. Ein Treiber darf nur über gespeicherte Referenzen löschen (siehe `FtpDriver::assertPartRef`) und keine Geheimnisse in Ausnahmen schreiben.

## Fehlercodes

| Code | Bedeutung / Maßnahme |
|---|---|
| `insufficient_space` | zu wenig freier Platz – Platz schaffen oder Dateibereiche verkleinern |
| `key_missing` | kein öffentlicher Schlüssel eingetragen |
| `no_destination_enabled` | kein aktives Ziel |
| `backup_already_active` | es läuft schon eine Sicherung |
| `signing_key_unreadable` | `app.key` wurde geändert – der Signaturschlüssel ist nicht mehr lesbar |
| `auth_failed`, `tls_failed`, `connection_failed`, `timeout` | FTPS-Ziel: Zugangsdaten, TLS-Zertifikat/Modus, Erreichbarkeit |
| `host_blocked`, `port_not_allowed` | Ziel verletzt die SSRF-/Port-Regeln |
| `upload_failed`, `verify_failed`, `remote_delete_failed` | Hochladen/Prüfen/Löschen am Ziel fehlgeschlagen |
| `file_changed` | eine Datei änderte sich während der Sicherung (Lauf wiederholen) |
| `timeout_run` | Lauf länger als `max_run_hours` |
| `database_error`, `io_error`, `format_error`, `path_invalid`, `internal_error` | technische Fehler; Details nur im Server-Log (ohne Geheimnisse) |

## Grenzen und offene Punkte

- **Cron:** Läuft der Cronjob nicht mehr, merkt es niemand, außer Sie schauen auf die Statusseite oder überwachen sie extern.
- **Dateiscan:** Der Scan hat kein Zeitbudget; bei sehr vielen Dateien Bereiche verkleinern oder Ausschlüsse setzen.
- **Speicherbedarf:** Erst werden alle Teile lokal geschrieben, dann hochgeladen (kein Gegendruck). Planen Sie Platz für ein komplettes Backup ein.
- **Konsistenz:** Dateien und Datenbank werden nicht atomar gemeinsam gesichert; zwischen Dump und Dateiphase gelöschte Dateien fehlen (`skipped_missing`).
- **Ransomware:** Ein kompromittierter Server kann mit gespeicherten Zugangsdaten Remote-Backups löschen. Nutzen Sie ein Ziel mit serverseitigen Snapshots/Versionen oder laden Sie regelmäßig manuell herunter.
- **Keine Benachrichtigungen:** Es werden keine E-Mails bei Fehlern versendet; das Backoffice zeigt Status und Fehler.
- **Kein Rate-Limit** für den Download (nur Administration, Audit-Eintrag je Download).
- Der Starter kennt keine 2FA; das Step-up-Passwort ist der zusätzliche Schutz.

## Tests

```bash
# Ohne Datenbank (SQLite im Speicher), alle Format-, Lauf-, FTP-, Sicherheits- und Restore-Bausteine:
php templates/php-mysql-starter/modules/backup/tests/run.php
# Optional: Kompatibilität zum Hub-Werkzeug
MGD_HUB_TOOL=/pfad/zu/mgd-backup.php php templates/php-mysql-starter/modules/backup/tests/run.php hub
# End-to-End gegen den echten Starter und eine leere MySQL/MariaDB-Testdatenbank (lokaler php -S):
MGD_TEST_DB_HOST=127.0.0.1 MGD_TEST_DB_PORT=3306 MGD_TEST_DB_NAME=mgd_backup_e2e MGD_TEST_DB_USER=mgd MGD_TEST_DB_PASSWORD=mgdpass \
  php templates/php-mysql-starter/modules/backup/tests/e2e.php
```

`npm test` führt die PHP-Tests ohne Datenbank automatisch mit aus, sofern PHP mit `sodium`, `zlib`, `curl` und `pdo_sqlite` verfügbar ist (sonst werden sie übersprungen).

Siehe auch: [Lizenz, Label, Whitelabel und Module](25-Lizenz-Label-Whitelabel-und-Module.md) · [Betrieb, Staging, Deployment, Backup und Monitoring](11-Betrieb-Staging-Deployment-Backup-und-Monitoring.md) · [Datenschutz und Compliance](07-Datenschutz-und-Compliance.md)
