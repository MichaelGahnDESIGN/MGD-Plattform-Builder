<?php

declare(strict_types=1);

/*
 * Gemeinsame Hilfen der Modultests (reines PHP, kein Composer, kein Starter noetig).
 *
 *   php modules/backup/tests/run.php
 *
 * Voraussetzungen: PHP 8.2+ mit sodium, zlib, pdo_sqlite (Datenbank der Tests) und curl. Es werden weder Netzwerk noch
 * echte Zugangsdaten benutzt; alle Schluessel, Passwoerter und Hosts in den Tests sind erfunden.
 */

use MGD\Backup\Format\RecipientKey;

if (PHP_SAPI !== 'cli') {
    exit(1);
}

spl_autoload_register(static function (string $class): void {
    $prefix = 'MGD\\Backup\\';

    if (!str_starts_with($class, $prefix)) {
        return;
    }

    $relative = substr($class, strlen($prefix));

    if (preg_match('/^[A-Za-z0-9_\\\\]+$/', $relative) !== 1) {
        return;
    }

    $file = dirname(__DIR__) . '/src/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($file)) {
        require $file;
    }
});

final class T
{
    public static int $passed = 0;

    /** @var list<string> */
    private static array $cleanup = [];

    public static function check(bool $condition, string $label): void
    {
        if (!$condition) {
            fwrite(STDERR, "FAIL: {$label}\n");
            exit(1);
        }

        self::$passed++;
        echo "ok   {$label}\n";
    }

    /** Erwartet eine Ausnahme der Klasse $class (und optional einen Fehlercode). */
    public static function throws(callable $work, string $class, string $label, ?string $code = null): void
    {
        try {
            $work();
        } catch (Throwable $e) {
            $matches = $e instanceof $class;
            $codeOk = $code === null || (property_exists($e, 'errorCode') && $e->errorCode === $code);
            self::check($matches && $codeOk, $label . ($matches && !$codeOk ? ' (falscher Code: ' . (property_exists($e, 'errorCode') ? $e->errorCode : '?') . ')' : ''));

            return;
        }

        self::check(false, $label . ' (keine Ausnahme)');
    }

    public static function tempDir(string $prefix = 'mgdbk-test-'): string
    {
        $base = getenv('TMPDIR') ?: sys_get_temp_dir();
        $dir = rtrim($base, '/') . '/' . $prefix . bin2hex(random_bytes(6));

        if (!mkdir($dir, 0700, true) && !is_dir($dir)) {
            fwrite(STDERR, "FAIL: Temp-Ordner nicht anlegbar (TMPDIR setzen)\n");
            exit(1);
        }

        self::$cleanup[] = $dir;

        return $dir;
    }

    public static function cleanup(): void
    {
        foreach (self::$cleanup as $dir) {
            self::rmTree($dir);
        }

        self::$cleanup = [];
    }

