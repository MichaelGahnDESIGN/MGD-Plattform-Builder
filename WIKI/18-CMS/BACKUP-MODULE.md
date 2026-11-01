# Backup module (starter module `backup`)

The `backup` module ships with the PHP/MySQL starter in `templates/php-mysql-starter/modules/backup/`. It backs up the **database and selected files, encrypted**, on a schedule or on demand. It runs on plain shared hosting: pure PHP, one MySQL/MariaDB database, **no** Node, **no** Composer, **no** `mysqldump`, **no** `exec()`, **no** queue worker. A cron job advances each run in small time slices. German version: [29 · Backup-Modul](../30-Backup-Modul.md).

> [!IMPORTANT]
> A backup is only proven once a restore has been tested ([Backup & Restore](../06-OPERATIONS/BACKUP-RESTORE.md)). Keep the private key on **two separate media** outside the server. Without it every backup is unreadable.

## Scope

| Does | Deliberately does not |
|---|---|
| Back up the database through PDO (keyset pagination, NDJSON, gzip), consistent via snapshot when one call is enough | Restore in the browser (command line only) |
| Back up files from **released areas**, with exclusion patterns | Accept arbitrary paths from the back office |
| Public-key encryption (libsodium), signed manifest | Generate or store the private key |
| Destinations: **local** (authenticated download) and **FTPS** over `ext-curl` | Plain FTP, SFTP, Dropbox, Google Drive or other services (later as marketplace modules through the driver interface) |
| Interval, retention, maximum age, free-space check, cancel, resume | Incremental file backups (every `full` run backs up all selected files) |

## Requirements

- PHP ≥ 8.2 with `sodium`, `zlib`, `pdo_mysql`; `curl` for FTPS.
- A cron job at the host (HTTP URL **or** PHP CLI).
- Write access to `storage/backups/` (blocked from the web by `.htaccess`; on Nginx and similar block `storage/` yourself – the parts are encrypted but do not belong on the web).

## Setup in seven steps

1. **Enable the module:** Back office › Settings › Modules › "Backup" › Enable. The migration creates six `backup_*` tables.
2. **Create the key pair on your own computer** (not on the server):
   ```bash
   php modules/backup/tools/mgd-backup.php keygen
   ```
   The command asks for a passphrase (at least 12 characters, Argon2id) and writes the private key to `~/.mgd-backup-keys/primary.key` (mode 0600). Only the **fingerprint** and the **public key line** (`mgdbk-pk1:…`) are printed. Optionally create a second key as emergency key (`--name=emergency`). Store both private files in different places.
3. **Enter the public key:** Backup › Keys. Format and checksum are validated; your current password is required.
4. **Write down the signing fingerprint:** On the first run the server creates an Ed25519 signing key (stored encrypted in the database). Its fingerprint is shown under Backup › Keys. Note it – it lets you verify later that a backup really came from your server.
5. **Add a destination:** Backup › Destinations.
   - *Local:* parts are kept in `storage/backups/mgdbk/`; download in the back office.
   - *FTPS:* host name, port 21 (explicit TLS) or 990 (implicit), user, password, target folder. The password is stored encrypted and never shown. A remote destination only becomes active after **"Test connection"** succeeded.
6. **Set schedule, retention and file areas** (Backup › Schedule and retention).
7. **Set up the cron job** (next section), try a first run with "Back up now", then **practice a restore**.

## Cron job

Every call works at most `backup.tick_seconds` (default 100 s, capped by `max_execution_time`) and continues a running backup. Every 5 to 15 minutes is enough.

**CLI (preferred, no secret in a URL):**

```text
php /path/to/project/modules/backup/tools/cron.php
```

Further flags: `--status` (show state), `--enqueue=db|full` (also queue a run).

**HTTP (cron jobs that only call URLs):** Backup › Cron job › "Create new token". The token is shown **once** and stored only as SHA-256. The URL is `https://YOUR-DOMAIN/backup-cron/<TOKEN>`. Wrong tokens return 404; after 10 failed attempts within 15 minutes the endpoint answers 429. Without a token the endpoint does not exist. URLs end up in host access logs – replace the token if in doubt.

The status page shows the last cron call and warns if it is more than one day ago.

