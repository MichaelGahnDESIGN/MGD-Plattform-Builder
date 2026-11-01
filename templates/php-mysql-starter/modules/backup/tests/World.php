<?php

declare(strict_types=1);

use MGD\Backup\Budget;
use MGD\Backup\Db\Dumper;
use MGD\Backup\Destinations\DriverFactory;
use MGD\Backup\Environment;
use MGD\Backup\Keys;
use MGD\Backup\Paths;
use MGD\Backup\Runner;
use MGD\Backup\Secrets;
use MGD\Backup\Store;

/** Baut eine komplette Testumgebung: SQLite, Temp-Ordner, Projektordner mit Dateien, Store, Keys, Runner, Fake-FTP. */
final class World
{
    public PDO $pdo;

    public Store $store;

    public Paths $paths;

    public Keys $keys;

    public Secrets $secrets;

    public Runner $runner;

    public DriverFactory $drivers;

    public Environment $env;

    public Dumper $dumper;

    public FakeFtpClient $ftp;

    public string $dir;

    public string $project;

    /** @var array{public:string,secret:string,text:string,fp:string} */
    public array $recipient;

    /** @var list<array{string,array<string,mixed>,?int}> */
    public array $audit = [];

    public DateTimeImmutable $now;

    /** @param array<string,string> $areas Bereich => Pfad relativ zum Projektordner */
    public function __construct(int $rows = 25, bool $withFiles = true, array $areas = ['uploads' => 'public/uploads'], int $maxAttempts = 3, ?int $partBytes = null)
    {
        $this->dir = T::tempDir();
        $this->project = $this->dir . '/project';
        mkdir($this->project . '/public/uploads/sub', 0700, true);
        mkdir($this->project . '/config', 0700, true);
        mkdir($this->project . '/storage/backups', 0700, true);
        file_put_contents($this->project . '/config/config.php', "<?php return ['secret' => 'TOP-SECRET-CONFIG'];");

        if ($withFiles) {
            file_put_contents($this->project . '/public/uploads/a.txt', 'Datei A mit Inhalt');
            file_put_contents($this->project . '/public/uploads/sub/b.bin', random_bytes(300000));
            file_put_contents($this->project . '/public/uploads/sub/leer.txt', '');
            file_put_contents($this->project . '/public/uploads/skip.tmp', 'wird ausgeschlossen');
        }

        $this->pdo = T::pdo();
        T::seedProject($this->pdo, $rows);
        $this->now = new DateTimeImmutable('2026-10-27 12:00:00', new DateTimeZone('UTC'));
        $this->store = new Store($this->pdo, fn (): DateTimeImmutable => $this->now);
        $this->paths = new Paths($this->project . '/storage/backups/mgdbk');
        $this->secrets = new Secrets('test-app-key-0123456789abcdef-test');
        $this->keys = new Keys($this->store, $this->secrets);
        $this->ftp = new FakeFtpClient();
        $this->drivers = new DriverFactory($this->paths, $this->secrets, [], fn (array $destination, string $password): FakeFtpClient => $this->ftp);
        $this->dumper = new Dumper($this->pdo, ['login_attempts']);
        $this->env = new Environment(
            '0.0.0-test',
            $areas,
            $this->project,
            [$this->project . '/config', $this->project . '/storage'],
            ['login_attempts'],
            fn (): array => array_map('strval', $this->pdo->query('SELECT version FROM schema_migrations ORDER BY version')->fetchAll(PDO::FETCH_COLUMN)),
            minFreeBytes: 1,
            maxAttempts: $maxAttempts,
            timezone: 'UTC',
            partBytesOverride: $partBytes,
        );
        $this->runner = new Runner($this->store, $this->paths, $this->keys, $this->drivers, $this->dumper, $this->env, function (string $action, array $meta, ?int $user): void {
            $this->audit[] = [$action, $meta, $user];
        });
        $this->recipient = T::keyPair();
        $this->keys->setRecipient('primary', $this->recipient['text']);
    }

    public function enableLocal(int $keep = 3): int
    {
        return $this->store->createDestination(['dest_type' => 'local', 'name' => 'Lokal', 'enabled' => 1, 'keep_count' => $keep, 'status' => 'ok'], 1);
    }

    public function enableFtp(string $password = 'erfundenes-test-passwort', int $keep = 3): int
    {
        $config = json_encode(['host' => 'ftp.example.org', 'port' => 21, 'mode' => 'explicit', 'username' => 'testnutzer', 'base_path' => '/backups']);

        return $this->store->createDestination([
            'dest_type' => 'ftp', 'name' => 'Fern', 'enabled' => 1, 'keep_count' => $keep, 'status' => 'ok',
            'config_json' => $config, 'secret_enc' => $this->secrets->encrypt(json_encode(['password' => $password])),
        ], 1);
    }

    /** Alle Ticks bis der aktive Lauf fertig ist (mit grossem Budget). @return array<string,mixed> der Lauf */
    public function runToEnd(int $runId, int $maxTicks = 40, float $seconds = 30.0): array
    {
        for ($i = 0; $i < $maxTicks; $i++) {
            $this->runner->tick(new Budget($seconds));
            $run = $this->store->run($runId);

            if ($run !== null && !in_array($run['status'], ['queued', 'running'], true)) {
                return $run;
            }
        }

        throw new RuntimeException('Lauf nicht fertig geworden');
    }

    /** Ein Budget, das nach etwa $n Zeitabfragen aufgebraucht ist (deterministisch, ohne echte Zeit). */
    public function steps(int $n): Budget
    {
        $calls = 0;

        return new Budget((float) $n, static function () use (&$calls): float {
            return (float) $calls++;
        });
    }

    /** Ordner mit den lokalen Teilen eines Laufs. */
    public function runDir(array $run): string
    {
        return $this->paths->root() . '/' . $run['folder_name'];
    }
}
