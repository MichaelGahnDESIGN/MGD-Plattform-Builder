<?php

declare(strict_types=1);

namespace MGD\Backup;

use Closure;
use MGD\Backup\Db\Dumper;
use MGD\Backup\Destinations\DestinationDriver;
use MGD\Backup\Destinations\DriverFactory;
use MGD\Backup\Destinations\PredictsPartRef;
use MGD\Backup\Files\AreaPolicy;
use MGD\Backup\Files\Packer;
use MGD\Backup\Files\Scanner;
use MGD\Backup\Format\BackupFormat;
use MGD\Backup\Format\ManifestCodec;
use MGD\Backup\Format\PartWriter;
use MGD\Backup\Format\RecipientKey;
use Throwable;

/**
 * Zustandsautomat eines Sicherungslaufs, getrieben von Cron-Aufrufen (tick). Kein Queue-Worker, kein exec().
 *
 * Phasen: queued/preparing -> dumping_db -> (full: scanning_files -> packing_files) -> finalizing -> uploading -> completing.
 * Jeder tick() hat ein Zeitbudget und arbeitet den aktiven Lauf so weit ab, wie es reicht; der Zustand (Cursor) steht in
 * der Datenbank. Ein Teil wird nie halb fortgesetzt: bricht ein Aufruf mitten im Teil ab, wird die Teildatei verworfen und ab
 * dem Cursor neu geschrieben. Parallelaufrufe schliessen eine flock-Sperre (Dateisystem) und der eindeutige active_slot in
 * backup_runs aus.
 *
 * Abweichung vom Referenzverfahren (MGD-Support-Hub): Erst werden alle Teile lokal geschrieben, dann hochgeladen (kein Gegendruck). Bei reinen
 * Remote-Zielen wird ein Teil lokal geloescht, sobald er ueberall hochgeladen ist.
 */
final class Runner
{
    private const BACKOFF_BASE_SECONDS = 300;

    private const BACKOFF_MAX_SECONDS = 7200;

    /** @var Closure(string,array<string,mixed>,?int):void|null */
    private ?Closure $audit;

    /**
     * @param (Closure(string,array<string,mixed>,?int):void)|null $audit Audit-Senke (Aktion, Metadaten, Nutzer)
     */
    public function __construct(
        private readonly Store $store,
        private readonly Paths $paths,
        private readonly Keys $keys,
        private readonly DriverFactory $drivers,
        private readonly Dumper $dumper,
        private readonly Environment $env,
        ?Closure $audit = null,
    ) {
        $this->audit = $audit;
    }

    // ------------------------------------------------------------------ Einstieg

    /**
     * Ein Cron-Aufruf: plant faellige Laeufe, arbeitet den aktiven Lauf ab, verarbeitet Loeschungen.
     *
     * @return array{state:string,run_id:?int,phase:?string}
     */
    public function tick(Budget $budget): array
    {
        $lock = $this->lock();

        if ($lock === null) {
            return ['state' => 'busy', 'run_id' => null, 'phase' => null];
        }

        try {
            $this->store->updateSettings(['last_tick_at' => $this->store->nowString()]);
            $this->scheduleIfDue();
            $run = $this->store->activeRun();
            $result = ['state' => 'idle', 'run_id' => null, 'phase' => null];

            if ($run !== null) {
                $run = $this->advance($run, $budget);
                $result = ['state' => 'worked', 'run_id' => (int) $run['id'], 'phase' => (string) $run['phase']];
            }

            $this->processDeletions($budget);
            $this->sweepOrphans();

            return $result;
        } finally {
            $this->unlock($lock);
        }
    }

    /**
     * Legt einen Lauf an (manuell, Zeitplan oder CLI). Startet NICHT selbst: der naechste Cron-Aufruf arbeitet ihn ab.
     *
     * @throws BackupException backup_already_active, key_missing, no_destination_enabled
     */
    public function enqueue(string $kind, string $trigger, ?int $userId = null): int
    {
        if (!in_array($kind, ['db', 'full'], true) || !in_array($trigger, ['manual', 'schedule', 'cli'], true)) {
            throw new BackupException('internal_error');
        }

        if ($this->store->activeRun() !== null) {
            throw new BackupException('backup_already_active');
        }

        $this->keys->recipients(); // wirft key_missing

        if ($this->store->destinations(true) === []) {
            throw new BackupException('no_destination_enabled');
        }

        $uuid = self::uuid();
        $created = $this->store->nowString();
        $folder = Paths::folderName($created, $kind, $uuid);

        try {
            $id = $this->store->createRun(['uuid' => $uuid, 'kind' => $kind, 'trigger_type' => $trigger, 'folder_name' => $folder, 'requested_by' => $userId]);
        } catch (BackupException) {
            throw new BackupException('backup_already_active'); // active_slot ist eindeutig: parallel angelegt
        }

        $this->record('backup.requested', ['run_id' => $id, 'kind' => $kind, 'trigger' => $trigger], $userId);

        return $id;
    }

