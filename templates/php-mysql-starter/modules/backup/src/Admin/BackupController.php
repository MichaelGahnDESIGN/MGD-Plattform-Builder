<?php

declare(strict_types=1);

namespace MGD\Backup\Admin;

use MGD\Backup\BackupException;
use MGD\Backup\Destinations\Ftp\CurlFtpClient;
use MGD\Backup\Destinations\FtpDriver;
use MGD\Backup\Destinations\HostPolicy;
use MGD\Backup\Failure;
use MGD\Backup\Files\AreaPolicy;
use MGD\Backup\Format\BackupFormatException;
use MGD\Backup\Retention;
use MGD\Backup\Schedule;
use MGD\Backup\Store;
use MGD\Starter\Admin\AdminController;
use MGD\Starter\Core\App;
use MGD\Starter\Core\Auth\Role;
use MGD\Starter\Core\Auth\User;
use MGD\Starter\Core\Http\HttpException;
use MGD\Starter\Core\Http\Request;
use MGD\Starter\Core\Http\Response;
use MGD\Starter\Core\View\Form;
use MGD\Starter\Core\View\Ui;
use MGD\Starter\Core\View\View;
use Throwable;

/**
 * Backoffice des Backup-Moduls. Alle Aktionen verlangen serverseitig die Rolle Administration (guard), POST-Aktionen CSRF;
 * sicherheitsrelevante Aktionen zusaetzlich das aktuelle Passwort (StepUp). Zugangsdaten werden nie ausgegeben, Fehler nur als
 * Katalogtext (Failure::CODES), nie als Roh-Meldung oder Pfad.
 */
final class BackupController extends AdminController
{
    private readonly Services $services;

    private readonly Lang $lang;

    public function __construct(App $app, ?Services $services = null)
    {
        parent::__construct($app);
        $this->services = $services ?? Services::for($app);
        $this->lang = new Lang($app->locale());
    }

    // ------------------------------------------------------------------ Seiten

    public function index(Request $request): Response
    {
        $user = $this->guard($request, Role::Admin);

        return $this->page($this->t('title'), $this->overview($request, null), $user, '/admin/backup');
    }

    public function details(Request $request, array $params): Response
    {
        $user = $this->guard($request, Role::Admin);
        $run = $this->services->store->run((int) ($params['id'] ?? 0));

        if ($run === null) {
            throw new HttpException(404, $this->t('err_notfound'));
        }

        return $this->page($this->t('run_title', ['id' => (int) $run['id']]), $this->detailsBody($run), $user, '/admin/backup');
    }

    // ------------------------------------------------------------------ Aktionen

    public function run(Request $request): Response
    {
        $user = $this->guard($request, Role::Admin);
        $kind = $request->input('kind') === 'db' ? 'db' : 'full';

        try {
            $id = $this->services->runner->enqueue($kind, 'manual', $user->id);
        } catch (BackupException $e) {
            return $this->fail($this->t('code_' . Failure::category($e)));
        }

        return $this->done($this->t('msg_started') . ' (#' . $id . ')');
    }

    public function cancel(Request $request): Response
    {
        $this->guard($request, Role::Admin);
        $user = $this->app->currentUser();

        try {
            $this->services->runner->requestCancel((int) $request->input('run_id'), $user?->id);
        } catch (BackupException) {
            return $this->fail($this->t('err_notfound'));
        }

        return $this->done($this->t('msg_cancel'));
    }

    public function saveSettings(Request $request): Response
    {
        $user = $this->guard($request, Role::Admin);

        if (($blocked = $this->stepUp($user, $request)) !== null) {
            return $blocked;
        }

        $interval = $request->input('schedule_interval');
        $kind = $request->input('schedule_kind');
        $time = $request->input('schedule_time');
        $weekday = (int) $request->input('schedule_weekday');
        $monthday = (int) $request->input('schedule_monthday');
        $maxAge = (int) $request->input('max_age_days');
        $partSize = (int) $request->input('part_size_mb');

        if (!in_array($interval, Schedule::INTERVALS, true) || !in_array($kind, ['db', 'full'], true) || !Schedule::isValidTime($time)
            || $weekday < 1 || $weekday > 7 || $monthday < 1 || $monthday > 28 || $maxAge < 7 || $maxAge > 365 || $partSize < 8 || $partSize > 512) {
            return $this->fail($this->t('err_input'));
        }

        // Bereiche nur aus der Whitelist der config.php.
        $available = array_keys($this->services->env->configuredAreas);
        $areas = array_values(array_intersect($available, $request->inputList('areas')));
        $patterns = AreaPolicy::parsePatterns($request->input('exclude_patterns'));

        $fields = [
            'schedule_interval' => $interval, 'schedule_kind' => $kind, 'schedule_time' => $time, 'schedule_weekday' => $weekday,
            'schedule_monthday' => $monthday, 'max_age_days' => $maxAge, 'part_size_mb' => $partSize,
            'areas_json' => json_encode($areas, JSON_THROW_ON_ERROR), 'exclude_patterns' => implode("\n", $patterns), 'updated_by' => $user->id,
        ];
        $fields['next_run_at'] = $this->services->runner->computeNext($fields + ['schedule_interval' => $interval]);
        $this->services->store->updateSettings($fields);
        $this->audit($user, 'backup.settings_changed', 'backup', null, ['interval' => $interval, 'kind' => $kind, 'max_age_days' => $maxAge, 'areas' => $areas]);

        return $this->done($this->t('msg_saved'));
    }

