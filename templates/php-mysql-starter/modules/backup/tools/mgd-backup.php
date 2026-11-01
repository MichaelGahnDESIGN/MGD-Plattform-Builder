<?php

declare(strict_types=1);

/**
 * mgd-backup.php - Standalone-Werkzeug fuer das Backup-Modul (MGD-Plattform-Builder), CLI auf dem eigenen Rechner.
 * Benoetigt nur PHP 8.3+ mit ext-sodium und ext-zlib, kein Composer: die Format-Klassen aus
 * modules/backup/src/Format/ werden ueber einen Mini-Autoloader eingebunden.
 *
 *   php modules/backup/tools/mgd-backup.php keygen [--name=primary] [--dir=<ordner>] [--force] [--passphrase-stdin | --no-passphrase]
 *       Erzeugt ein X25519-Schluesselpaar. Der PRIVATE Schluessel geht nach <ordner>/<name>.key (Standard-Ordner
 *       ~/.mgd-backup-keys, Ordner 0700, Datei 0600, mit Passphrase: Argon2id SENSITIVE + secretbox). Die Passphrase
 *       braucht mindestens 12 Zeichen; ohne Terminal und ohne --passphrase-stdin oder mit leerer Passphrase bricht
 *       keygen ab, ausser es steht ausdruecklich --no-passphrase da (nicht empfohlen). Angezeigt werden nur der
 *       oeffentliche Schluessel und der Fingerabdruck, nie der private Schluessel. Bricht ab, wenn die Datei
 *       existiert (ausser --force).
 *   php modules/backup/tools/mgd-backup.php info <teildatei>
 *       Zeigt Kopf, Empfaenger und Fingerabdruecke einer Teildatei (ohne Schluessel).
 *   php modules/backup/tools/mgd-backup.php verify <teildatei-oder-ordner> --key-file=<datei> [--signer=<fp> | --accept-unverified-signer] [--passphrase-stdin]
 *       Entschluesselt streamend und prueft alles (Authentifizierung jedes Blocks, Abschneiden, Struktur). Bei einem
 *       Ordner zusaetzlich Manifest-Signatur, Groesse und SHA-256 jedes Teils, Zeilen- und Dateizahlen.
 *       Ausgabe "OK" (Exit 0) oder "FEHLER: <code>" auf stderr (Exit 1).
 *   php modules/backup/tools/mgd-backup.php decrypt <ordner> --key-file=<datei> --out=<ordner> [--signer=<fp> | --accept-unverified-signer] [--passphrase-stdin]
 *       Prueft wie verify und schreibt dann nach --out (neu oder leer): db.ndjson.gz, den Dateibaum je Bereich
 *       (Klartext, Rechte 0600) und ggf. keys.env (0600). Nie auf stdout. Bei einem Fehler wird nichts uebrig
 *       gelassen.
 *
 * Signer-Pflicht (verify eines Ordners oder Manifests, decrypt): Der erwartete Signer-Fingerabdruck (16 Hex) kommt aus
 * --signer=<fp>, sonst aus der Datei <name>.signer neben dem Schluessel bzw. in ~/.mgd-backup-keys (<name> = Dateiname
 * der Schluesseldatei ohne .key). Ohne beides bricht das Werkzeug ab, ausser es steht ausdruecklich
 * --accept-unverified-signer da: dann ist die Herkunft des Backups NICHT bestaetigt.
 *
 * Passphrase: am Terminal verdeckt abgefragt; ohne Terminal nur mit --passphrase-stdin (erste Zeile von stdin).
 * Keine Geheimnisse in Ausgaben oder Fehlertexten.
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'MGD\\Backup\\Format\\';
    if (! str_starts_with($class, $prefix)) {
        return;
    }
    $name = substr($class, strlen($prefix));
    if (preg_match('/^[A-Za-z0-9]+$/D', $name) !== 1) {
        return;
    }
    $file = __DIR__.'/../src/Format/'.$name.'.php';
    if (is_file($file)) {
        require $file;
    }
});

use MGD\Backup\Format\BackupFormat;
use MGD\Backup\Format\BackupFormatException;
use MGD\Backup\Format\DbStreamReader;
use MGD\Backup\Format\FileRecordAssembler;
use MGD\Backup\Format\FileRecordReader;
use MGD\Backup\Format\ManifestCodec;
use MGD\Backup\Format\PartReader;
use MGD\Backup\Format\RecipientKey;
use MGD\Backup\Format\SecretKeyFile;

/** Fehler mit fester, geheimnisfreier deutscher Meldung (Exit 1). */
final class MgdCliError extends RuntimeException {}

final class MgdBackupCli
{
    public const KEY_DIR_NAME = '.mgd-backup-keys';

    private const MIN_PASSPHRASE = 12;

    private const VALUE_OPTIONS = ['name', 'dir', 'key-file', 'out', 'signer'];