    public function requestCancel(int $runId, ?int $userId = null): void
    {
        $run = $this->store->run($runId);

        if ($run === null || !in_array((string) $run['status'], ['queued', 'running'], true)) {
            throw new BackupException('internal_error');
        }

        $this->store->updateRun($runId, ['cancel_requested_at' => $this->store->nowString()]);
        $this->record('backup.cancel_requested', ['run_id' => $runId], $userId);
    }

    // ------------------------------------------------------------------ Zeitplan

    private function scheduleIfDue(): void
    {
        $settings = $this->store->settings();
        $interval = (string) $settings['schedule_interval'];

        if (!in_array($interval, ['daily', 'weekly', 'monthly'], true)) {
            if ($settings['next_run_at'] !== null) {
                $this->store->updateSettings(['next_run_at' => null]);
            }

            return;
        }

        $now = $this->store->now();
        $next = Store::toDate($settings['next_run_at'] !== null ? (string) $settings['next_run_at'] : null);

        if ($next === null) {
            $this->store->updateSettings(['next_run_at' => $this->computeNext($settings, $now)]);

            return;
        }

        if ($next > $now) {
            return;
        }

        // Faellig: naechsten Termin ab JETZT setzen (kein Nachholstau), dann (falls frei) einen Lauf anlegen.
        $this->store->updateSettings(['next_run_at' => $this->computeNext($settings, $now)]);

        if ($this->store->activeRun() !== null) {
            return;
        }

        try {
            $this->enqueue((string) $settings['schedule_kind'] === 'db' ? 'db' : 'full', 'schedule', null);
        } catch (BackupException $e) {
            $this->record('backup.failed', ['error_code' => Failure::category($e), 'trigger' => 'schedule'], null);
        }
    }

    /** @param array<string,mixed> $settings */
    public function computeNext(array $settings, ?\DateTimeImmutable $after = null): ?string
    {
        $next = Schedule::nextRun(
            (string) $settings['schedule_interval'],
            (string) $settings['schedule_time'],
            (int) $settings['schedule_weekday'],
            (int) $settings['schedule_monthday'],
            $after ?? $this->store->now(),
            $this->env->timezone,
        );

        return $next?->format(Store::DATE_FORMAT);
    }

    // ------------------------------------------------------------------ Lauf abarbeiten

    /**
     * @param array<string,mixed> $run
     * @return array<string,mixed> der Lauf nach der Arbeit
     */
    private function advance(array $run, Budget $budget): array
    {
        $guard = 0;

        while ($guard++ < 10000) {
            $run = $this->store->run((int) $run['id']) ?? $run;

            if (!in_array((string) $run['status'], ['queued', 'running'], true)) {
                break;
            }

            // Ist das Budget aufgebraucht, werden nur noch die kurzen Abschlussphasen zu Ende gebracht.
            if ($budget->exhausted() && !in_array((string) $run['phase'], ['finalizing', 'completing'], true)) {
                break;
            }

            try {
                if ($run['cancel_requested_at'] !== null) {
                    $this->cancel($run);
                    continue;
                }

                if ($this->tooOld($run)) {
                    throw new BackupException('timeout_run');
                }

                $continue = match ((string) $run['phase']) {
                    'queued' => $this->prepare($run),
                    'dumping_db' => $this->dumpDb($run, $budget),
                    'scanning_files' => $this->scanFiles($run),
                    'packing_files' => $this->packFiles($run, $budget),
                    'finalizing' => $this->finalize($run),
                    'uploading' => $this->upload($run, $budget),
                    'completing' => $this->complete($run),
                    default => throw new BackupException('internal_error'),
                };
            } catch (Throwable $e) {
                $this->fail($run, Failure::category($e));
                break;
            }

            $this->store->updateRun((int) $run['id'], ['heartbeat_at' => $this->store->nowString()]);

            if (!$continue) {
                break;
            }
        }

        return $this->store->run((int) $run['id']) ?? $run;
    }

    private function tooOld(array $run): bool
    {
        $started = Store::toDate($run['started_at'] !== null ? (string) $run['started_at'] : (string) $run['queued_at']);

        return $started !== null && $started < $this->store->now()->modify('-' . max(1, $this->env->maxRunHours) . ' hours');
    }

    // ------------------------------------------------------------------ Phase: prepare

    /** @param array<string,mixed> $run */
    private function prepare(array $run): bool
    {
        $id = (int) $run['id'];
        $recipients = $this->keys->recipients();
        $this->keys->ensureSigning();
        $destinations = $this->store->destinations(true);

        if ($destinations === []) {
            throw new BackupException('no_destination_enabled');
        }

        $this->paths->ensureRunDir((string) $run['folder_name']);
        $this->assertSpace($this->databaseBytes());

        foreach ($destinations as $destination) {
            if ($this->store->runDestination($id, (int) $destination['id']) === null) {
                $this->store->addRunDestination($id, (int) $destination['id']);
            }
        }

        $cursor = [
            'db' => $this->dumper->initialState(),
            'next_seq' => 1,
            'recipients' => array_map(static fn (RecipientKey $key): string => $key->toText(), $recipients),
            'warnings' => [],
            'db_first' => true,
            'snapshot' => $this->dumper->supportsSnapshot(),
        ];

        $this->store->updateRun($id, [
            'status' => 'running',
            'phase' => 'dumping_db',
            'started_at' => $this->store->nowString(),
            'heartbeat_at' => $this->store->nowString(),
            'cursor_json' => $this->encode($cursor),
            'key_fps' => implode(',', $this->keys->fingerprints()),
            'signer_fp' => $this->keys->signingFingerprint(),
            'app_version' => substr($this->env->appVersion, 0, 40),
        ]);
        $this->record('backup.started', ['run_id' => $id, 'kind' => $run['kind']], null);

        return true;
    }