    public function saveKey(Request $request): Response
    {
        $user = $this->guard($request, Role::Admin);

        if (($blocked = $this->stepUp($user, $request)) !== null) {
            return $blocked;
        }

        $keys = $this->services->keys;

        if ($request->input('action') === 'remove_emergency') {
            $keys->removeEmergency();
            $this->audit($user, 'backup.key_changed', 'backup', null, ['role' => 'emergency', 'removed' => true]);

            return $this->done($this->t('msg_saved'));
        }

        $role = $request->input('role') === 'emergency' ? 'emergency' : 'primary';
        $old = (string) ($this->services->store->settings()[$role . '_key_fp'] ?? '');

        try {
            $fingerprint = $keys->setRecipient($role, $request->input('public_key'));
        } catch (BackupFormatException|BackupException) {
            return $this->fail($this->t('err_key'));
        }

        $this->audit($user, 'backup.key_changed', 'backup', null, ['role' => $role, 'old_fp' => $old, 'new_fp' => $fingerprint]);

        return $this->done($this->t('msg_saved'));
    }

    public function saveDestination(Request $request): Response
    {
        $user = $this->guard($request, Role::Admin);

        if (($blocked = $this->stepUp($user, $request)) !== null) {
            return $blocked;
        }

        $store = $this->services->store;
        $id = (int) $request->input('id');
        $existing = $id > 0 ? $store->destination($id) : null;

        if ($id > 0 && $existing === null) {
            return $this->fail($this->t('err_notfound'));
        }

        $type = $existing !== null ? (string) $existing['dest_type'] : $request->input('dest_type');
        $name = trim($request->input('name'));
        $keep = (int) $request->input('keep_count');

        if (!in_array($type, ['local', 'ftp'], true) || $name === '' || mb_strlen($name) > 100 || $keep < 1 || $keep > 100) {
            return $this->fail($name === '' ? $this->t('err_name') : $this->t('err_input'));
        }

        $fields = ['name' => $name, 'keep_count' => $keep];
        $wantsEnabled = $request->input('enabled') === '1';
        $notice = '';

        if ($type === 'local') {
            if ($existing === null) {
                foreach ($store->destinations() as $other) {
                    if ($other['dest_type'] === 'local') {
                        return $this->fail($this->t('dest_local_exists'));
                    }
                }
            }

            $fields += ['dest_type' => 'local', 'enabled' => $wantsEnabled ? 1 : 0, 'status' => 'ok'];
        } else {
            $host = HostPolicy::normalize($request->input('host'));
            $port = (int) $request->input('port');
            $mode = $request->input('mode') === 'implicit' ? 'implicit' : 'explicit';
            $username = trim($request->input('username'));
            $password = $request->input('password');

            if (strlen($password) > 256) {
                return $this->fail($this->t('err_input'));
            }
            $ports = array_map('intval', (array) ($this->services->options['ftp_allowed_ports'] ?? CurlFtpClient::ALLOWED_PORTS));

            if (!HostPolicy::isValidHost($host) || str_contains($host, '[')) {
                return $this->fail($this->t('err_host'));
            }

            if (!in_array($port, $ports, true)) {
                return $this->fail($this->t('err_port'));
            }

            if ($username === '' || mb_strlen($username) > 128 || str_contains($username, ':') || preg_match('/[\x00-\x1f\x7f]/', $username) === 1) {
                return $this->fail($this->t('err_input'));
            }

            try {
                $base = FtpDriver::cleanBase(trim($request->input('base_path')) ?: '/');
            } catch (BackupException) {
                return $this->fail($this->t('err_path'));
            }

            if ((new HostPolicy(($this->services->options['allow_private_hosts'] ?? false) === true))->resolveAllowed($host) === null) {
                return $this->fail($this->t('code_host_blocked'));
            }

            $oldConfig = $existing !== null ? json_decode((string) ($existing['config_json'] ?? ''), true) : [];
            $oldConfig = is_array($oldConfig) ? $oldConfig : [];
            $connection = ['host' => $host, 'port' => $port, 'mode' => $mode, 'username' => $username];
            $changed = $existing === null || array_diff_assoc($connection, array_intersect_key($oldConfig, $connection)) !== []
                || ($oldConfig['base_path'] ?? '/') !== ($base === '' ? '/' : $base);
            $fields['config_json'] = json_encode($connection + ['base_path' => $base === '' ? '/' : $base], JSON_THROW_ON_ERROR);
            $fields['dest_type'] = 'ftp';
            $secretChanged = $password !== '';

            if ($secretChanged) {
                $fields['secret_enc'] = $this->services->secrets->encrypt(json_encode(['password' => $password], JSON_THROW_ON_ERROR));
            } elseif ($existing !== null && array_diff_assoc($connection, array_intersect_key($oldConfig, $connection)) !== []) {
                // Verbindungsdaten geaendert, aber kein neues Passwort: das alte Passwort darf nicht an einen neuen Server gehen.
                $fields['secret_enc'] = null;
                $notice = $this->t('dest_reset_secret');
            }

            $untested = $changed || $secretChanged;
            $status = $untested ? 'unconfigured' : (string) ($existing['status'] ?? 'unconfigured');
            $fields['status'] = $status;
            $fields['enabled'] = ($wantsEnabled && $status === 'ok') ? 1 : 0;

            if ($wantsEnabled && $status !== 'ok') {
                $notice = trim($notice . ' ' . $this->t('dest_untested'));
            }
        }

        if ($existing === null) {
            $newId = $store->createDestination($fields, $user->id);
            $this->audit($user, 'backup.destination_created', 'backup', $newId, ['type' => $type]);
        } else {
            $newId = (int) $existing['id'];
            $store->updateDestination($newId, $fields);
            $this->audit($user, 'backup.destination_updated', 'backup', $newId, ['type' => $type, 'fields' => array_keys($fields), 'secret_changed' => isset($fields['secret_enc'])]);
        }

        return $notice !== '' ? $this->redirectWith('/admin/backup', 'warning', $this->t('msg_saved') . ' ' . $notice) : $this->done($this->t('msg_saved'));
    }