    private const FLAG_OPTIONS = ['force', 'passphrase-stdin', 'no-passphrase', 'accept-unverified-signer', 'help'];

    private const USAGE = "Aufruf:\n"
        ."  php modules/backup/tools/mgd-backup.php keygen [--name=primary] [--dir=<ordner>] [--force] [--passphrase-stdin | --no-passphrase]\n"
        ."  php modules/backup/tools/mgd-backup.php info <teildatei>\n"
        ."  php modules/backup/tools/mgd-backup.php verify <teildatei-oder-ordner> --key-file=<datei> [--signer=<fp> | --accept-unverified-signer] [--passphrase-stdin]\n"
        ."  php modules/backup/tools/mgd-backup.php decrypt <ordner> --key-file=<datei> --out=<ordner> [--signer=<fp> | --accept-unverified-signer] [--passphrase-stdin]\n";

    /** @param  list<string>  $argv */
    public static function main(array $argv): int
    {
        if (PHP_SAPI !== 'cli') {
            return 2;
        }
        if (! extension_loaded('sodium') || ! extension_loaded('zlib')) {
            fwrite(STDERR, "FEHLER: PHP-Erweiterung sodium oder zlib fehlt.\n");

            return 1;
        }

        try {
            [$positional, $options] = self::parseArgs(array_slice($argv, 1));
            $command = array_shift($positional) ?? '';
            if ($command === '' || isset($options['help'])) {
                fwrite(STDERR, self::USAGE);

                return 2;
            }

            switch ($command) {
                case 'keygen':
                    self::expectPositional($positional, 0);

                    return self::keygen($options);
                case 'info':
                    self::expectPositional($positional, 1);

                    return self::info($positional[0]);
                case 'verify':
                    self::expectPositional($positional, 1);

                    return self::verify($positional[0], $options);
                case 'decrypt':
                    self::expectPositional($positional, 1);

                    return self::decrypt($positional[0], $options);
                default:
                    fwrite(STDERR, self::USAGE);

                    return 2;
            }
        } catch (InvalidArgumentException $e) {
            fwrite(STDERR, 'FEHLER: '.$e->getMessage()."\n".self::USAGE);

            return 2;
        } catch (MgdCliError $e) {
            fwrite(STDERR, 'FEHLER: '.$e->getMessage()."\n");

            return 1;
        } catch (BackupFormatException $e) {
            fwrite(STDERR, 'FEHLER: '.$e->errorCode."\n");

            return 1;
        } catch (Throwable) {
            // Keine Ausnahmetexte ausgeben: sie koennten Pfade oder Inhalte enthalten.
            fwrite(STDERR, "FEHLER: internal_error\n");

            return 1;
        }
    }

    // ------------------------------------------------------------------------------------------ keygen

    /** @param  array<string,string|true>  $options */
    private static function keygen(array $options): int
    {
        $name = self::stringOption($options, 'name') ?? 'primary';
        if (preg_match('/^[A-Za-z0-9_-]{1,40}$/D', $name) !== 1) {
            throw new InvalidArgumentException('--name: 1-40 Zeichen aus A-Z a-z 0-9 _ -');
        }
        $customDir = self::stringOption($options, 'dir');
        $dir = rtrim($customDir ?? self::defaultKeyDir(), '/');
        if ($dir === '') {
            throw new InvalidArgumentException('--dir ist leer.');
        }
        $file = $dir.'/'.$name.'.key';
        $force = isset($options['force']);
        if ((file_exists($file) || is_link($file)) && ! $force) {
            fwrite(STDERR, "FEHLER: Es existiert schon ein Schluessel ($file). Ohne Not nicht ersetzen: alte Backups waeren dann nicht mehr lesbar. Mit --force nur, wenn die Datei gesichert ist.\n");

            return 1;
        }

        $passphrase = self::askNewPassphrase(isset($options['passphrase-stdin']), isset($options['no-passphrase']));

        $dirExisted = is_dir($dir);
        $old = umask(0077);
        try {
            if (! $dirExisted && ! mkdir($dir, 0700, true) && ! is_dir($dir)) {
                fwrite(STDERR, "FEHLER: Schluesselordner nicht anlegbar.\n");

                return 1;
            }
        } finally {
            umask($old);
        }
        if (! $dirExisted || $customDir === null) {
            chmod($dir, 0700);
        } elseif ((fileperms($dir) & 0077) !== 0) {
            fwrite(STDERR, "Warnung: Der Ordner $dir ist fuer andere lesbar; besser 'chmod 700' setzen.\n");
        }

        $hasPassphrase = $passphrase !== null;
        $pair = sodium_crypto_box_keypair();
        $secret = sodium_crypto_box_secretkey($pair);
        $recipient = RecipientKey::fromRaw(sodium_crypto_box_publickey($pair));
        try {
            SecretKeyFile::write($file, $secret, $passphrase);
        } finally {
            sodium_memzero($secret);
            sodium_memzero($pair);
            if ($passphrase !== null) {
                sodium_memzero($passphrase);
            }
        }

        echo "Schluesselpaar erzeugt.\n";
        echo "  Privater Schluessel (Rechte 0600): $file\n";
        echo '  Passphrase: '.($hasPassphrase ? "ja\n" : "nein (nicht empfohlen)\n");
        echo '  Fingerabdruck: '.$recipient->fingerprint()."\n";
        echo "  Oeffentlicher Schluessel (diese Zeile im Backoffice unter Backup eintragen):\n";
        echo '    '.$recipient->toText()."\n";
        echo "\n";
        echo "!!! SICHERUNG NOETIG !!!\n";
        echo "Sichere die Datei $file an ZWEI getrennten, sicheren Orten (z. B. Passwortmanager und ein\n";
        echo "verschluesselter Datentraeger an einem anderen Ort). Sie darf nie auf den Server, ins Repo, in Chats oder\n";
        echo "per Mail. Ohne diesen privaten Schluessel (und ggf. die Passphrase) sind ALLE Backups unwiederbringlich\n";
        echo "verloren. Der Fingerabdruck dient dem Vergleich im Backoffice.\n";

        return 0;
    }