    // ------------------------------------------------------------------ Phase: DB

    /** @param array<string,mixed> $run */
    private function dumpDb(array $run, Budget $budget): bool
    {
        $id = (int) $run['id'];
        $cursor = $this->cursor($run);
        $seq = (int) $cursor['next_seq'];
        $writer = $this->openPart($run, $cursor, BackupFormat::KIND_DB, $seq, 'db');

        try {
            $result = $this->dumper->writeNextPart($writer, (array) $cursor['db'], $budget, $this->partBytes());
            $part = $writer->finish();
        } catch (Throwable $e) {
            $writer->abort();

            throw $e;
        }

        $first = (bool) $cursor['db_first'];
        $cursor['db'] = $result['state'];
        $cursor['db_first'] = false;
        $cursor['next_seq'] = $seq + 1;

        if ($result['done']) {
            // Ein Dump in einem Durchgang mit Lesesicht ist konsistent (snapshot), sonst nur tabellenweise (per_tick).
            $consistent = $first && (bool) $cursor['snapshot'];
            $stats = (array) $result['state']['stats'];
            $this->store->updateRun($id, [
                'db_consistency' => $consistent ? 'snapshot' : 'per_tick',
                'db_tables' => count($stats),
                'db_rows' => array_sum($stats),
                'phase' => (string) $run['kind'] === 'full' ? 'scanning_files' : 'finalizing',
            ]);

            if (!$consistent) {
                $cursor['warnings'][] = ['code' => 'db_not_atomic', 'count' => 1];
            }
        }

        $this->savePart($id, $part, $seq, 'db', $cursor);

        return true;
    }

    // ------------------------------------------------------------------ Phase: Dateien

    /** @param array<string,mixed> $run */
    private function scanFiles(array $run): bool
    {
        $id = (int) $run['id'];
        $cursor = $this->cursor($run);
        $settings = $this->store->settings();
        $areas = $this->selectedAreas($settings);
        $patterns = AreaPolicy::parsePatterns((string) ($settings['exclude_patterns'] ?? ''));
        $stats = (new Scanner($this->paths))->buildIndex((string) $run['folder_name'], $areas, $patterns);
        $this->assertSpace($this->databaseBytes() + $stats['bytes']);

        $packer = new Packer($this->paths);
        $cursor['files'] = $packer->initialState(array_keys($areas)) + ['scan' => $stats];

        foreach (['skipped_links', 'skipped_unsafe', 'skipped_unreadable'] as $key) {
            if ($stats[$key] > 0) {
                $cursor['warnings'][] = ['code' => $key, 'count' => $stats[$key]];
            }
        }

        $this->store->updateRun($id, ['phase' => $stats['files'] > 0 ? 'packing_files' : 'finalizing', 'cursor_json' => $this->encode($cursor)]);

        return true;
    }

    /** @param array<string,mixed> $run */
    private function packFiles(array $run, Budget $budget): bool
    {
        $id = (int) $run['id'];
        $cursor = $this->cursor($run);
        $folder = (string) $run['folder_name'];
        $seq = (int) $cursor['next_seq'];
        $writer = $this->openPart($run, $cursor, BackupFormat::KIND_FILES, $seq, 'files');
        $areas = $this->selectedAreas($this->store->settings());

        try {
            $result = (new Packer($this->paths))->writeNextPart($writer, $this->paths->indexPath($folder), $areas, (array) $cursor['files'], $budget, $this->partBytes());
            $part = $writer->finish();
        } catch (Throwable $e) {
            $writer->abort();

            throw $e;
        }

        $scan = $cursor['files']['scan'] ?? [];
        $cursor['files'] = $result['state'] + ['scan' => $scan];
        $cursor['next_seq'] = $seq + 1;
        $state = $result['state'];

        if ($result['done']) {
            if ((int) $state['skipped_missing'] > 0) {
                $cursor['warnings'][] = ['code' => 'skipped_missing', 'count' => (int) $state['skipped_missing']];
            }

            $this->store->updateRun($id, ['phase' => 'finalizing', 'file_count' => (int) $state['files'], 'files_bytes' => (int) $state['bytes']]);
        }

        $this->savePart($id, $part, $seq, 'files', $cursor);

        return true;
    }

    // ------------------------------------------------------------------ Phase: Manifest