## Lifecycle of a run

`queued → dumping_db → (full: scanning_files → packing_files) → finalizing → uploading → completing`

- The run is created (manual, schedule or CLI) and processed piece by piece by the cron calls. State lives in the database.
- **Parts:** database and files go into encrypted parts (default 64 MB, 8–512 MB). If a call stops in the middle of a part, the half-written file is discarded and rewritten from the stored cursor.
- **Consistency:** if one call is enough for the whole database it is read in a consistent view (`START TRANSACTION WITH CONSISTENT SNAPSHOT`, separate second connection; `snapshot`). Otherwise it is table-by-table consistent (`per_tick`, flagged on the run).
- **Parallel runs:** file system lock (`flock`) plus a unique `active_slot` in the database – at most one backup at a time.
- **Free space:** checked before the start (database) and after the file scan (including file sizes) against `min_free_bytes`.
- **Uploads:** remote destinations receive the parts in order, the manifest last. FTPS uploads in chunks and resumes after an error. At most 5 attempts per part with back-off (5 min · 2ⁿ, max. 2 h); then the destination counts as failed for this run and already uploaded parts are cleaned up. For remote-only setups a part is deleted locally once it is everywhere.
- **Result:** `completed`, `partial` (e.g. skipped files or a destination failed) or `failed`. Errors are stored only as **catalog codes** (table below), never as raw messages.

### Retention

Per destination the newest `keep_count` backups **per kind** (`db`/`full`) are kept, plus a global maximum age (7–365 days). **The newest successful backup per destination and kind always stays**, even beyond the maximum age. Deletion works only through **stored references** (path/file name from the database), never from a directory listing, and only for names matching `mgdbk_…`; foreign files in the target folder are never touched. Failed deletions are retried with back-off.

## Download and verification

Backup › History › Details lists every part. The download is an **authenticated endpoint** (administration role): the file name never comes from the request but from the database and is checked against the naming regex; the path must lie below the backup folder (no symlink, no `..`); the response carries `Content-Disposition: attachment`, `X-Content-Type-Options: nosniff`, `Cache-Control: no-store, private` and `Content-Security-Policy: sandbox`.

Verify and decrypt on **your own computer**:

```bash
php modules/backup/tools/mgd-backup.php verify  <folder-with-parts> --key-file=<key> --signer=<fingerprint>
php modules/backup/tools/mgd-backup.php decrypt <folder-with-parts> --key-file=<key> --out=<new-folder> --signer=<fingerprint>
```

`verify` decrypts as a stream and checks every block (tampering, truncation, appended bytes), the manifest signature, size and SHA-256 of every part, and row/file counts. Without `--signer` (or `--accept-unverified-signer`) the tool refuses, so that a forged backup with a foreign signing key is noticed. `decrypt` writes `db.ndjson.gz` and the file tree in plaintext (modes 0600/0700).

## Restore (command line only)

1. Fetch the parts from the destination or download them; decrypt them on your own computer with `decrypt` (see above). **The private key never leaves your computer.**
2. Bring the plaintext folder to the server (e.g. via FTPS) and switch on **maintenance mode**.
3. Dry run – writes nothing, shows tables, rows, areas and the confirmation code:
   ```bash
   php modules/backup/tools/restore.php <plaintext-folder>
   ```
4. Apply:
   ```bash
   php modules/backup/tools/restore.php <plaintext-folder> --confirm=<code> [--only=db|files]
   ```

Safeguards: the dry run reads the entire dump before anything is written (structure, row counts, every table and column must exist in the target, no `backup_*` tables allowed, the migrations in the backup must match the installation). The database is replaced in **one transaction** (rollback on error); the `backup_*` tables stay untouched. File areas are first copied to a staging folder next to the area (allow-listed paths only, no symlinks) and then swapped with `rename`; the previous folder stays as `<area>.pre-restore-<time>` and is never deleted automatically. Without maintenance mode (`--no-maintenance-check` lifts only this check) or while a backup is running nothing is written.

> [!NOTE]
> After a restore the database, sessions and password resets are back at the backup time. Deletions made after the backup (data subject rights, Art. 17 GDPR) must be repeated – see privacy below.

## Security model