    public function testDestination(Request $request): Response
    {
        $user = $this->guard($request, Role::Admin);
        $store = $this->services->store;
        $destination = $store->destination((int) $request->input('id'));

        if ($destination === null) {
            return $this->fail($this->t('err_notfound'));
        }

        $ok = false;
        $code = null;

        try {
            $this->services->drivers->make($destination)->test();
            $ok = true;
        } catch (Throwable $e) {
            $code = Failure::category($e);
        }

        $store->updateDestination((int) $destination['id'], [
            'status' => $ok ? 'ok' : 'error', 'last_test_at' => $store->nowString(), 'last_test_ok' => $ok ? 1 : 0, 'last_error_code' => $code,
        ]);
        $this->audit($user, 'backup.destination_tested', 'backup', (int) $destination['id'], ['ok' => $ok, 'category' => $code]);

        return $ok ? $this->done($this->t('msg_test_ok')) : $this->fail($this->lang->code($code));
    }

    public function deleteDestination(Request $request): Response
    {
        $user = $this->guard($request, Role::Admin);

        if (($blocked = $this->stepUp($user, $request)) !== null) {
            return $blocked;
        }

        $store = $this->services->store;
        $destination = $store->destination((int) $request->input('id'));

        if ($destination === null) {
            return $this->fail($this->t('err_notfound'));
        }

        if ($store->destinationInUse((int) $destination['id'])) {
            return $this->fail($this->t('err_inuse'));
        }

        $store->deleteDestination((int) $destination['id']);
        $this->audit($user, 'backup.destination_deleted', 'backup', (int) $destination['id'], ['type' => $destination['dest_type']]);

        return $this->done($this->t('msg_saved'));
    }

    public function newToken(Request $request): Response
    {
        $user = $this->guard($request, Role::Admin);

        if (($blocked = $this->stepUp($user, $request)) !== null) {
            return $blocked;
        }

        $token = bin2hex(random_bytes(24));
        $this->services->store->updateSettings(['cron_token_hash' => hash('sha256', $token), 'cron_fail_count' => 0, 'cron_fail_since' => null, 'updated_by' => $user->id]);
        $this->audit($user, 'backup.cron_token_created', 'backup');

        // Das Token wird nur in dieser einen Antwort angezeigt (keine Weiterleitung, nichts in Session oder Log).
        return $this->page($this->t('title'), Ui::notice($this->t('msg_token'), 'success') . $this->overview($request, $token), $user, '/admin/backup')
            ->withHeader('Cache-Control', 'no-store');
    }

    public function deleteRun(Request $request): Response
    {
        $user = $this->guard($request, Role::Admin);

        if (($blocked = $this->stepUp($user, $request)) !== null) {
            return $blocked;
        }

        $run = $this->services->store->run((int) $request->input('run_id'));
        $scope = $request->input('scope') === 'all' ? 'all' : 'local';

        if ($run === null || in_array((string) $run['status'], ['queued', 'running'], true)) {
            return $this->fail($this->t('err_notfound'));
        }

        $marked = (new Retention($this->services->store))->markRun((int) $run['id'], $scope);
        $this->audit($user, 'backup.deleted', 'backup', (int) $run['id'], ['scope' => $scope, 'marked' => $marked]);

        return $this->done($this->t('msg_deleted'));
    }