    /** @param array<string,mixed> $run */
    private function finalize(array $run): bool
    {
        $id = (int) $run['id'];
        $cursor = $this->cursor($run);
        $folder = (string) $run['folder_name'];
        $parts = $this->store->parts($id);
        $seq = (int) $cursor['next_seq'];
        $isFull = (string) $run['kind'] === 'full';

        $areas = ['uploads' => ['count' => 0, 'bytes' => 0], 'erp' => ['count' => 0, 'bytes' => 0]];
        $files = ['areas' => $areas, 'skipped_missing' => 0];

        if ($isFull && isset($cursor['files'])) {
            $files['areas'] = array_merge($areas, array_map(static fn (array $a): array => ['count' => (int) $a['count'], 'bytes' => (int) $a['bytes']], (array) $cursor['files']['areas']));
            $files['skipped_missing'] = (int) $cursor['files']['skipped_missing'];
            $files['skipped_links'] = (int) ($cursor['files']['scan']['skipped_links'] ?? 0);

            if ((int) ($cursor['files']['scan']['skipped_unsafe'] ?? 0) > 0) {
                $files['skipped_unsafe'] = (int) $cursor['files']['scan']['skipped_unsafe'];
            }
        }

        $dbState = (array) ($cursor['db'] ?? []);
        $tables = [];

        foreach ((array) ($dbState['tables'] ?? []) as $name) {
            $tables[] = ['name' => (string) $name, 'rows' => (int) ($dbState['stats'][$name] ?? 0)];
        }

        $signPublic = $this->keys->signingPublic();
        $json = ManifestCodec::build([
            'run_uuid' => (string) $run['uuid'],
            'kind' => (string) $run['kind'],
            'trigger' => (string) $run['trigger_type'],
            'created_at' => self::iso((string) ($run['started_at'] ?? $run['queued_at'])),
            'finished_at' => self::iso($this->store->nowString()),
            'app' => [
                'version' => substr($this->env->appVersion, 0, 200),
                'php' => PHP_VERSION,
                'db_driver' => $this->dumper->driver(),
                'db_server' => substr($this->dumper->serverVersion(), 0, 200),
            ],
            'schema' => ['migrations' => $this->env->migrations()],
            'db' => [
                'consistency' => (string) ($run['db_consistency'] ?? 'per_tick'),
                'tables' => $tables,
                'excluded' => array_values((array) ($dbState['excluded'] ?? [])),
            ],
            'files' => $files,
            'keys' => ['included' => false, 'app_key_id' => null, 'uploads_key_id' => null, 'previous_ids' => []],
            'recipients' => array_values(array_map(static fn (string $text): string => RecipientKey::parse($text)->fingerprint(), (array) $cursor['recipients'])),
            'parts' => array_map(static fn (array $p): array => [
                'seq' => (int) $p['seq'],
                'kind' => (string) $p['part_kind'],
                'name' => (string) $p['file_name'],
                'cipher_bytes' => (int) $p['cipher_bytes'],
                'cipher_sha256' => (string) $p['cipher_sha256'],
                'plain_bytes' => (int) $p['plain_bytes'],
            ], $parts),
            'signer' => ['alg' => ManifestCodec::SIGNER_ALG, 'public_key' => base64_encode($signPublic), 'fp' => ManifestCodec::signerFingerprint($signPublic)],
        ]);
        $pair = ManifestCodec::sign($json, $this->keys->signingSecret());

        $writer = $this->openPart($run, $cursor, BackupFormat::KIND_MANIFEST, $seq, 'manifest');

        try {
            $writer->write(json_encode($pair, JSON_THROW_ON_ERROR));
            $part = $writer->finish();
        } catch (Throwable $e) {
            $writer->abort();

            throw $e;
        }

        $cursor['next_seq'] = $seq + 1;
        $this->savePart($id, $part, $seq, 'manifest', $cursor);
        @unlink($this->paths->assertInside($this->paths->indexPath($folder)));

        $allParts = $this->store->parts($id);
        $this->store->updateRun($id, [
            'manifest_sha256' => $part->sha256,
            'parts' => count($allParts),
            'cipher_bytes' => array_sum(array_map(static fn (array $p): int => (int) $p['cipher_bytes'], $allParts)),
        ]);

        // Uploads je entferntem Ziel und Teil anlegen; lokale Ziele brauchen keinen Upload.
        $remote = 0;

        foreach ($this->store->runDestinations($id) as $rd) {
            $destination = $this->store->destination((int) $rd['destination_id']);

            if ($destination === null || $destination['dest_type'] === 'local') {
                continue;
            }

            $remote++;

            foreach ($allParts as $p) {
                $this->store->addUpload((int) $p['id'], (int) $destination['id']);
            }
        }

        $this->store->updateRun($id, ['phase' => $remote > 0 ? 'uploading' : 'completing']);

        return true;
    }

    // ------------------------------------------------------------------ Phase: Uploads

