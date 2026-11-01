<?php

declare(strict_types=1);

namespace MGD\Backup\Admin;

use MGD\Backup\Db\Dumper;
use MGD\Backup\Destinations\DriverFactory;
use MGD\Backup\Environment;
use MGD\Backup\Keys;
use MGD\Backup\Paths;
use MGD\Backup\Runner;
use MGD\Backup\Secrets;
use MGD\Backup\Store;
use MGD\Starter\Core\App;
use MGD\Starter\Core\Database\ConnectionFactory;

/**
 * Verdrahtet die Bausteine des Moduls mit dem Starter (App, Konfiguration, Kerndatenbank). Alle Einstellungen stehen in
 * config/config.php unter dem Schluessel "backup" (siehe config.example.php); nichts davon laesst sich im Backoffice aendern.
 */
final class Services
{
    /** @var array<int,self> */
    private static array $instances = [];

    public function __construct(
        public readonly App $app,
        public readonly Store $store,
        public readonly Paths $paths,
        public readonly Secrets $secrets,
        public readonly Keys $keys,
        public readonly DriverFactory $drivers,
        public readonly Environment $env,
        public readonly Runner $runner,
        /** @var array<string,mixed> */
        public readonly array $options,
    ) {
    }

    public static function for(App $app): self
    {
        return self::$instances[spl_object_id($app)] ??= self::build($app);
    }

    private static function build(App $app): self
    {
        $options = $app->config->get('backup', []);
        $options = is_array($options) ? $options : [];
        $root = (string) $app->config->get('paths.root', dirname(__DIR__, 4));
        $pdo = $app->databases->core();
        $store = new Store($pdo);
        $paths = new Paths(rtrim($app->pathOr('backups', 'storage/backups'), '/') . '/mgdbk');
        $secrets = new Secrets($app->config->string('app.key'));
        $keys = new Keys($store, $secrets);
        $drivers = new DriverFactory($paths, $secrets, $options);

        $areas = $options['areas'] ?? null;

        if (!is_array($areas)) {
            $areas = ['uploads' => $app->pathOr('uploads', 'public/uploads'), 'custom-assets' => $app->pathOr('custom_assets', 'public/assets/custom')];
        }

        $core = $app->config->get('db.core', []);
        $dumper = new Dumper(
            $pdo,
            array_values(array_unique([...array_map('strval', (array) ($options['exclude_tables'] ?? [])), 'login_attempts', 'password_resets', 'password_reset_requests'])),
            is_array($core) ? static fn (): \PDO => ConnectionFactory::create($core) : null,
        );
        $env = new Environment(
            $app->version()->number,
            array_map('strval', $areas),
            $root,
            [$root . '/config', $root . '/src', $root . '/database', $root . '/scripts', $root . '/vendor', $app->pathOr('storage', 'storage')],
            $dumper->excludedNames(),
            static function () use ($pdo): array {
                try {
                    return array_map('strval', $pdo->query('SELECT version FROM schema_migrations ORDER BY version')->fetchAll(\PDO::FETCH_COLUMN));
                } catch (\Throwable) {
                    return [];
                }
            },
            max(0, (int) ($options['min_free_bytes'] ?? 268435456)),
            max(1, (int) ($options['max_run_hours'] ?? 24)),
            max(1, (int) ($options['max_attempts'] ?? 5)),
            $app->config->string('app.timezone', 'Europe/Berlin'),
        );
        $runner = new Runner(
            $store,
            $paths,
            $keys,
            $drivers,
            $dumper,
            $env,
            static function (string $action, array $meta, ?int $userId) use ($app): void {
                $actor = $userId !== null ? $app->users()->findActive($userId) : null;
                $app->audit()->record($actor, $action, 'backup', isset($meta['run_id']) ? (string) $meta['run_id'] : null, $meta);
            }
        );

        return new self($app, $store, $paths, $secrets, $keys, $drivers, $env, $runner, $options);
    }

    /** Sekunden, die ein einzelner Cron-Aufruf arbeiten darf (config backup.tick_seconds, 20 bis 600). */
    public function tickSeconds(): int
    {
        return max(20, min(600, (int) ($this->options['tick_seconds'] ?? 100)));
    }
}