    // ------------------------------------------------------------------------------------------ info

    private static function info(string $file): int
    {
        $header = PartReader::readHeader($file);
        $uuid = self::formatUuid($header['run_uuid_hex']);

        echo 'Datei: '.basename($file)."\n";
        echo 'Groesse: '.(int) filesize($file)." Byte\n";
        echo 'Format: '.BackupFormat::MAGIC.' Version '.$header['version']."\n";
        echo 'Teilart: '.BackupFormat::KIND_NAMES[$header['kind']].' ('.$header['kind'].")\n";
        echo "Run-UUID: $uuid\n";
        echo 'Teilnummer: '.$header['seq']."\n";
        echo 'Empfaenger: '.count($header['recipients'])."\n";
        foreach ($header['recipients'] as $fingerprint) {
            echo "  Fingerabdruck: $fingerprint\n";
        }
        echo 'Kopf: '.$header['head_bytes']." Byte\n";

        return 0;
    }

    // ------------------------------------------------------------------------------------------ verify / decrypt

    /** @param  array<string,string|true>  $options */
    private static function verify(string $target, array $options): int
    {
        $keyFile = self::keyFileOption($options);
        $isFolder = is_dir($target);
        // Signer-Pflicht: fuer Ordner und Manifest-Teile; bei anderen Einzelteilen gibt es keine Signatur zu pruefen.
        $needsSigner = $isFolder || PartReader::readHeader($target)['kind'] === BackupFormat::KIND_MANIFEST;
        $signer = $needsSigner ? self::requireSigner($options, $keyFile) : self::signerOption($options);
        $secret = self::loadKey($options);
        try {
            if ($isFolder) {
                $summary = self::processFolder(rtrim($target, '/'), $secret, $signer, null);
                self::printSummary($summary, null);
            } else {
                self::verifySingle($target, $secret, $signer);
            }
        } finally {
            sodium_memzero($secret);
        }

        return 0;
    }

    /** @param  array<string,string|true>  $options */
    private static function decrypt(string $folder, array $options): int
    {
        $out = self::stringOption($options, 'out');
        if ($out === null || ! is_dir($folder)) {
            throw new InvalidArgumentException('decrypt braucht einen Backup-Ordner und --out=<ordner>.');
        }
        $out = rtrim($out, '/');
        if ($out === '') {
            throw new InvalidArgumentException('--out ist leer.');
        }
        // Signer-Pflicht vor allem anderen: ohne bestaetigten Signer wird nichts angelegt.
        $signer = self::requireSigner($options, self::keyFileOption($options));

        // Ausgabeordner: neu oder leer; nur dann darf bei einem Fehler aufgeraeumt werden.
        $created = false;
        if (file_exists($out) || is_link($out)) {
            if (! is_dir($out) || is_link($out) || (scandir($out) ?: []) !== ['.', '..']) {
                throw new BackupFormatException('out_dir_not_empty');
            }
        } else {
            $old = umask(0077);
            $ok = mkdir($out, 0700, true);
            umask($old);
            if (! $ok && ! is_dir($out)) {
                throw new BackupFormatException(BackupFormatException::WRITE_FAILED);
            }
            $created = true;
        }
        chmod($out, 0700);

        $secret = self::loadKey($options);
        try {
            $summary = self::processFolder(rtrim($folder, '/'), $secret, $signer, $out);
        } catch (Throwable $e) {
            self::removeContents($out);
            if ($created) {
                @rmdir($out);
            }
            throw $e;
        } finally {
            sodium_memzero($secret);
        }
        self::printSummary($summary, $out);

        return 0;
    }