    /** @param array<string,mixed> $run */
    private function upload(array $run, Budget $budget): bool
    {
        $id = (int) $run['id'];
        $now = $this->store->now();
        $folder = (string) $run['folder_name'];
        $waiting = false;

        foreach ($this->store->runDestinations($id) as $rd) {
            if ((string) $rd['status'] !== 'pending') {
                continue;
            }

            $destination = $this->store->destination((int) $rd['destination_id']);

            if ($destination === null || $destination['dest_type'] === 'local') {
                continue;
            }

            if ($budget->exhausted()) {
                return false;
            }

            $next = Store::toDate($rd['next_attempt_at'] !== null ? (string) $rd['next_attempt_at'] : null);

            if ($next !== null && $next > $now) {
                $waiting = true;

                continue;
            }

            try {
                $driver = $this->drivers->make($destination);

                if ($rd['remote_folder_ref'] === null || $rd['remote_folder_ref'] === '') {
                    $ref = $driver->beginRun($folder);
                    $this->store->updateRunDestination((int) $rd['id'], ['remote_folder_ref' => $ref]);
                }

                if ($this->uploadParts($run, $rd, $destination, $driver, $budget)) {
                    $waiting = true;
                }

                $uploads = $this->store->uploadsOfRunDestination($id, (int) $destination['id']);

                if ($uploads !== [] && count(array_filter($uploads, static fn (array $u): bool => $u['status'] === 'done')) === count($uploads)) {
                    $this->store->updateRunDestination((int) $rd['id'], ['status' => 'done', 'completed_at' => $this->store->nowString(), 'error_code' => null]);
                }
            } catch (Throwable $e) {
                $this->destinationFailed($rd, Failure::category($e));
                $waiting = true;
            }
        }

        $open = false;

        foreach ($this->store->runDestinations($id) as $rd) {
            $destination = $this->store->destination((int) $rd['destination_id']);

            if ((string) $rd['status'] === 'pending' && $destination !== null && $destination['dest_type'] !== 'local') {
                $open = true;
            }
        }

        if (!$open) {
            $this->store->updateRun($id, ['phase' => 'completing']);

            return true;
        }

        return !$waiting && !$budget->exhausted();
    }

    /**
     * Laedt die Teile eines Ziels der Reihe nach hoch.
     *
     * @param array<string,mixed> $run
     * @param array<string,mixed> $rd
     * @param array<string,mixed> $destination
     * @return bool true, wenn der Fortschritt gerade blockiert ist (Fehler mit Wartezeit); false bei Fertigstellung oder Budgetende
     */
    private function uploadParts(array $run, array $rd, array $destination, DestinationDriver $driver, Budget $budget): bool
    {
        $id = (int) $run['id'];
        $folder = (string) $run['folder_name'];
        $now = $this->store->now();

        foreach ($this->store->uploadsOfRunDestination($id, (int) $destination['id']) as $upload) {
            if ($upload['status'] === 'done') {
                continue;
            }

            if ($budget->exhausted()) {
                return false;
            }

            $next = Store::toDate($upload['next_attempt_at'] !== null ? (string) $upload['next_attempt_at'] : null);

            if ($next !== null && $next > $now) {
                return true; // Teile gehen der Reihe nach (das Manifest zuletzt): warten
            }

            $path = $this->paths->partPath($folder, (string) $upload['file_name']);

            if ($driver instanceof PredictsPartRef && ($upload['remote_ref'] === null || $upload['remote_ref'] === '')) {
                $upload['remote_ref'] = $driver->partRef((string) $upload['file_name']);
                $this->store->updateUpload((int) $upload['id'], ['remote_ref' => $upload['remote_ref']]);
            }

            try {
                $progress = $driver->upload(['resume_state' => $upload['resume_state'] ?? null, 'remote_ref' => $upload['remote_ref'] ?? null], $path, $budget);

                if (!$progress->done) {
                    $this->store->updateUpload((int) $upload['id'], ['status' => 'uploading', 'resume_state' => $progress->resumeState, 'bytes_done' => $progress->bytesDone]);

                    return false;
                }

                $driver->verify((string) $progress->remoteRef, (int) $upload['cipher_bytes']);
                $this->store->updateUpload((int) $upload['id'], [
                    'status' => 'done', 'remote_ref' => $progress->remoteRef, 'resume_state' => null, 'bytes_done' => $progress->bytesDone,
                    'error_code' => null, 'uploaded_at' => $this->store->nowString(), 'next_attempt_at' => null,
                ]);
                $this->dropLocalPartIfUploadedEverywhere($id, $folder, (int) $upload['part_id'], (string) $upload['file_name']);
            } catch (Throwable $e) {
                $attempts = (int) $upload['attempts'] + 1;
                $this->store->updateUpload((int) $upload['id'], [
                    'status' => 'pending', 'attempts' => $attempts, 'error_code' => Failure::category($e), 'resume_state' => null,
                    'next_attempt_at' => $this->backoff($attempts),
                ]);

                if ($attempts >= max(1, $this->env->maxAttempts)) {
                    $this->giveUp($rd, Failure::category($e));
                }

                return true;
            }
        }

        return false;
    }

    /** Das Ziel gilt fuer diesen Lauf als fehlgeschlagen; bereits hochgeladene Teile werden aufgeraeumt. */
    private function giveUp(array $rd, string $code): void
    {
        $this->store->updateRunDestination((int) $rd['id'], [
            'status' => 'failed', 'error_code' => $code, 'next_attempt_at' => null, 'delete_after' => $this->store->nowString(), 'attempts' => 0,
        ]);
    }