    public static function rmTree(string $dir): void
    {
        if (is_link($dir) || is_file($dir)) {
            @unlink($dir);

            return;
        }

        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry !== '.' && $entry !== '..') {
                self::rmTree($dir . '/' . $entry);
            }
        }

        @rmdir($dir);
    }

    /** SQLite-Datenbank mit dem Schema der Modul-Migration (MySQL-Syntax wird fuer SQLite uebersetzt) und etwas Beispieldaten. */
    public static function pdo(): PDO
    {
        $pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]);
        $sql = (string) file_get_contents(dirname(__DIR__) . '/migrations/001_backup.sql');
        $sql = (string) preg_replace('/^\s*--.*$/m', '', $sql);
        $sql = (string) preg_replace('/\)\s*ENGINE=InnoDB[^;]*;/', ');', $sql);
        $sql = (string) preg_replace('/INT UNSIGNED AUTO_INCREMENT PRIMARY KEY/', 'INTEGER PRIMARY KEY AUTOINCREMENT', $sql);

        foreach (preg_split('/;\s*(?:\R|$)/', $sql) ?: [] as $statement) {
            if (trim($statement) !== '') {
                $pdo->exec($statement);
            }
        }

        return $pdo;
    }

    /** Beispieltabellen des "Projekts" (nicht backup_*), darunter Binaerdaten, UTF-8, NULL und ein zusammengesetzter Schluessel. */
    public static function seedProject(PDO $pdo, int $rows = 25): void
    {
        $pdo->exec('CREATE TABLE pages (id INTEGER PRIMARY KEY AUTOINCREMENT, slug VARCHAR(80) NOT NULL, title TEXT, body TEXT, blob_data BLOB, price REAL)');
        $pdo->exec('CREATE TABLE page_tags (page_id INTEGER NOT NULL, tag VARCHAR(40) NOT NULL, PRIMARY KEY (page_id, tag))');
        $pdo->exec('CREATE TABLE schema_migrations (version VARCHAR(190) NOT NULL PRIMARY KEY, checksum CHAR(64) NOT NULL, applied_at DATETIME NOT NULL)');
        $pdo->exec('CREATE TABLE login_attempts (id INTEGER PRIMARY KEY AUTOINCREMENT, email_hash CHAR(64))');
        $pdo->exec("INSERT INTO schema_migrations VALUES ('core/001_core', '" . str_repeat('a', 64) . "', '2026-01-01 00:00:00')");
        $pdo->exec("INSERT INTO schema_migrations VALUES ('module/backup/001_backup', '" . str_repeat('b', 64) . "', '2026-01-01 00:00:00')");
        $insert = $pdo->prepare('INSERT INTO pages (slug, title, body, blob_data, price) VALUES (?, ?, ?, ?, ?)');
        $tag = $pdo->prepare('INSERT INTO page_tags (page_id, tag) VALUES (?, ?)');

        for ($i = 1; $i <= $rows; $i++) {
            $insert->bindValue(1, 'seite-' . $i);
            $insert->bindValue(2, $i % 5 === 0 ? null : 'Titel äöü ✓ ' . $i);
            $insert->bindValue(3, 'Lorem ipsum ' . $i . ' ' . bin2hex(random_bytes(250)));
            $insert->bindValue(4, "\xff\xfe\x00bin" . $i, PDO::PARAM_LOB);
            $insert->bindValue(5, $i + 0.5);
            $insert->execute();
            $tag->execute([$i, 'a']);
            $tag->execute([$i, 'b']);
        }

        $pdo->exec("INSERT INTO login_attempts (email_hash) VALUES ('should-not-be-backed-up')");
    }

    /** Zeilenweise Pruefsumme einer Tabelle (zum Vergleich vor/nach Restore). */
    public static function tableDigest(PDO $pdo, string $table, string $orderBy): string
    {
        $hash = hash_init('sha256');

        foreach ($pdo->query('SELECT * FROM ' . $table . ' ORDER BY ' . $orderBy) as $row) {
            hash_update($hash, json_encode(array_map(static fn ($v) => is_string($v) ? base64_encode($v) : $v, $row), JSON_THROW_ON_ERROR) . "\n");
        }

        return hash_final($hash);
    }

    /** @return array{public:string,secret:string,text:string,fp:string} erfundenes Empfaengerschluesselpaar */
    public static function keyPair(): array
    {
        $pair = sodium_crypto_box_keypair();
        $recipient = RecipientKey::fromRaw(sodium_crypto_box_publickey($pair));

        return ['public' => sodium_crypto_box_publickey($pair), 'secret' => sodium_crypto_box_secretkey($pair), 'text' => $recipient->toText(), 'fp' => $recipient->fingerprint()];
    }

    /** Fuehrt einen Befehl ohne Shell aus und liefert [Exit-Code, stdout, stderr]. @param list<string> $command */
    public static function run(array $command, ?string $stdin = null): array
    {
        $process = proc_open($command, [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);

        if (!is_resource($process)) {
            return [127, '', ''];
        }

        fwrite($pipes[0], $stdin ?? '');
        fclose($pipes[0]);
        $out = (string) stream_get_contents($pipes[1]);
        $err = (string) stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);

        return [proc_close($process), $out, $err];
    }
}