    /**
     * Prueft einen Backup-Ordner vollstaendig und schreibt dabei optional den Klartext nach $outDir.
     *
     * @return array{run_uuid:string,kind:string,signer_fp:string,signer_checked:bool,parts:int,tables:int,rows:int,files:int,file_bytes:int,keys:bool}
     */
    private static function processFolder(string $folder, string $secret, ?string $expectedSigner, ?string $outDir): array
    {
        // Teile im Ordner (nur Namen im festen Schema; alles andere, z. B. files.idx, wird ignoriert).
        $present = [];
        $manifests = [];
        foreach (scandir($folder) ?: [] as $entry) {
            if (preg_match(BackupFormat::PART_NAME_REGEX, $entry) !== 1 || is_link($folder.'/'.$entry) || ! is_file($folder.'/'.$entry)) {
                continue;
            }
            $present[$entry] = true;
            if (str_ends_with($entry, '_manifest.mgdb')) {
                $manifests[] = $entry;
            }
        }
        if ($manifests === []) {
            throw new BackupFormatException('manifest_missing');
        }
        if (count($manifests) > 1) {
            throw new BackupFormatException('manifest_ambiguous');
        }

        // Manifest entschluesseln und Signatur pruefen.
        $manifestPart = self::readPart($folder.'/'.$manifests[0], $secret, null, null, null);
        if ($manifestPart['header']['kind'] !== BackupFormat::KIND_MANIFEST || $manifestPart['data'] === null) {
            throw new BackupFormatException('part_mismatch');
        }
        $pair = json_decode($manifestPart['data'], true, 8);
        if (! is_array($pair)) {
            throw new BackupFormatException(BackupFormatException::BAD_MANIFEST);
        }
        $claimed = ManifestCodec::unverified($pair);
        $signerPublic = (string) base64_decode($claimed['signer']['public_key'], true);
        if ($expectedSigner !== null && ! hash_equals($expectedSigner, ManifestCodec::signerFingerprint($signerPublic))) {
            throw new BackupFormatException(BackupFormatException::SIGNER_MISMATCH);
        }
        $manifest = ManifestCodec::verify($pair, $signerPublic);

        $runUuidHex = str_replace('-', '', $manifest['run_uuid']);
        $runUuid = (string) hex2bin($runUuidHex);
        if (! hash_equals($runUuid, $manifestPart['header']['run_uuid'])) {
            throw new BackupFormatException(BackupFormatException::RUN_MISMATCH);
        }

        $listed = [];
        $parts = $manifest['parts'];
        usort($parts, static fn (array $a, array $b): int => $a['seq'] <=> $b['seq']);
        foreach ($parts as $part) {
            $listed[$part['name']] = true;
        }
        foreach (array_keys($present) as $name) {
            if (! isset($listed[$name]) && ! str_ends_with((string) $name, '_manifest.mgdb')) {
                throw new BackupFormatException('part_unlisted');
            }
        }

        // Ausgaben vorbereiten.
        $assembler = new FileRecordAssembler($outDir);
        $dbOut = null;
        if ($outDir !== null) {
            $old = umask(0177);
            $dbOut = @fopen($outDir.'/db.ndjson.gz', 'xb');
            umask($old);
            if ($dbOut === false) {
                throw new BackupFormatException(BackupFormatException::WRITE_FAILED);
            }
        }
        $dbSink = $dbOut === null ? null : static function (string $chunk) use ($dbOut): void {
            if (fwrite($dbOut, $chunk) !== strlen($chunk)) {
                throw new BackupFormatException(BackupFormatException::WRITE_FAILED);
            }
        };

        $tables = [];
        $keysData = null;
        $partCount = 0;
        try {
            foreach ($parts as $part) {
                $path = $folder.'/'.$part['name'];
                if (! is_file($path) || is_link($path)) {
                    throw new BackupFormatException('part_missing');
                }
                if (filesize($path) !== $part['cipher_bytes']) {
                    throw new BackupFormatException('part_size_mismatch');
                }
                if (! hash_equals($part['cipher_sha256'], (string) hash_file('sha256', $path))) {
                    throw new BackupFormatException('part_hash_mismatch');
                }

                $result = self::readPart($path, $secret, $runUuid, $assembler, $part['kind'] === 'db' ? $dbSink : null);
                $header = $result['header'];
                if (BackupFormat::KIND_NAMES[$header['kind']] !== $part['kind'] || $header['seq'] !== $part['seq']) {
                    throw new BackupFormatException('part_mismatch');
                }
                if ($result['plain_bytes'] !== $part['plain_bytes']) {
                    throw new BackupFormatException('plain_bytes_mismatch');
                }
                foreach ($result['tables'] as $table => $rows) {
                    $tables[$table] = ($tables[$table] ?? 0) + $rows;
                }
                if ($part['kind'] === 'keys') {
                    if ($keysData !== null) {
                        throw new BackupFormatException('keys_part_invalid');
                    }
                    $keysData = self::parseKeys((string) $result['data']);
                }
                $partCount++;
            }
        } finally {
            if ($dbOut !== null) {
                fclose($dbOut);
            }
        }

        // Gesamtpruefungen gegen das Manifest.
        $fileStats = $assembler->finish();
        $expectedTables = [];
        foreach ($manifest['db']['tables'] as $table) {
            $expectedTables[$table['name']] = $table['rows'];
        }
        ksort($expectedTables);
        ksort($tables);
        if ($expectedTables !== $tables) {
            throw new BackupFormatException('db_rows_mismatch');
        }
        // Bereiche aus dem Manifest und aus den Dateien muessen genau uebereinstimmen (Zaehler und Bytes).
        $areaNames = array_unique(array_merge(array_map('strval', array_keys($manifest['files']['areas'])), array_map('strval', array_keys($fileStats['areas']))));
        foreach ($areaNames as $area) {
            $expected = $manifest['files']['areas'][$area] ?? ['count' => 0, 'bytes' => 0];
            $actual = $fileStats['areas'][$area] ?? ['count' => 0, 'bytes' => 0];
            if ($expected['count'] !== $actual['count'] || $expected['bytes'] !== $actual['bytes']) {
                throw new BackupFormatException('file_count_mismatch');
            }
        }
        if ($manifest['keys']['included'] !== ($keysData !== null)) {
            throw new BackupFormatException('keys_part_invalid');
        }
        if ($outDir !== null && $keysData !== null) {
            self::writeKeysEnv($outDir.'/keys.env', $keysData);
        }

        return [
            'run_uuid' => $manifest['run_uuid'],
            'kind' => $manifest['kind'],
            'signer_fp' => $manifest['signer']['fp'],
            'signer_checked' => $expectedSigner !== null,
            'parts' => $partCount + 1,
            'tables' => count($tables),
            'rows' => array_sum($tables),
            'files' => $fileStats['files'],
            'file_bytes' => $fileStats['bytes'],
            'keys' => $keysData !== null,
        ];
    }