    /**
     * Authentifizierter Download einer lokalen Teildatei. Streamt direkt (kein Laden in den Speicher) und beendet die Anfrage.
     */
    public function download(Request $request, array $params): Response
    {
        $user = $this->guard($request, Role::Admin);
        $found = (new DownloadGuard($this->services))->resolve((int) ($params['id'] ?? 0), (string) ($params['seq'] ?? ''));

        if ($found === null) {
            throw new HttpException(404, $this->t('err_notfound'));
        }

        $this->audit($user, 'backup.downloaded', 'backup', (int) $params['id'], ['seq' => (int) $params['seq']]);
        $this->app->headers->send();
        $this->stream($found['path'], $found['name'], $found['size']);
        exit;
    }

    /** @codeCoverageIgnore Streaming mit exit; die Pruefung steckt in DownloadGuard. */
    private function stream(string $path, string $name, int $size): void
    {
        $handle = @fopen($path, 'rb');

        if ($handle === false) {
            http_response_code(404);

            return;
        }

        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        @set_time_limit(0);
        http_response_code(200);

        foreach (DownloadGuard::headers($name, $size) as $header => $value) {
            header($header . ': ' . $value);
        }

        while (!feof($handle) && connection_aborted() === 0) {
            echo fread($handle, 65536);
            flush();
        }

        fclose($handle);
    }

    // ------------------------------------------------------------------ Hilfen

    private function t(string $key, array $params = []): string
    {
        return $this->lang->t($key, $params);
    }

    private function done(string $message): Response
    {
        return $this->redirectWith('/admin/backup', 'success', $message);
    }

    private function fail(string $message): Response
    {
        return $this->redirectWith('/admin/backup', 'danger', $message);
    }

    /** Prueft das Step-up-Passwort. Liefert null bei Erfolg, sonst die Fehlerantwort. */
    private function stepUp(User $user, Request $request): ?Response
    {
        $result = (new StepUp($this->services->store))->verify($this->app->users()->passwordHash($user->id), $request->input('current_password'));

        if ($result === StepUp::OK) {
            return null;
        }

        $this->audit($user, 'backup.stepup_failed', 'backup', null, ['blocked' => $result === StepUp::BLOCKED]);

        return $this->fail($result === StepUp::BLOCKED ? $this->t('err_blocked') : $this->t('err_password'));
    }

    private function stepUpField(): string
    {
        return Form::text('current_password', $this->t('stepup'), '', ' required autocomplete="current-password" maxlength="1024"', 'password');
    }

    // ------------------------------------------------------------------ Ansicht

    private function overview(Request $request, ?string $newToken): string
    {
        $store = $this->services->store;
        $settings = $store->settings();
        $destinations = $store->destinations();
        $active = $store->activeRun();
        $notices = '';

        if (!extension_loaded('sodium') || !extension_loaded('zlib')) {
            $notices .= Ui::notice($this->t('err_sodium'), 'danger');
        }

        if ((string) ($settings['primary_key_text'] ?? '') === '') {
            $notices .= Ui::notice($this->t('no_key'), 'warning');
        }

        if ($store->destinations(true) === []) {
            $notices .= Ui::notice($this->t('no_destination'), 'warning');
        }

        $tick = Store::toDate($settings['last_tick_at'] !== null ? (string) $settings['last_tick_at'] : null);

        if ($tick === null) {
            $notices .= Ui::notice($this->t('cron_missing'), 'warning');
        } elseif ($tick < $store->now()->modify('-1 day')) {
            $notices .= Ui::notice($this->t('cron_stale'), 'warning');
        }

        return Ui::pageHeader($this->t('title'), '', $this->t('intro'))
            . Ui::notice($this->t('privacy'), 'info')
            . $notices
            . $this->statusCard($settings, $active)
            . $this->keysCard($settings)
            . $this->destinationsCard($destinations, (int) $request->query('edit'))
            . $this->scheduleCard($settings)
            . $this->cronCard($settings, $newToken)
            . $this->historyCard()
            . '<section class="card" aria-labelledby="bk-restore"><h2 id="bk-restore">' . View::e($this->t('restore')) . '</h2><p>' . View::e($this->t('restore_intro'))
            . '</p><pre class="code-view"><code>' . View::e($this->t('restore_steps')) . '</code></pre></section>';
    }