| Topic | Implementation |
|---|---|
| Access | **administration role** only, checked server-side in every route (`requireRole`); every POST action with a CSRF token |
| Step-up | keys, destinations, schedule/retention, cron token and deleting require the **current password**; 5 failed attempts per hour lock the confirmation (even with the right password) |
| Encryption | `crypto_box_seal` for the per-part file key (up to 4 recipients), `secretstream` (XChaCha20-Poly1305) in 64 KiB blocks, AAD = SHA-256 of the header: truncation, appending, swapping and header changes are detected |
| Authenticity | Ed25519 signature over the manifest; the expected signer is passed when verifying |
| Server knows | only **public** recipient keys; the signing key and destination passwords only **encrypted** (libsodium secretbox, key derived from `app.key`) |
| Paths | file areas only from an allow-list in `config.php`; `config/`, `storage/`, `src/` etc. never allowed; `config.php`, `.env`, `.git` never in the backup; paths inside the package follow a fixed regex (zip-slip protection); backup folder `0700`, parts `0600`; `realpath`/symlink checks |
| FTPS | TLS ≥ 1.2, certificate **and** host name verification, no plain FTP, `CURLOPT_FTP_SKIP_PASV_IP`, FTP/FTPS protocols only, no redirects, port allow-list (21, 990), paths without `..`/quotes/control characters (no QUOTE injection) |
| SSRF | destination host must resolve to public addresses only (no localhost, private network, link-local/metadata); the checked IPs are pinned with `CURLOPT_RESOLVE` (no rebinding). Targets in your own network only deliberately via `backup.allow_private_hosts` in `config.php` |
| Errors | catalog codes only: no paths, host names, credentials or curl text in the database, UI, audit or logs |
| Audit | `backup.requested/started/completed/failed/cancelled`, `backup.settings_changed`, `backup.key_changed`, `backup.destination_*`, `backup.cron_token_created`, `backup.downloaded`, `backup.deleted`, `backup.retention_deleted`, `backup.restored`, `backup.stepup_failed` – IDs, counters and fingerprints only |

**Not included** (deliberately, see limits): a cool-down period for new keys/destinations and security e-mails on changes. Anyone holding an administrator's session and password can enter their own public key. Protect administrator accounts (strong password, short sessions) and watch the audit log.

## Privacy (GDPR)

- Backups contain **personal data** (accounts, content, possibly private profiles). They are encrypted but remain personal data under the GDPR.
- **Retention belongs in your deletion concept:** data you delete stays in older backups until retention expires (count × interval, at most the maximum age). Document that period.
- **Data subject rights after a restore:** deletions and access requests made after the backup time must be repeated.
- **External destinations** (hosting account, data center, later cloud services): clarify the data processing agreement (DPA) and location. Encrypting with your public key lowers the risk but does not replace a legal review.
- The module collects no new personal data itself; it stores times, counters, fingerprints and error codes plus audit entries (who triggered what, when).

## Configuration (`config/config.php`, key `backup`)

All optional; changeable only through file access. Template in `config/config.example.php`.

| Key | Default | Meaning |
|---|---|---|
| `areas` | `uploads`, `custom-assets` | released file areas (name ⇒ path) |
| `exclude_tables` | `[]` | additional tables never backed up (always excluded: `backup_*`, `login_attempts`, `password_resets`, `password_reset_requests`) |
| `tick_seconds` | 100 | working time per cron call (20–600 s) |
| `min_free_bytes` | 256 MiB | free space to keep |
| `max_run_hours` | 24 | maximum duration of a run |
| `max_attempts` | 5 | attempts per upload/deletion |
| `ftp_allowed_ports` | 21, 990 | allowed FTPS ports |
| `upload_chunk_bytes` | 8 MiB | chunk per upload step |
| `allow_private_hosts` | `false` | allow private-network targets (deliberately only) |

## Package format MGDB1

Encrypted parts `mgdbk_<YYYYMMDD>T<HHMMSS>Z_<db|full>_<uuid8>_<nnn>_<db|files|manifest>.mgdb` in a folder of the same name. Header: magic `MGDB1`, version, part kind, run UUID, part number, recipients (fingerprint + sealed key), stream header; then blocks `[u32 length][ciphertext]`. Part `manifest`: `{"manifest":"<base64 JSON>","sig":"<base64 Ed25519>"}` with app version, migration list, tables and rows, file areas, recipients, parts (size, SHA-256) and signer.

