<?php

declare(strict_types=1);

namespace MGD\Backup\Format;

use JsonException;

/**
 * Manifest eines Backup-Laufs: aufbauen, mit Ed25519 signieren, pruefen und validieren.
 *
 * Klartext des Teils "manifest": {"manifest":"<base64 JSON>","sig":"<base64 Ed25519 detached>"}. Die Signatur
 * gilt den exakten JSON-Bytes. Der Signer (oeffentlicher Schluessel) kommt vom Aufrufer, nicht aus dem Manifest:
 * verify() prueft gegen den uebergebenen Schluessel.
 *
 * Felder: format, format_version, run_uuid, kind, trigger, created_at, finished_at, app{version,php,db_driver,db_server},
 * schema{migrations[]}, db{consistency,tables[{name,rows}],excluded[]}, files{areas{uploads,erp:{count,bytes}},skipped_missing,skipped_links?},
 * keys{included,app_key_id,uploads_key_id,previous_ids[]}, recipients[fp], parts[{seq,kind,name,cipher_bytes,cipher_sha256,
 * plain_bytes}], signer{alg,public_key,fp}. Zeitangaben: UTC im Format YYYY-MM-DDTHH:MM:SSZ. Das Manifest listet sich
 * selbst nicht in parts (die Pruefsumme eines Teils kann nicht im eigenen Inhalt stehen).
 */
final class ManifestCodec
{
    public const FORMAT = 'MGDB1';

    public const FORMAT_VERSION = 1;

    public const SIGNER_ALG = 'ed25519';

    private const MAX_JSON_BYTES = 12582912;

    private const TOP_LEVEL = ['format', 'format_version', 'run_uuid', 'kind', 'trigger', 'created_at', 'finished_at', 'app', 'schema', 'db', 'files', 'keys', 'recipients', 'parts', 'signer'];

    /** Fingerabdruck eines Ed25519-Signaturschluessels (wie bei Empfaengern: 8 Byte SHA-256, 16 Hex). */
    public static function signerFingerprint(string $signPublicRaw): string
    {
        return BackupFormat::fingerprint($signPublicRaw);
    }