    /** Einzelne Teildatei: entschluesseln, Struktur pruefen. */
    private static function verifySingle(string $file, string $secret, ?string $expectedSigner): void
    {
        $assembler = new FileRecordAssembler(null);
        $result = self::readPart($file, $secret, null, $assembler, null);
        $kind = $result['header']['kind'];
        $name = BackupFormat::KIND_NAMES[$kind];

        $detail = '';
        if ($kind === BackupFormat::KIND_DB) {
            $detail = count($result['tables']).' Tabellen, '.array_sum($result['tables']).' Zeilen';
        } elseif ($kind === BackupFormat::KIND_FILES) {
            $stats = $assembler->finish(true);
            $detail = $stats['files'].' Dateien vollstaendig, '.count($assembler->openPaths()).' laufen im naechsten Teil weiter';
        } elseif ($kind === BackupFormat::KIND_KEYS) {
            self::parseKeys((string) $result['data']);
            $detail = 'Schluessel-Teil gueltig (Werte werden nicht angezeigt)';
        } else {
            $pair = json_decode((string) $result['data'], true, 8);
            if (! is_array($pair)) {
                throw new BackupFormatException(BackupFormatException::BAD_MANIFEST);
            }
            $claimed = ManifestCodec::unverified($pair);
            $public = (string) base64_decode($claimed['signer']['public_key'], true);
            if ($expectedSigner !== null && ! hash_equals($expectedSigner, ManifestCodec::signerFingerprint($public))) {
                throw new BackupFormatException(BackupFormatException::SIGNER_MISMATCH);
            }
            ManifestCodec::verify($pair, $public);
            $detail = 'Signer '.$claimed['signer']['fp'].($expectedSigner !== null ? ' (bestaetigt)' : ' (nicht gegen --signer geprueft)');
        }

        echo "OK\n";
        echo "Teil: $name, Teilnummer ".$result['header']['seq'].', '.$result['plain_bytes']." Byte Klartext\n";
        echo $detail."\n";
    }