**Compatibility:** the format is the same as in the MGD-Support-Hub (libsodium `secretstream` + `crypto_box_seal`, Ed25519-signed manifest). Database backups (`db`) created by the builder can be verified and decrypted with that project's tool (proven in the tests). **Deviation:** file backups (`full`) use free area names and paths (a superset of the hub's path rule `(uploads|erp)/<number>/<number>/<uuid>`); the hub tool rejects such packages with `bad_path`, the bundled tool reads both forms. The builder never writes a `keys` part (`keys.included = false`); `config.php` and `app.key` are never backed up.

## Extending: more destinations

The interface `MGD\Backup\Destinations\DestinationDriver` (`test`, `beginRun`, `upload`, `verify`, `delete`, `deleteFolder`) is the only contact point to a destination. Further drivers register through `DriverFactory::register('type', fn (array $destination) => …)` (type id `^[a-z0-9]{2,8}$`; built-in types cannot be overridden). External services (cloud storage with OAuth) do **not** belong in this repository but into the marketplace repository as modules. A driver must delete only through stored references (see `FtpDriver::assertPartRef`) and must not put secrets into exceptions.

## Error codes

| Code | Meaning / action |
|---|---|
| `insufficient_space` | not enough free space – free some or shrink the file areas |
| `key_missing` | no public key entered |
| `no_destination_enabled` | no active destination |
| `backup_already_active` | a backup is already running |
| `signing_key_unreadable` | `app.key` changed – the signing key can no longer be read |
| `auth_failed`, `tls_failed`, `connection_failed`, `timeout` | FTPS destination: credentials, TLS certificate/mode, reachability |
| `host_blocked`, `port_not_allowed` | destination violates the SSRF/port rules |
| `upload_failed`, `verify_failed`, `remote_delete_failed` | uploading/verifying/deleting at the destination failed |
| `file_changed` | a file changed during the backup (repeat the run) |
| `timeout_run` | run exceeded `max_run_hours` |
| `database_error`, `io_error`, `format_error`, `path_invalid`, `internal_error` | technical errors; details only in the server log (without secrets) |

## Limits and open points

- **Cron:** if the cron job stops nobody notices unless you look at the status page or monitor it externally.
- **File scan:** the scan has no time budget; with very many files shrink the areas or set exclusions.
- **Disk space:** all parts are written locally first, then uploaded (no back-pressure). Plan space for a complete backup.
- **Consistency:** files and database are not backed up atomically together; files deleted between the dump and the file phase are missing (`skipped_missing`).
- **Ransomware:** a compromised server can delete remote backups with the stored credentials. Use a destination with server-side snapshots/versions or download regularly by hand.
- **No notifications:** no e-mails are sent on errors; the back office shows status and errors.
- **No rate limit** on downloads (administration only, one audit entry per download).
- The starter has no 2FA; the step-up password is the additional protection.

## Tests

```bash
# Without a database (SQLite in memory): format, run, FTP, security and restore building blocks:
php templates/php-mysql-starter/modules/backup/tests/run.php
# Optional: compatibility with the hub tool
MGD_HUB_TOOL=/path/to/mgd-backup.php php templates/php-mysql-starter/modules/backup/tests/run.php hub
# End to end against the real starter and an empty MySQL/MariaDB test database (local php -S):
MGD_TEST_DB_HOST=127.0.0.1 MGD_TEST_DB_PORT=3306 MGD_TEST_DB_NAME=mgd_backup_e2e MGD_TEST_DB_USER=mgd MGD_TEST_DB_PASSWORD=mgdpass \
  php templates/php-mysql-starter/modules/backup/tests/e2e.php
```

`npm test` also runs the database-free PHP tests when PHP with `sodium`, `zlib`, `curl` and `pdo_sqlite` is available (otherwise they are skipped).

See also: [Licensing, label, white-label and modules](LICENSING.md) · [Backup & Restore](../06-OPERATIONS/BACKUP-RESTORE.md) · [Privacy by Design](../04-PRIVACY/PRIVACY-BY-DESIGN.md)