    /** @param array<string,mixed> $settings @param array<string,mixed>|null $active */
    private function statusCard(array $settings, ?array $active): string
    {
        $rows = '<dl class="meta-list">'
            . '<dt>' . View::e($this->t('last_success')) . '</dt><dd>' . View::e($this->time($settings['last_success_at'])) . '</dd>'
            . '<dt>' . View::e($this->t('last_tick')) . '</dt><dd>' . View::e($this->time($settings['last_tick_at'])) . '</dd>'
            . '<dt>' . View::e($this->t('next_run')) . '</dt><dd>' . View::e($this->time($settings['next_run_at'], 'none')) . '</dd>'
            . '</dl>';
        $runBox = '';

        if ($active !== null) {
            $runBox = '<div role="status" aria-live="polite"><h3>' . View::e($this->t('active_run')) . ' #' . (int) $active['id'] . '</h3><p>'
                . Ui::badge($this->runStatus((string) $active['status']), 'info') . ' ' . View::e($this->t('phase')) . ': <code>' . View::e((string) $active['phase']) . '</code></p>'
                . Form::open('/admin/backup/cancel', ' class="inline-form" data-confirm="' . View::e($this->t('cancel_confirm')) . '"')
                . '<input type="hidden" name="run_id" value="' . (int) $active['id'] . '">' . Form::submit($this->t('cancel'), 'button button-secondary') . '</form></div>';
        }

        $form = Form::open('/admin/backup/run')
            . Form::select('kind', $this->t('kind'), ['full' => $this->t('kind_full'), 'db' => $this->t('kind_db')], 'full')
            . '<p class="muted">' . View::e($this->t('run_now_hint')) . '</p>'
            . Form::submit($this->t('start')) . '</form>';

        return '<section class="card" aria-labelledby="bk-status"><h2 id="bk-status">' . View::e($this->t('status')) . '</h2>' . $rows . $runBox
            . '<h3>' . View::e($this->t('run_now')) . '</h3>' . $form . '</section>';
    }

    /** @param array<string,mixed> $settings */
    private function keysCard(array $settings): string
    {
        $fp = static fn (?string $value): string => $value !== null && $value !== '' ? '<code>' . View::e($value) . '</code>' : '–';
        $list = '<dl class="meta-list">'
            . '<dt>' . View::e($this->t('key_primary')) . '</dt><dd>' . $fp($settings['primary_key_fp'] ?? null) . '</dd>'
            . '<dt>' . View::e($this->t('key_emergency')) . '</dt><dd>' . $fp($settings['emergency_key_fp'] ?? null) . '</dd>'
            . '<dt>' . View::e($this->t('key_signer')) . '</dt><dd>' . $fp($settings['signing_fp'] ?? null) . '</dd></dl>'
            . '<p class="muted">' . View::e($this->t('key_signer_hint')) . '</p>';
        $form = Form::open('/admin/backup/key')
            . Form::select('role', $this->t('key_role'), ['primary' => $this->t('key_primary'), 'emergency' => $this->t('key_emergency')], 'primary')
            . Form::textarea('public_key', $this->t('key_public'), '', ' rows="2" required spellcheck="false" autocomplete="off" placeholder="mgdbk-pk1:…"')
            . '<p class="muted">' . View::e($this->t('stepup_hint')) . '</p>' . $this->stepUpField() . Form::submit($this->t('key_save')) . '</form>';
        $remove = (string) ($settings['emergency_key_fp'] ?? '') !== ''
            ? Form::open('/admin/backup/key', ' data-confirm="' . View::e($this->t('key_remove_emergency') . '?') . '"') . '<input type="hidden" name="action" value="remove_emergency">'
                . $this->stepUpField() . Form::submit($this->t('key_remove_emergency'), 'button button-secondary') . '</form>'
            : '';

        return '<section class="card" aria-labelledby="bk-keys"><h2 id="bk-keys">' . View::e($this->t('keys')) . '</h2><p>' . View::e($this->t('keys_intro')) . '</p>'
            . $list . $form . $remove . '</section>';
    }

    /** @param list<array<string,mixed>> $destinations */
    private function destinationsCard(array $destinations, int $editId): string
    {
        $rows = [];

        foreach ($destinations as $destination) {
            $config = json_decode((string) ($destination['config_json'] ?? ''), true);
            $config = is_array($config) ? $config : [];
            $status = (string) $destination['status'];
            $variant = $status === 'ok' ? 'success' : ($status === 'error' ? 'danger' : 'neutral');
            $detail = $destination['dest_type'] === 'ftp' ? '<br><span class="muted">' . View::e((string) ($config['host'] ?? '')) . ':' . (int) ($config['port'] ?? 0) . '</span>' : '';
            $error = (string) ($destination['last_error_code'] ?? '') !== '' && $status === 'error'
                ? '<br><span class="muted">' . View::e($this->lang->code((string) $destination['last_error_code'])) . '</span>' : '';
            $id = (int) $destination['id'];
            $rows[] = [
                '<strong>' . View::e((string) $destination['name']) . '</strong>' . $detail,
                View::e($this->t('dest_type_' . $destination['dest_type'])),
                Ui::badge($this->t('status_' . $status), $variant) . ' ' . Ui::badge($this->t($destination['enabled'] ? 'enabled_yes' : 'enabled_no'), $destination['enabled'] ? 'success' : 'neutral') . $error,
                (string) (int) $destination['keep_count'],
                Ui::link('/admin/backup?edit=' . $id . '#bk-dest-form', $this->t('dest_edit'), 'button button-ghost')
                    . Form::open('/admin/backup/destination/test', ' class="inline-form"') . '<input type="hidden" name="id" value="' . $id . '">'
                    . Form::submit($this->t('dest_test'), 'button button-secondary') . '</form>'
                    . Form::open('/admin/backup/destination/delete', ' data-confirm="' . View::e($this->t('dest_delete_confirm')) . '"') . '<input type="hidden" name="id" value="' . $id . '">'
                    . $this->stepUpField() . Form::submit($this->t('dest_delete'), 'button button-ghost') . '</form>',
            ];
        }

        $table = $rows === [] ? Ui::emptyState($this->t('dest_none'))
            : Ui::table([$this->t('dest_name'), $this->t('dest_type'), $this->t('dest_status'), $this->t('dest_keep'), $this->t('col_action')], $rows);

        return '<section class="card" aria-labelledby="bk-dest"><h2 id="bk-dest">' . View::e($this->t('destinations')) . '</h2><p class="muted">' . View::e($this->t('dest_untested')) . '</p>'
            . $table . $this->destinationForm($editId) . '</section>';
    }