    /** Bei reinen Remote-Zielen: Teil lokal loeschen, sobald er bei allen entfernten Zielen liegt. */
    private function dropLocalPartIfUploadedEverywhere(int $runId, string $folder, int $partId, string $fileName): void
    {
        foreach ($this->store->destinations(true) as $destination) {
            if ($destination['dest_type'] === 'local') {
                return; // ein lokales Ziel ist aktiv: Teile bleiben lokal
            }
        }

        foreach ($this->store->uploadsOfRun($runId) as $upload) {
            if ((int) $upload['part_id'] === $partId && $upload['status'] !== 'done') {
                return;
            }
        }

        $this->paths->deleteFile($this->paths->partPath($folder, $fileName));
        $this->store->markPartDeleted($partId);
    }

    /** @param array<string,mixed> $rd */
    private function destinationFailed(array $rd, string $code): void
    {
        $attempts = (int) $rd['attempts'] + 1;
        $fields = ['attempts' => $attempts, 'error_code' => $code, 'next_attempt_at' => $this->backoff($attempts)];

        if ($attempts >= max(1, $this->env->maxAttempts)) {
            $this->giveUp($rd, $code);

            return;
        }

        $this->store->updateRunDestination((int) $rd['id'], $fields);
    }

    private function backoff(int $attempts): string
    {
        $seconds = min(self::BACKOFF_MAX_SECONDS, self::BACKOFF_BASE_SECONDS * (2 ** max(0, $attempts - 1)));

        return $this->store->now()->modify('+' . $seconds . ' seconds')->format(Store::DATE_FORMAT);
    }

    // ------------------------------------------------------------------ Phase: Abschluss

    /** @param array<string,mixed> $run */
    private function complete(array $run): bool
    {
        $id = (int) $run['id'];
        $folder = (string) $run['folder_name'];
        $cursor = $this->cursor($run);
        $ok = 0;
        $failed = 0;
        $firstError = null;
        $hasLocal = false;

        foreach ($this->store->runDestinations($id) as $rd) {
            $destination = $this->store->destination((int) $rd['destination_id']);
            $isLocal = $destination !== null && $destination['dest_type'] === 'local';

            if ((string) $rd['status'] === 'failed') {
                $failed++;
                $firstError ??= (string) ($rd['error_code'] ?? 'upload_failed');

                continue;
            }

            if ($isLocal) {
                $hasLocal = true;
            }

            $this->store->updateRunDestination((int) $rd['id'], ['status' => 'done', 'completed_at' => $this->store->nowString(), 'error_code' => null]);
            $ok++;
        }

        $warnings = (array) ($cursor['warnings'] ?? []);
        // Ausgelassene Dateien machen den Lauf zu "partial"; ein nur tabellenweise konsistenter Dump ist ein Hinweis (db_not_atomic).
        $degrading = array_filter($warnings, static fn (array $w): bool => ($w['code'] ?? '') !== 'db_not_atomic');

        if ($ok === 0) {
            $status = 'failed';
        } elseif ($failed > 0 || $degrading !== []) {
            $status = 'partial';
        } else {
            $status = 'completed';
        }

        $update = [
            'status' => $status,
            'phase' => 'done',
            'active_slot' => null,
            'finished_at' => $this->store->nowString(),
            'warnings_json' => $warnings === [] ? null : $this->encode($warnings),
            'error_code' => $status === 'failed' ? ($firstError ?? 'upload_failed') : null,
        ];

        if (!$hasLocal) {
            $this->paths->deleteFolder($folder);
            $update['local_deleted_at'] = $this->store->nowString();
        }

        $this->store->updateRun($id, $update);

        if ($ok > 0) {
            $this->store->updateSettings(['last_success_at' => $this->store->nowString()]);
        }

        $this->record('backup.completed', ['run_id' => $id, 'status' => $status, 'ok' => $ok, 'failed' => $failed], null);
        (new Retention($this->store))->apply();

        return false;
    }

    // ------------------------------------------------------------------ Abbruch / Fehler

    /** @param array<string,mixed> $run */
    private function cancel(array $run): void
    {
        $id = (int) $run['id'];
        $this->abortCleanup($id, (string) $run['folder_name']);
        $this->store->updateRun($id, ['status' => 'cancelled', 'phase' => 'done', 'active_slot' => null, 'finished_at' => $this->store->nowString(), 'error_code' => 'cancelled']);
        $this->record('backup.cancelled', ['run_id' => $id], null);
    }

    /** @param array<string,mixed> $run */
    private function fail(array $run, string $code): void
    {
        $id = (int) $run['id'];

        try {
            $this->abortCleanup($id, (string) $run['folder_name']);
        } catch (Throwable) {
            // Aufraeumen ist best effort; der Fehlercode des Laufs bleibt der urspruengliche.
        }

        $this->store->updateRun($id, ['status' => 'failed', 'phase' => 'done', 'active_slot' => null, 'finished_at' => $this->store->nowString(), 'error_code' => $code]);
        $this->record('backup.failed', ['run_id' => $id, 'error_code' => $code], null);
    }