    /**
     * Entschluesselt eine Teildatei vollstaendig und prueft ihre Struktur.
     *
     * @return array{header:array{version:int,kind:int,run_uuid:string,run_uuid_hex:string,seq:int,recipients:list<string>,head_bytes:int},plain_bytes:int,tables:array<string,int>,data:?string}
     */
    private static function readPart(string $file, string $secret, ?string $expectedRunUuid, ?FileRecordAssembler $assembler, ?Closure $plainSink): array
    {
        $reader = PartReader::open($file, $secret, $expectedRunUuid);
        $header = $reader->header();
        $plainBytes = 0;
        $counting = static function () use ($reader, &$plainBytes, $plainSink): Generator {
            foreach ($reader->chunks() as $chunk) {
                $plainBytes += strlen($chunk);
                if ($plainSink !== null) {
                    $plainSink($chunk);
                }
                yield $chunk;
            }
        };

        $tables = [];
        $data = null;
        switch ($header['kind']) {
            case BackupFormat::KIND_DB:
                foreach ((new DbStreamReader($counting()))->events() as $event) {
                    if ($event['type'] === 'end') {
                        $tables[$event['table']] = ($tables[$event['table']] ?? 0) + $event['rows'];
                    }
                }
                break;
            case BackupFormat::KIND_FILES:
                if ($assembler === null) {
                    throw new BackupFormatException(BackupFormatException::BAD_ARGUMENT);
                }
                foreach ((new FileRecordReader($counting()))->records() as $record) {
                    $assembler->accept($record);
                }
                break;
            default:
                $data = '';
                foreach ($counting() as $chunk) {
                    $data .= $chunk;
                    if (strlen($data) > BackupFormat::MAX_SMALL_PART_BYTES) {
                        throw new BackupFormatException(BackupFormatException::BAD_ARGUMENT);
                    }
                }
        }

        return ['header' => $header, 'plain_bytes' => $plainBytes, 'tables' => $tables, 'data' => $data];
    }

    /**
     * @return array<string,string> Name => Wert
     */
    private static function parseKeys(string $json): array
    {
        $data = json_decode($json, true, 4);
        $fields = BackupFormat::KEYS_FIELDS;
        if (! is_array($data) || array_diff(array_keys($data), $fields) !== [] || array_diff($fields, array_keys($data)) !== []) {
            throw new BackupFormatException('keys_part_invalid');
        }
        $out = [];
        foreach ($fields as $field) {
            $value = $data[$field];
            if (is_array($value) && array_is_list($value) && count($value) <= 100) {
                foreach ($value as $item) {
                    if (! is_string($item)) {
                        throw new BackupFormatException('keys_part_invalid');
                    }
                }
                $value = implode(',', $value);
            }
            if ($value === null) {
                $value = '';
            }
            if (! is_string($value) || preg_match('/^[A-Za-z0-9+\/=:,._@-]{0,4096}$/D', $value) !== 1) {
                throw new BackupFormatException('keys_part_invalid');
            }
            $out[$field] = $value;
        }

        return $out;
    }

    /** @param  array<string,string>  $keys */
    private static function writeKeysEnv(string $path, array $keys): void
    {
        $lines = ['# Aus dem Backup entschluesselt (mgd-backup.php decrypt). Geheim halten, nie committen.'];
        foreach ($keys as $name => $value) {
            $lines[] = $name.'='.$value;
        }
        $old = umask(0177);
        $handle = @fopen($path, 'xb');
        umask($old);
        if ($handle === false) {
            throw new BackupFormatException(BackupFormatException::WRITE_FAILED);
        }
        $content = implode("\n", $lines)."\n";
        $ok = fwrite($handle, $content) === strlen($content);
        fclose($handle);
        chmod($path, 0600);
        if (! $ok) {
            throw new BackupFormatException(BackupFormatException::WRITE_FAILED);
        }
    }

    /** @param  array{run_uuid:string,kind:string,signer_fp:string,signer_checked:bool,parts:int,tables:int,rows:int,files:int,file_bytes:int,keys:bool}  $s */
    private static function printSummary(array $s, ?string $out): void
    {
        echo "OK\n";
        echo 'Lauf: '.$s['run_uuid'].' ('.$s['kind'].")\n";
        echo 'Signer: '.$s['signer_fp'].($s['signer_checked'] ? " (bestaetigt)\n" : " (nicht gegen --signer geprueft: bitte den Fingerabdruck mit deiner Notiz vergleichen)\n");
        echo 'Teile: '.$s['parts'].' (inkl. Manifest), Datenbank: '.$s['tables'].' Tabellen, '.$s['rows'].' Zeilen, Dateien: '.$s['files'].' ('.$s['file_bytes'].' Byte), Schluessel: '.($s['keys'] ? 'ja' : 'nein')."\n";
        if ($out !== null) {
            echo "Geschrieben nach: $out (db.ndjson.gz, Dateibaum je Bereich im Klartext mit Rechten 0600/0700; keys.env nur, wenn das Backup einen Schluessel-Teil enthaelt)\n";
        }
    }

    // ------------------------------------------------------------------------------------------ Hilfen

    /**
     * @param  list<string>  $args
     * @return array{0:list<string>,1:array<string,string|true>}
     */
    private static function parseArgs(array $args): array
    {
        $positional = [];
        $options = [];
        for ($i = 0; $i < count($args); $i++) {
            $arg = $args[$i];
            if (! str_starts_with($arg, '--')) {
                $positional[] = $arg;

                continue;
            }
            $body = substr($arg, 2);
            $value = null;
            if (str_contains($body, '=')) {
                [$body, $value] = explode('=', $body, 2);
            }
            if (in_array($body, self::FLAG_OPTIONS, true)) {
                $options[$body] = true;
            } elseif (in_array($body, self::VALUE_OPTIONS, true)) {
                if ($value === null) {
                    $value = $args[++$i] ?? null;
                }
                if ($value === null || $value === '') {
                    throw new InvalidArgumentException("--$body braucht einen Wert.");
                }
                $options[$body] = $value;
            } else {
                throw new InvalidArgumentException('Unbekannte Option.');
            }
        }

        return [$positional, $options];
    }