    private function destinationForm(int $editId): string
    {
        $existing = $editId > 0 ? $this->services->store->destination($editId) : null;
        $config = $existing !== null ? json_decode((string) ($existing['config_json'] ?? ''), true) : [];
        $config = is_array($config) ? $config : [];
        $type = $existing !== null ? (string) $existing['dest_type'] : 'ftp';
        $typeField = $existing !== null
            ? '<p><strong>' . View::e($this->t('dest_type')) . ':</strong> ' . View::e($this->t('dest_type_' . $type)) . '</p>'
            : Form::select('dest_type', $this->t('dest_type'), ['ftp' => $this->t('dest_type_ftp'), 'local' => $this->t('dest_type_local')], 'ftp');

        return '<h3 id="bk-dest-form">' . View::e($existing !== null ? $this->t('dest_edit') : $this->t('dest_add')) . '</h3>'
            . Form::open('/admin/backup/destination')
            . '<input type="hidden" name="id" value="' . (int) ($existing['id'] ?? 0) . '">' . $typeField
            . Form::text('name', $this->t('dest_name'), (string) ($existing['name'] ?? ''), ' required maxlength="100"')
            . Form::text('keep_count', $this->t('dest_keep'), (string) ($existing['keep_count'] ?? 7), ' required min="1" max="100" inputmode="numeric"', 'number')
            . Form::checkbox('enabled', $this->t('dest_enabled'), $existing !== null && (int) $existing['enabled'] === 1)
            . ($type === 'ftp'
                ? Form::text('host', $this->t('dest_host'), (string) ($config['host'] ?? ''), ' maxlength="253" autocomplete="off" spellcheck="false"')
                    . Form::text('port', $this->t('dest_port'), (string) ($config['port'] ?? 21), ' min="21" max="990" inputmode="numeric"', 'number')
                    . Form::select('mode', $this->t('dest_mode'), ['explicit' => $this->t('dest_mode_explicit'), 'implicit' => $this->t('dest_mode_implicit')], (string) ($config['mode'] ?? 'explicit'))
                    . Form::text('username', $this->t('dest_user'), (string) ($config['username'] ?? ''), ' maxlength="128" autocomplete="off"')
                    . Form::text('password', $this->t('dest_password'), '', ' autocomplete="new-password" maxlength="256"', 'password')
                    . '<p class="muted">' . View::e($this->t('dest_password_hint')) . '</p>'
                    . Form::text('base_path', $this->t('dest_base'), (string) ($config['base_path'] ?? '/backups'), ' maxlength="255" autocomplete="off" spellcheck="false"')
                : '')
            . '<p class="muted">' . View::e($this->t('stepup_hint')) . '</p>' . $this->stepUpField()
            . Form::submit($this->t('dest_save')) . '</form>';
    }