    /** Lokale Dateien eines abgebrochenen Laufs loeschen; bereits hochgeladene Teile zum Loeschen vormerken. */
    private function abortCleanup(int $runId, string $folder): void
    {
        $this->paths->deleteFolder($folder);

        foreach ($this->store->runDestinations($runId) as $rd) {
            $hasRefs = $rd['remote_folder_ref'] !== null && $rd['remote_folder_ref'] !== '';

            if ($hasRefs) {
                $this->store->updateRunDestination((int) $rd['id'], ['status' => 'deleting', 'delete_after' => $this->store->nowString(), 'attempts' => 0, 'next_attempt_at' => null]);
            } else {
                $this->store->updateRunDestination((int) $rd['id'], ['status' => 'deleted']);
            }
        }
    }

    // ------------------------------------------------------------------ Loeschungen

    /**
     * Loescht markierte Sicherungen ueber GESPEICHERTE Referenzen (nie anhand eines Listings). Fehler werden mit Backoff
     * wiederholt; nach der letzten Wiederholung bleibt der Eintrag mit status delete_failed stehen und ist im Backoffice sichtbar.
     */
    public function processDeletions(Budget $budget): int
    {
        $done = 0;
        $now = $this->store->now();
        $rows = array_merge($this->store->deletingRunDestinations(), $this->store->failedWithCleanup());

        foreach ($rows as $rd) {
            if ($budget->exhausted()) {
                break;
            }

            $next = Store::toDate($rd['next_attempt_at'] !== null ? (string) $rd['next_attempt_at'] : null);

            if ($next !== null && $next > $now) {
                continue;
            }

            $run = $this->store->run((int) $rd['run_id']);
            $destination = $this->store->destination((int) $rd['destination_id']);
            $finalStatus = (string) $rd['status'] === 'failed' ? 'failed' : 'deleted';

            try {
                if ($run !== null && $destination !== null) {
                    $this->deleteRemote($run, $rd, $destination);
                }

                $this->store->updateRunDestination((int) $rd['id'], ['status' => $finalStatus, 'delete_after' => null, 'next_attempt_at' => null, 'remote_folder_ref' => $finalStatus === 'deleted' ? null : $rd['remote_folder_ref']]);
                $done++;
                $this->record('backup.retention_deleted', ['run_id' => (int) $rd['run_id'], 'destination_id' => (int) $rd['destination_id']], null);
            } catch (Throwable $e) {
                $attempts = (int) $rd['attempts'] + 1;
                $gaveUp = $attempts >= max(1, $this->env->maxAttempts);
                $this->store->updateRunDestination((int) $rd['id'], [
                    'attempts' => $attempts,
                    'error_code' => Failure::category($e) === 'internal_error' ? 'remote_delete_failed' : Failure::category($e),
                    'next_attempt_at' => $this->backoff($attempts),
                    'status' => $gaveUp ? 'delete_failed' : (string) $rd['status'],
                ]);
            }

            if ($run !== null) {
                $this->pruneRunIfGone((int) $run['id']);
            }
        }

        return $done;
    }

    /**
     * @param array<string,mixed> $run
     * @param array<string,mixed> $rd
     * @param array<string,mixed> $destination
     */
    private function deleteRemote(array $run, array $rd, array $destination): void
    {
        $driver = $this->drivers->make($destination);

        if ($destination['dest_type'] === 'local') {
            $driver->deleteFolder((string) $run['folder_name']);

            foreach ($this->store->parts((int) $run['id']) as $part) {
                $this->store->markPartDeleted((int) $part['id']);
            }

            $this->store->updateRun((int) $run['id'], ['local_deleted_at' => $this->store->nowString()]);

            return;
        }

        foreach ($this->store->uploadsOfRunDestination((int) $run['id'], (int) $destination['id']) as $upload) {
            $ref = (string) ($upload['remote_ref'] ?? '');

            if ($ref !== '' && $upload['status'] !== 'deleted') {
                $driver->delete($ref);
                $this->store->updateUpload((int) $upload['id'], ['status' => 'deleted', 'remote_ref' => $upload['remote_ref']]);
            }
        }

        $folderRef = (string) ($rd['remote_folder_ref'] ?? '');

        if ($folderRef !== '') {
            $driver->deleteFolder($folderRef);
        }
    }

    /** Ein Lauf ohne verbleibende Ziel-Eintraege (alle geloescht) verschwindet aus dem Verlauf. */
    private function pruneRunIfGone(int $runId): void
    {
        $run = $this->store->run($runId);

        if ($run === null || !in_array((string) $run['status'], ['completed', 'partial'], true)) {
            return; // fehlgeschlagene und abgebrochene Laeufe bleiben als Nachweis im Verlauf
        }

        $rds = $this->store->runDestinations($runId);

        if ($rds === []) {
            return;
        }

        foreach ($rds as $rd) {
            if ($rd['status'] !== 'deleted') {
                return;
            }
        }

        $this->store->deleteRun($runId);
    }

