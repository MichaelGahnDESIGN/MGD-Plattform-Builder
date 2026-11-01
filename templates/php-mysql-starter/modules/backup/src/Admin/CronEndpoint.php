<?php

declare(strict_types=1);

namespace MGD\Backup\Admin;

use MGD\Backup\Budget;
use MGD\Backup\Store;
use MGD\Starter\Core\Http\Request;
use MGD\Starter\Core\Http\Response;
use Throwable;

/**
 * Cron-Einstieg per HTTP: GET /backup-cron/<token>. Fuer Hoster ohne CLI-Cron (Cronjobs, die nur eine URL aufrufen koennen).
 *
 *  - Das Token wird nur als SHA-256 gespeichert und mit hash_equals verglichen; es laesst sich im Backoffice erneuern.
 *  - Ohne gesetztes Token gibt es den Endpunkt nicht (404). Falsche Tokens liefern ebenfalls 404 ohne Hinweis; nach 10
 *    Fehlversuchen in 15 Minuten antwortet der Endpunkt fuer den Rest der Zeit mit 429, ohne zu pruefen.
 *  - Antworten enthalten nie Pfade, Fehlertexte oder Zugangsdaten, nur "ok <zustand>".
 *  - Jeder Aufruf arbeitet hoechstens backup.tick_seconds (und nie laenger als max_execution_time erlaubt).
 */
final class CronEndpoint
{
    private const MAX_FAILURES = 10;

    private const WINDOW_SECONDS = 900;

    public function __construct(private readonly Services $services)
    {
    }

    public function handle(Request $request, string $token): Response
    {
        $store = $this->services->store;
        $settings = $store->settings();
        $hash = (string) ($settings['cron_token_hash'] ?? '');

        if ($hash === '' || $request->method !== 'GET') {
            return self::plain('Not found', 404);
        }

        $now = $store->now();
        $since = Store::toDate($settings['cron_fail_since'] !== null ? (string) $settings['cron_fail_since'] : null);
        $count = (int) $settings['cron_fail_count'];

        if ($since === null || $since->modify('+' . self::WINDOW_SECONDS . ' seconds') <= $now) {
            $count = 0;
            $since = null;
        }

        if ($count >= self::MAX_FAILURES) {
            return self::plain('Too many requests', 429);
        }

        if (strlen($token) > 128 || !hash_equals($hash, hash('sha256', $token))) {
            $store->updateSettings(['cron_fail_count' => $count + 1, 'cron_fail_since' => ($since ?? $now)->format(Store::DATE_FORMAT)]);

            return self::plain('Not found', 404);
        }

        if ($count > 0) {
            $store->updateSettings(['cron_fail_count' => 0, 'cron_fail_since' => null]);
        }

        if (!extension_loaded('sodium') || !extension_loaded('zlib')) {
            return self::plain('error', 503);
        }

        try {
            $result = $this->services->runner->tick(Budget::forCron($this->services->tickSeconds()));
        } catch (Throwable $e) {
            error_log('[mgd-backup] cron: ' . $e::class);

            return self::plain('error', 500);
        }

        return self::plain('ok ' . $result['state'], 200);
    }

    private static function plain(string $text, int $status): Response
    {
        return Response::text($text . "\n", 'text/plain; charset=utf-8', $status)
            ->withHeader('Cache-Control', 'no-store')
            ->withHeader('X-Content-Type-Options', 'nosniff');
    }
}