    /** @param array<string,mixed> $settings */
    private function scheduleCard(array $settings): string
    {
        $configured = array_keys($this->services->env->configuredAreas);
        $selected = ($settings['areas_json'] ?? null) === null ? $configured : (array) json_decode((string) $settings['areas_json'], true);
        $weekdays = [];

        for ($i = 1; $i <= 7; $i++) {
            $weekdays[(string) $i] = $this->t('weekday_' . $i);
        }

        $areas = $configured === [] ? '<p class="muted">' . View::e($this->t('areas_none')) . '</p>'
            : Form::checkboxes('areas', $this->t('areas'), array_combine($configured, $configured), array_map('strval', $selected));

        return '<section class="card" aria-labelledby="bk-sched"><h2 id="bk-sched">' . View::e($this->t('schedule')) . '</h2>'
            . Form::open('/admin/backup/settings')
            . Form::select('schedule_interval', $this->t('interval'), ['off' => $this->t('interval_off'), 'daily' => $this->t('interval_daily'), 'weekly' => $this->t('interval_weekly'), 'monthly' => $this->t('interval_monthly')], (string) $settings['schedule_interval'])
            . Form::select('schedule_kind', $this->t('sched_kind'), ['full' => $this->t('kind_full'), 'db' => $this->t('kind_db')], (string) $settings['schedule_kind'])
            . Form::text('schedule_time', $this->t('sched_time'), (string) $settings['schedule_time'], ' required pattern="([01][0-9]|2[0-3]):[0-5][0-9]" placeholder="03:00"')
            . Form::select('schedule_weekday', $this->t('sched_weekday'), $weekdays, (string) (int) $settings['schedule_weekday'])
            . Form::text('schedule_monthday', $this->t('sched_monthday'), (string) (int) $settings['schedule_monthday'], ' required min="1" max="28" inputmode="numeric"', 'number')
            . Form::text('max_age_days', $this->t('max_age'), (string) (int) $settings['max_age_days'], ' required min="7" max="365" inputmode="numeric"', 'number')
            . '<p class="muted">' . View::e($this->t('max_age_hint')) . '</p>'
            . Form::text('part_size_mb', $this->t('part_size'), (string) (int) $settings['part_size_mb'], ' required min="8" max="512" inputmode="numeric"', 'number')
            . $areas . '<p class="muted">' . View::e($this->t('areas_hint')) . '</p>'
            . Form::textarea('exclude_patterns', $this->t('excludes'), (string) ($settings['exclude_patterns'] ?? ''), ' rows="3" spellcheck="false"')
            . '<p class="muted">' . View::e($this->t('stepup_hint')) . '</p>' . $this->stepUpField()
            . Form::submit($this->t('sched_save')) . '</form></section>';
    }

    /** @param array<string,mixed> $settings */
    private function cronCard(array $settings, ?string $newToken): string
    {
        $base = rtrim($this->app->config->string('app.url'), '/');
        $url = ($base !== '' ? $base : 'https://example.org') . View::url('/backup-cron/<TOKEN>');
        $has = (string) ($settings['cron_token_hash'] ?? '') !== '';
        $shown = $newToken !== null
            ? '<div role="status"><p>' . View::e($this->t('cron_shown')) . '</p><pre class="code-view"><code>' . View::e(($base !== '' ? $base : 'https://example.org') . View::url('/backup-cron/' . $newToken)) . '</code></pre></div>'
            : '';

        return '<section class="card" aria-labelledby="bk-cron"><h2 id="bk-cron">' . View::e($this->t('cron')) . '</h2><p>' . View::e($this->t('cron_intro')) . '</p>'
            . '<p>' . View::e($this->t($has ? 'cron_set' : 'cron_unset')) . '</p>' . $shown
            . '<p><strong>' . View::e($this->t('cron_url')) . '</strong></p><pre class="code-view"><code>' . View::e($url) . '</code></pre>'
            . '<p><strong>' . View::e($this->t('cron_cli')) . '</strong></p><pre class="code-view"><code>php modules/backup/tools/cron.php</code></pre>'
            . Form::open('/admin/backup/token') . '<p class="muted">' . View::e($this->t('cron_new_hint')) . '</p>' . $this->stepUpField()
            . Form::submit($this->t('cron_new'), 'button button-secondary') . '</form></section>';
    }

    private function historyCard(): string
    {
        $rows = [];

        foreach ($this->services->store->runs(30) as $run) {
            $status = (string) $run['status'];
            $variant = match ($status) {
                'completed' => 'success', 'partial' => 'warning', 'failed' => 'danger', default => 'neutral',
            };
            $error = $status === 'failed' && (string) $run['error_code'] !== '' ? '<br><span class="muted">' . View::e($this->lang->code((string) $run['error_code'])) . '</span>' : '';
            $rows[] = [
                '#' . (int) $run['id'],
                View::e($this->time($run['finished_at'] ?? $run['queued_at'])),
                View::e($this->t('kind_' . $run['kind'])),
                View::e($this->t('trigger_' . $run['trigger_type'])),
                Ui::badge($this->runStatus($status), $variant) . $error,
                View::e(self::bytes((int) $run['cipher_bytes'])),
                (string) (int) $run['parts'],
                Ui::link('/admin/backup/run/' . (int) $run['id'], $this->t('details'), 'button button-ghost'),
            ];
        }

        return '<section class="card" aria-labelledby="bk-hist"><h2 id="bk-hist">' . View::e($this->t('history')) . '</h2>'
            . ($rows === [] ? Ui::emptyState($this->t('history_none'))
                : Ui::table([$this->t('col_id'), $this->t('col_time'), $this->t('col_kind'), $this->t('col_trigger'), $this->t('col_status'), $this->t('col_size'), $this->t('col_parts'), $this->t('col_action')], $rows))
            . '</section>';
    }