    /** @param  list<string>  $positional */
    private static function expectPositional(array $positional, int $count): void
    {
        if (count($positional) !== $count) {
            throw new InvalidArgumentException($count === 0 ? 'Unerwartetes Argument.' : 'Es fehlt ein Argument oder es sind zu viele.');
        }
    }

    /** @param  array<string,string|true>  $options */
    private static function stringOption(array $options, string $name): ?string
    {
        $value = $options[$name] ?? null;

        return is_string($value) ? $value : null;
    }

    /** @param  array<string,string|true>  $options */
    private static function signerOption(array $options): ?string
    {
        $signer = self::stringOption($options, 'signer');
        if ($signer === null) {
            return null;
        }
        $signer = strtolower($signer);
        if (preg_match('/^[0-9a-f]{16}$/D', $signer) !== 1) {
            throw new InvalidArgumentException('--signer: 16 Hex-Zeichen (Fingerabdruck).');
        }

        return $signer;
    }

    /** @param  array<string,string|true>  $options */
    private static function keyFileOption(array $options): string
    {
        $file = self::stringOption($options, 'key-file');
        if ($file === null) {
            throw new InvalidArgumentException('--key-file=<datei> fehlt.');
        }

        return $file;
    }

    /** @param  array<string,string|true>  $options */
    private static function loadKey(array $options): string
    {
        $file = self::keyFileOption($options);
        try {
            return SecretKeyFile::read($file, null);
        } catch (BackupFormatException $e) {
            if ($e->errorCode !== BackupFormatException::PASSPHRASE_REQUIRED) {
                throw $e;
            }
        }
        $passphrase = self::readPassphrase('Passphrase fuer den Schluessel: ', isset($options['passphrase-stdin']));
        if ($passphrase === null || $passphrase === '') {
            throw new BackupFormatException(BackupFormatException::PASSPHRASE_REQUIRED);
        }
        try {
            return SecretKeyFile::read($file, $passphrase);
        } finally {
            sodium_memzero($passphrase);
        }
    }

    /**
     * Neue Passphrase fuer keygen: am Terminal zweimal verdeckt oder mit --passphrase-stdin (erste Zeile von stdin).
     * Mindestlaenge 12 Zeichen. Ohne Passphrase (leer, kein Terminal) nur mit ausdruecklichem --no-passphrase.
     */
    private static function askNewPassphrase(bool $fromStdin, bool $noPassphrase): ?string
    {
        if ($noPassphrase) {
            if ($fromStdin) {
                throw new InvalidArgumentException('--no-passphrase und --passphrase-stdin schliessen sich aus.');
            }
            fwrite(STDERR, "Warnung: Der private Schluessel wird OHNE Passphrase gespeichert (--no-passphrase). Wer die Datei kopiert, kann alle Backups lesen.\n");

            return null;
        }

        if ($fromStdin) {
            $pass = self::readPassphrase('', true);
        } elseif (stream_isatty(STDIN)) {
            $pass = self::readPassphrase('Passphrase zum Schutz des privaten Schluessels (mindestens '.self::MIN_PASSPHRASE.' Zeichen): ', false);
        } else {
            throw new MgdCliError('Kein Terminal und keine --passphrase-stdin: eine Passphrase ist noetig. Mit --passphrase-stdin die Passphrase von stdin lesen oder ausdruecklich --no-passphrase angeben (nicht empfohlen).');
        }

        if ($pass === null || $pass === '') {
            throw new MgdCliError('Leere Passphrase. Eine Passphrase (mindestens '.self::MIN_PASSPHRASE.' Zeichen) ist noetig; ohne Passphrase nur mit ausdruecklichem --no-passphrase (nicht empfohlen).');
        }
        $length = function_exists('mb_strlen') ? mb_strlen($pass, 'UTF-8') : strlen($pass);
        if ($length < self::MIN_PASSPHRASE) {
            sodium_memzero($pass);
            throw new MgdCliError('Die Passphrase ist zu kurz (mindestens '.self::MIN_PASSPHRASE.' Zeichen).');
        }

        if (! $fromStdin) {
            $second = self::readPassphrase('Passphrase wiederholen: ', false);
            if ($second === null || ! hash_equals($pass, $second)) {
                sodium_memzero($pass);
                throw new BackupFormatException('passphrase_mismatch');
            }
            sodium_memzero($second);
        }

        return $pass;
    }