    /** Aufraeumhilfe: mgdbk_-Ordner ohne Lauf-Zeile und aelter als 24 Stunden werden entfernt (nie Fremdordner). */
    private function sweepOrphans(): void
    {
        foreach ($this->paths->folders() as $folder) {
            if ($this->store->runByFolder($folder) !== null) {
                continue; // gehoert zu einem Lauf (egal wie alt): nie anfassen
            }

            $dir = $this->paths->root() . '/' . $folder;
            $age = time() - (int) @filemtime($dir);

            if ($age > 86400) {
                try {
                    $this->paths->deleteFolder($folder);
                } catch (Throwable) {
                }
            }
        }
    }

    // ------------------------------------------------------------------ Hilfen

    /**
     * @param array<string,mixed> $run
     * @param array<string,mixed> $cursor
     */
    private function openPart(array $run, array $cursor, int $kind, int $seq, string $kindName): PartWriter
    {
        $folder = (string) $run['folder_name'];
        $name = Paths::partFileName($folder, $seq, $kindName);
        $path = $this->paths->assertInside($this->paths->partPath($folder, $name));

        // Rest eines abgebrochenen Aufrufs verwerfen: der Teil wird ab dem Cursor neu geschrieben.
        if (file_exists($path) || is_link($path)) {
            $this->paths->deleteFile($path);
        }

        $this->store->pdo()->prepare('DELETE FROM backup_parts WHERE run_id = ? AND seq = ?')->execute([(int) $run['id'], $seq]);
        $recipients = array_map(static fn (string $text): RecipientKey => RecipientKey::parse($text), (array) $cursor['recipients']);

        return PartWriter::open($path, $kind, (string) hex2bin(str_replace('-', '', (string) $run['uuid'])), $seq, $recipients);
    }

    /**
     * Speichert Teilzeile und Cursor gemeinsam.
     *
     * @param array<string,mixed> $cursor
     */
    private function savePart(int $runId, \MGD\Backup\Format\PartResult $part, int $seq, string $kindName, array $cursor): void
    {
        $this->store->transaction(function () use ($runId, $part, $seq, $kindName, $cursor): void {
            $this->store->addPart($runId, [
                'seq' => $seq,
                'part_kind' => $kindName,
                'file_name' => basename($part->path),
                'plain_bytes' => $part->plainBytes,
                'cipher_bytes' => $part->cipherBytes,
                'cipher_sha256' => $part->sha256,
            ]);
            $this->store->updateRun($runId, ['cursor_json' => $this->encode($cursor)]);
        });
    }

    /**
     * @param array<string,mixed> $settings
     * @return array<string,string> Bereich => aufgeloester Ordner
     */
    public function selectedAreas(array $settings): array
    {
        $available = AreaPolicy::resolve($this->env->configuredAreas, $this->env->projectRoot, $this->env->forbiddenDirs);

        if (($settings['areas_json'] ?? null) === null) {
            return $available; // noch nie gewaehlt: alle freigegebenen Bereiche
        }

        $selected = json_decode((string) $settings['areas_json'], true);
        $selected = is_array($selected) ? array_map('strval', $selected) : [];

        return array_intersect_key($available, array_flip($selected));
    }

    private function partBytes(): int
    {
        if ($this->env->partBytesOverride !== null) {
            return max(4096, $this->env->partBytesOverride); // nur fuer Tests: kleine Teile erzwingen
        }

        $mb = (int) $this->store->settings()['part_size_mb'];

        return max(1, min(512, $mb)) * 1048576;
    }

    private function databaseBytes(): int
    {
        return $this->dumper->estimateBytes();
    }

    /** @throws BackupException insufficient_space */
    private function assertSpace(int $needBytes): void
    {
        $free = @disk_free_space($this->paths->ensureRoot());

        if ($free !== false && (int) ceil($needBytes * 1.1) > (int) $free - $this->env->minFreeBytes) {
            throw new BackupException('insufficient_space');
        }
    }

    /**
     * @param array<string,mixed> $run
     * @return array<string,mixed>
     */
    private function cursor(array $run): array
    {
        $cursor = json_decode((string) ($run['cursor_json'] ?? ''), true);

        return is_array($cursor) ? $cursor : [];
    }

    private function encode(mixed $value): string
    {
        return json_encode($value, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    private static function iso(string $utc): string
    {
        $date = Store::toDate($utc);

        return $date === null ? gmdate('Y-m-d\TH:i:s\Z') : $date->format('Y-m-d\TH:i:s\Z');
    }

    public static function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    /** @param array<string,mixed> $meta */
    private function record(string $action, array $meta, ?int $userId): void
    {
        if ($this->audit === null) {
            return;
        }

        try {
            ($this->audit)($action, $meta, $userId);
        } catch (Throwable) {
            // Ein defektes Audit darf einen Lauf nicht abbrechen.
        }
    }

    /** @return resource|null */
    private function lock()
    {
        $root = $this->paths->ensureRoot();
        $handle = @fopen($root . '/.runner.lock', 'c');

        if ($handle === false) {
            return null;
        }

        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);

            return null;
        }

        return $handle;
    }

    /** @param resource $handle */
    private function unlock($handle): void
    {
        flock($handle, LOCK_UN);
        fclose($handle);
    }
}