    /** @param array<string,mixed> $run */
    private function detailsBody(array $run): string
    {
        $store = $this->services->store;
        $id = (int) $run['id'];
        $local = (new DownloadGuard($this->services))->localAvailable($id);
        $partRows = [];

        foreach ($store->parts($id) as $part) {
            $available = $local && $part['status'] === 'ready';
            $partRows[] = [
                (string) (int) $part['seq'],
                '<code>' . View::e((string) $part['file_name']) . '</code>',
                View::e((string) $part['part_kind']),
                View::e(self::bytes((int) $part['cipher_bytes'])),
                $available ? Ui::link('/admin/backup/download/' . $id . '/' . (int) $part['seq'], $this->t('download'), 'button button-ghost') : View::e($this->t('not_local')),
            ];
        }

        $warnings = '';

        foreach ((array) json_decode((string) ($run['warnings_json'] ?? ''), true) as $warning) {
            if (is_array($warning) && isset($warning['code'])) {
                $warnings .= '<li>' . View::e($this->t('warn_' . $warning['code'], ['count' => (int) ($warning['count'] ?? 0)])) . '</li>';
            }
        }

        $destRows = [];

        foreach ($store->runDestinations($id) as $rd) {
            $destination = $store->destination((int) $rd['destination_id']);
            $destRows[] = [View::e((string) ($destination['name'] ?? '?')), View::e((string) $rd['status']), View::e($this->lang->code($rd['error_code'] !== null ? (string) $rd['error_code'] : null))];
        }

        $meta = '<dl class="meta-list">'
            . '<dt>' . View::e($this->t('col_status')) . '</dt><dd>' . View::e($this->runStatus((string) $run['status'])) . ($run['error_code'] !== null ? ' – ' . View::e($this->lang->code((string) $run['error_code'])) : '') . '</dd>'
            . '<dt>' . View::e($this->t('col_kind')) . '</dt><dd>' . View::e($this->t('kind_' . $run['kind'])) . '</dd>'
            . '<dt>' . View::e($this->t('col_time')) . '</dt><dd>' . View::e($this->time($run['started_at'] ?? $run['queued_at'])) . ' → ' . View::e($this->time($run['finished_at'])) . '</dd>'
            . '<dt>' . View::e($this->t('consistency')) . '</dt><dd>' . View::e($run['db_consistency'] === 'snapshot' ? $this->t('cons_snapshot') : ($run['db_consistency'] === 'per_tick' ? $this->t('cons_per_tick') : '–')) . '</dd>'
            . '<dt>' . View::e($this->t('key_fps')) . '</dt><dd><code>' . View::e((string) ($run['key_fps'] ?? '')) . '</code></dd>'
            . '<dt>' . View::e($this->t('signer')) . '</dt><dd><code>' . View::e((string) ($run['signer_fp'] ?? '')) . '</code></dd>'
            . '<dt>' . View::e($this->t('manifest_sha')) . '</dt><dd><code>' . View::e((string) ($run['manifest_sha256'] ?? '')) . '</code></dd></dl>';

        $delete = in_array((string) $run['status'], ['queued', 'running'], true) ? '' : '<h3>' . View::e($this->t('delete_all')) . '</h3>'
            . Form::open('/admin/backup/run/delete', ' data-confirm="' . View::e($this->t('delete_confirm')) . '"') . '<input type="hidden" name="run_id" value="' . $id . '">'
            . Form::select('scope', $this->t('col_action'), ['local' => $this->t('delete_local'), 'all' => $this->t('delete_all')], 'local')
            . $this->stepUpField() . Form::submit($this->t('col_action'), 'button button-secondary') . '</form>';

        return Ui::pageHeader($this->t('run_title', ['id' => $id]), Ui::link('/admin/backup', $this->t('back'), 'button button-ghost'))
            . '<section class="card">' . $meta . ($warnings !== '' ? '<h3>' . View::e($this->t('warnings')) . '</h3><ul>' . $warnings . '</ul>' : '') . '</section>'
            . '<section class="card" aria-labelledby="bk-parts"><h2 id="bk-parts">' . View::e($this->t('parts')) . '</h2><p class="muted">' . View::e($this->t('download_hint')) . '</p>'
            . ($partRows === [] ? Ui::emptyState($this->t('none')) : Ui::table(['#', $this->t('part_name'), $this->t('col_kind'), $this->t('col_size'), $this->t('col_action')], $partRows)) . '</section>'
            . '<section class="card" aria-labelledby="bk-rd"><h2 id="bk-rd">' . View::e($this->t('remote')) . '</h2>'
            . ($destRows === [] ? Ui::emptyState($this->t('none')) : Ui::table([$this->t('dest_name'), $this->t('col_status'), $this->t('col_action')], $destRows)) . $delete . '</section>';
    }

    private function runStatus(string $status): string
    {
        return $this->t('run_' . $status);
    }

    private function time(mixed $value, string $empty = 'never'): string
    {
        return is_string($value) && $value !== '' ? $value . ' UTC' : $this->t($empty);
    }

    private static function bytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $value = (float) $bytes;

        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            $i++;
        }

        return ($i === 0 ? (string) $bytes : number_format($value, 1, ',', '.')) . ' ' . $units[$i];
    }
}