    /**
     * Setzt format/format_version, validiert und liefert das JSON (zum Signieren).
     *
     * @param  array<string,mixed>  $fields  alle Felder ausser format und format_version
     */
    public static function build(array $fields): string
    {
        $manifest = array_merge(['format' => self::FORMAT, 'format_version' => self::FORMAT_VERSION], $fields);
        self::validate($manifest);

        try {
            return json_encode($manifest, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new BackupFormatException(BackupFormatException::BAD_MANIFEST);
        }
    }

    /**
     * @param  string  $signSecret  64 Byte Ed25519-Geheimschluessel (sodium_crypto_sign_secretkey)
     * @return array{manifest:string,sig:string} beide base64
     */
    public static function sign(string $json, string $signSecret): array
    {
        if (strlen($signSecret) !== SODIUM_CRYPTO_SIGN_SECRETKEYBYTES || strlen($json) > self::MAX_JSON_BYTES) {
            throw new BackupFormatException(BackupFormatException::BAD_ARGUMENT);
        }

        return [
            'manifest' => base64_encode($json),
            'sig' => base64_encode(sodium_crypto_sign_detached($json, $signSecret)),
        ];
    }

    /**
     * Prueft die Signatur gegen den uebergebenen Schluessel und validiert das Manifest.
     *
     * @param  array<string,mixed>  $pair  {manifest, sig} (base64)
     * @param  string  $signPublic  32 Byte Ed25519 (roh)
     * @return array<string,mixed> das validierte Manifest
     */
    public static function verify(array $pair, string $signPublic): array
    {
        if (strlen($signPublic) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES) {
            throw new BackupFormatException(BackupFormatException::BAD_ARGUMENT);
        }
        [$json, $sig] = self::decodePair($pair);
        if (strlen($sig) !== SODIUM_CRYPTO_SIGN_BYTES || ! sodium_crypto_sign_verify_detached($sig, $json, $signPublic)) {
            throw new BackupFormatException(BackupFormatException::BAD_SIGNATURE);
        }
        $manifest = self::validate(self::decodeJson($json));

        // Das im Manifest genannte Signer-Feld muss zum pruefenden Schluessel passen.
        if (! hash_equals(base64_decode($manifest['signer']['public_key'], true) ?: '', $signPublic)) {
            throw new BackupFormatException(BackupFormatException::SIGNER_MISMATCH);
        }

        return $manifest;
    }

    /**
     * Liest das Manifest OHNE Signaturpruefung (nur um den genannten Signer zu lesen, z. B. fuer --signer).
     * Das Ergebnis ist NICHT vertrauenswuerdig, bis verify() mit dem passenden Schluessel gelungen ist.
     *
     * @param  array<string,mixed>  $pair
     * @return array<string,mixed>
     */
    public static function unverified(array $pair): array
    {
        [$json] = self::decodePair($pair);

        return self::validate(self::decodeJson($json));
    }

    /**
     * Strukturpruefung. Wirft BackupFormatException(bad_manifest) bzw. unsupported_version.
     *
     * @param  array<string,mixed>  $m
     * @return array<string,mixed>
     */
    public static function validate(array $m): array
    {
        $bad = static fn (): never => throw new BackupFormatException(BackupFormatException::BAD_MANIFEST);

        if (array_diff(array_keys($m), self::TOP_LEVEL) !== [] || array_diff(self::TOP_LEVEL, array_keys($m)) !== []) {
            $bad();
        }
        if (($m['format'] ?? null) !== self::FORMAT) {
            $bad();
        }
        if (! is_int($m['format_version'])) {
            $bad();
        }
        if ($m['format_version'] !== self::FORMAT_VERSION) {
            throw new BackupFormatException(BackupFormatException::UNSUPPORTED_VERSION);
        }
        if (! self::isString($m['run_uuid'], '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/D')
            || ! in_array($m['kind'], ['db', 'full'], true)
            || ! in_array($m['trigger'], ['manual', 'schedule', 'cli'], true)
            || ! self::isString($m['created_at'], self::TIME_REGEX)
            || ! self::isString($m['finished_at'], self::TIME_REGEX)) {
            $bad();
        }

        $app = $m['app'];
        if (! is_array($app) || ! self::keysAre($app, ['version', 'php', 'db_driver', 'db_server'])) {
            $bad();
        }
        foreach ($app as $value) {
            if (! is_string($value) || strlen($value) > 200) {
                $bad();
            }
        }

        $schema = $m['schema'];
        if (! is_array($schema) || ! self::keysAre($schema, ['migrations']) || ! self::isList($schema['migrations'], 20000)) {
            $bad();
        }
        foreach ($schema['migrations'] as $migration) {
            if (! self::isString($migration, '/^[0-9A-Za-z_]{1,200}$/D')) {
                $bad();
            }
        }

        $db = $m['db'];
        if (! is_array($db) || ! self::keysAre($db, ['consistency', 'tables', 'excluded'])
            || ! in_array($db['consistency'], ['snapshot', 'per_tick'], true)
            || ! self::isList($db['tables'], 5000) || ! self::isList($db['excluded'], 5000)) {
            $bad();
        }
        foreach ($db['tables'] as $table) {
            if (! is_array($table) || ! self::keysAre($table, ['name', 'rows'])
                || ! self::isString($table['name'], '/^[A-Za-z0-9_]{1,64}$/D') || ! self::isCount($table['rows'])) {
                $bad();
            }
        }
        foreach ($db['excluded'] as $name) {
            if (! self::isString($name, '/^[A-Za-z0-9_]{1,64}$/D')) {
                $bad();
            }
        }

        $files = $m['files'];
        // skipped_links und skipped_unsafe sind optional (Manifeste des Hubs haben skipped_unsafe nicht); wenn vorhanden, Zahlen.
        $filesKeys = ['areas', 'skipped_missing'];
        foreach (['skipped_links', 'skipped_unsafe'] as $optional) {
            if (is_array($files) && array_key_exists($optional, $files)) {
                $filesKeys[] = $optional;
            }
        }
        if (! is_array($files) || ! self::keysAre($files, $filesKeys) || ! self::isCount($files['skipped_missing'])
            || (isset($files['skipped_links']) && ! self::isCount($files['skipped_links']))
            || (isset($files['skipped_unsafe']) && ! self::isCount($files['skipped_unsafe']))
            || ! is_array($files['areas']) || count($files['areas']) > 64) {
            $bad();
        }
        // Bereiche sind frei benennbar (MGD-Support-Hub: uploads und erp). Der Builder schreibt immer uploads und erp mit, damit auch
        // Pruefwerkzeuge des Hubs ein DB-Backup des Builders lesen koennen.
        foreach ($files['areas'] as $name => $area) {
            if (preg_match(BackupFormat::AREA_NAME_REGEX, (string) $name) !== 1
                || ! is_array($area) || ! self::keysAre($area, ['count', 'bytes']) || ! self::isCount($area['count']) || ! self::isCount($area['bytes'])) {
                $bad();
            }
        }

        $keys = $m['keys'];
        if (! is_array($keys) || ! self::keysAre($keys, ['included', 'app_key_id', 'uploads_key_id', 'previous_ids'])
            || ! is_bool($keys['included'])
            || ! self::isNullableId($keys['app_key_id']) || ! self::isNullableId($keys['uploads_key_id'])
            || ! self::isList($keys['previous_ids'], 100)) {
            $bad();
        }
        foreach ($keys['previous_ids'] as $id) {
            if (! self::isString($id, '/^[0-9a-f]{16}$/D')) {
                $bad();
            }
        }

        if (! self::isList($m['recipients'], BackupFormat::MAX_RECIPIENTS) || $m['recipients'] === []) {
            $bad();
        }
        foreach ($m['recipients'] as $fingerprint) {
            if (! self::isString($fingerprint, '/^[0-9a-f]{16}$/D')) {
                $bad();
            }
        }

        if (! self::isList($m['parts'], 100000)) {
            $bad();
        }
        $seen = [];
        foreach ($m['parts'] as $part) {
            if (! is_array($part) || ! self::keysAre($part, ['seq', 'kind', 'name', 'cipher_bytes', 'cipher_sha256', 'plain_bytes'])
                || ! self::isCount($part['seq']) || $part['seq'] > 0xFFFFFFFF || isset($seen[$part['seq']])
                || ! in_array($part['kind'], ['db', 'files', 'keys'], true)
                || ! self::isString($part['name'], BackupFormat::PART_NAME_REGEX)
                || ! self::isCount($part['cipher_bytes']) || ! self::isCount($part['plain_bytes'])
                || ! self::isString($part['cipher_sha256'], '/^[0-9a-f]{64}$/D')) {
                $bad();
            }
            $seen[$part['seq']] = true;
        }

        $signer = $m['signer'];
        if (! is_array($signer) || ! self::keysAre($signer, ['alg', 'public_key', 'fp']) || $signer['alg'] !== self::SIGNER_ALG
            || ! is_string($signer['public_key']) || ! is_string($signer['fp']) || ! preg_match('/^[0-9a-f]{16}$/D', $signer['fp'])) {
            $bad();
        }
        $public = base64_decode($signer['public_key'], true);
        if ($public === false || strlen($public) !== SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES || ! hash_equals(self::signerFingerprint($public), $signer['fp'])) {
            $bad();
        }

        return $m;
    }

    private const TIME_REGEX = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/D';

    /**
     * @param  array<string,mixed>  $pair
     * @return array{0:string,1:string}
     */
    private static function decodePair(array $pair): array
    {
        if (! isset($pair['manifest'], $pair['sig']) || ! is_string($pair['manifest']) || ! is_string($pair['sig'])
            || strlen($pair['manifest']) > self::MAX_JSON_BYTES * 2) {
            throw new BackupFormatException(BackupFormatException::BAD_MANIFEST);
        }
        $json = base64_decode($pair['manifest'], true);
        $sig = base64_decode($pair['sig'], true);
        if ($json === false || $sig === false || $json === '') {
            throw new BackupFormatException(BackupFormatException::BAD_MANIFEST);
        }

        return [$json, $sig];
    }

    /** @return array<string,mixed> */
    private static function decodeJson(string $json): array
    {
        try {
            $data = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new BackupFormatException(BackupFormatException::BAD_MANIFEST);
        }
        if (! is_array($data)) {
            throw new BackupFormatException(BackupFormatException::BAD_MANIFEST);
        }

        return $data;
    }

    /**
     * Gleiche Schluesselmenge, Reihenfolge egal.
     *
     * @param  array<array-key,mixed>  $value
     * @param  list<string>  $keys
     */
    private static function keysAre(array $value, array $keys): bool
    {
        $actual = array_map('strval', array_keys($value));
        sort($actual);
        sort($keys);

        return $actual === $keys;
    }

    private static function isString(mixed $value, string $regex): bool
    {
        return is_string($value) && preg_match($regex, $value) === 1;
    }

    private static function isCount(mixed $value): bool
    {
        return is_int($value) && $value >= 0;
    }

    private static function isList(mixed $value, int $max): bool
    {
        return is_array($value) && array_is_list($value) && count($value) <= $max;
    }

    private static function isNullableId(mixed $value): bool
    {
        return $value === null || self::isString($value, '/^[0-9a-f]{16}$/D');
    }
}