    // ------------------------------------------------------------------------------------------ Signer-Pflicht

    /**
     * Erwarteter Signer-Fingerabdruck fuer verify/decrypt: --signer, sonst <name>.signer (neben der Schluesseldatei, dann im
     * Standard-Schluesselordner), sonst nur mit --accept-unverified-signer null. Sonst Abbruch mit Hinweis.
     *
     * @param  array<string,string|true>  $options
     */
    private static function requireSigner(array $options, string $keyFile): ?string
    {
        $signer = self::signerOption($options);
        if ($signer !== null) {
            return $signer;
        }

        $name = basename($keyFile);
        $name = str_ends_with($name, '.key') ? substr($name, 0, -4) : $name;
        if (preg_match('/^[A-Za-z0-9_-]{1,40}$/D', $name) === 1) {
            $candidates = [dirname($keyFile).'/'.$name.'.signer'];
            try {
                $candidates[] = self::defaultKeyDir().'/'.$name.'.signer';
            } catch (BackupFormatException) {
                // kein Home-Ordner: nur neben der Schluesseldatei suchen
            }
            foreach (array_unique($candidates) as $file) {
                if (is_file($file) && ! is_link($file)) {
                    return self::readSignerFile($file);
                }
            }
        }

        if (isset($options['accept-unverified-signer'])) {
            fwrite(STDERR, "Warnung: Der Signer wird NICHT gegen einen bekannten Fingerabdruck geprueft (--accept-unverified-signer). Die Herkunft des Backups ist damit nicht bestaetigt.\n");

            return null;
        }

        throw new MgdCliError(
            "Der erwartete Signer-Fingerabdruck fehlt. Ohne ihn waere ein gefaelschtes Backup mit eigenem Signaturschluessel nicht erkennbar.\n"
            ."  Gib ihn mit --signer=<16 Hex-Zeichen> an oder lege ihn in der Datei ~/.mgd-backup-keys/$name.signer ab (eine Zeile).\n"
            ."  Den Fingerabdruck zeigt das Backoffice unter Backup (Signer-Fingerabdruck notieren) (--signer=...).\n"
            .'  Nur wenn du die Herkunft anders pruefst: --accept-unverified-signer.'
        );
    }

    private static function readSignerFile(string $file): string
    {
        $content = @file_get_contents($file, false, null, 0, 1025);
        $line = $content === false ? '' : trim((string) strtok($content, "\r\n"));
        $line = strtolower($line);
        if (preg_match('/^[0-9a-f]{16}$/D', $line) !== 1) {
            throw new MgdCliError('Die Signer-Datei ist ungueltig (erwartet: 16 Hex-Zeichen in der ersten Zeile).');
        }

        return $line;
    }

    private static function readPassphrase(string $prompt, bool $fromStdin): ?string
    {
        if ($fromStdin) {
            $line = fgets(STDIN);

            return $line === false ? null : rtrim($line, "\r\n");
        }
        if (! stream_isatty(STDIN)) {
            return null;
        }
        $saved = trim((string) @shell_exec('stty -g 2>/dev/null'));
        if ($saved === '') {
            throw new BackupFormatException('passphrase_prompt_unavailable');
        }
        fwrite(STDERR, $prompt);
        @shell_exec('stty -echo 2>/dev/null');
        try {
            $line = fgets(STDIN);
        } finally {
            @shell_exec('stty '.escapeshellarg($saved).' 2>/dev/null');
            fwrite(STDERR, "\n");
        }

        return $line === false ? null : rtrim($line, "\r\n");
    }

    private static function defaultKeyDir(): string
    {
        $home = (string) getenv('HOME');
        if ($home === '' && function_exists('posix_getpwuid')) {
            $home = (string) (posix_getpwuid(posix_geteuid())['dir'] ?? '');
        }
        if ($home === '' || ! is_dir($home)) {
            throw new BackupFormatException('home_not_found');
        }

        return rtrim($home, '/').'/'.self::KEY_DIR_NAME;
    }

    private static function formatUuid(string $hex): string
    {
        return substr($hex, 0, 8).'-'.substr($hex, 8, 4).'-'.substr($hex, 12, 4).'-'.substr($hex, 16, 4).'-'.substr($hex, 20, 12);
    }

    /** Loescht den Inhalt von $dir (nicht $dir selbst); folgt keinen Symlinks. */
    private static function removeContents(string $dir): void
    {
        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }
            $path = $dir.'/'.$entry;
            if (is_dir($path) && ! is_link($path)) {
                self::removeContents($path);
                @rmdir($path);
            } else {
                @unlink($path);
            }
        }
    }
}

if (PHP_SAPI === 'cli' && isset($_SERVER['SCRIPT_FILENAME']) && realpath($_SERVER['SCRIPT_FILENAME']) === __FILE__) {
    exit(MgdBackupCli::main($_SERVER['argv'] ?? []));
}
