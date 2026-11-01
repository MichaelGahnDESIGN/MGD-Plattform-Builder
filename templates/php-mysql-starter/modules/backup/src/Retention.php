<?php

declare(strict_types=1);

namespace MGD\Backup;

/**
 * Aufbewahrung: Je Ziel und Art (db/full) bleiben die neuesten keep_count fertigen Sicherungen; aeltere als max_age_days
 * werden ebenfalls entfernt. Die juengste erfolgreiche Sicherung je Ziel und Art bleibt IMMER (auch ueber max_age_days
 * hinaus), damit nie die letzte Sicherung verschwindet. apply() MARKIERT nur (status = deleting); geloescht wird durch
 * Runner::processDeletions() ueber gespeicherte Referenzen.
 */
final class Retention
{
    public function __construct(private readonly Store $store)
    {
    }

    /**
     * Berechnet, welche Laeufe (rd_id) fuer ein Ziel zu loeschen sind.
     *
     * @return list<array{rd_id:int,run_id:int}>
     */
    public function plan(int $destinationId, int $keepCount, int $maxAgeDays): array
    {
        $now = $this->store->now();
        $keep = max(1, $keepCount);
        $perKind = [];
        $delete = [];

        foreach ($this->store->completedRunDestinations($destinationId) as $row) {
            $kind = (string) $row['kind'];
            $rank = $perKind[$kind] = ($perKind[$kind] ?? 0) + 1; // 1 = juengste
            $finished = Store::toDate((string) $row['finished_at']);
            $tooOld = $finished !== null && $maxAgeDays > 0 && $finished < $now->modify('-' . $maxAgeDays . ' days');

            if ($rank === 1) {
                continue; // die juengste je Ziel und Art bleibt immer
            }

            if ($rank > $keep || $tooOld) {
                $delete[] = ['rd_id' => (int) $row['rd_id'], 'run_id' => (int) $row['run_id']];
            }
        }

        return $delete;
    }

    /**
     * Markiert die faelligen Sicherungen aller Ziele zum Loeschen.
     *
     * @return int Zahl der markierten Sicherungen
     */
    public function apply(): int
    {
        $maxAge = (int) $this->store->settings()['max_age_days'];
        $marked = 0;

        foreach ($this->store->destinations() as $destination) {
            foreach ($this->plan((int) $destination['id'], (int) $destination['keep_count'], $maxAge) as $item) {
                $this->store->updateRunDestination($item['rd_id'], ['status' => 'deleting', 'delete_after' => $this->store->nowString(), 'attempts' => 0, 'next_attempt_at' => null]);
                $marked++;
            }
        }

        return $marked;
    }

    /**
     * Manuelles Loeschen eines Laufs: 'local' nur auf dem lokalen Ziel, 'all' auf allen Zielen.
     */
    public function markRun(int $runId, string $scope): int
    {
        $marked = 0;

        foreach ($this->store->runDestinations($runId) as $rd) {
            if (!in_array((string) $rd['status'], ['done', 'failed', 'delete_failed'], true)) {
                continue;
            }

            $destination = $this->store->destination((int) $rd['destination_id']);
            $isLocal = $destination !== null && $destination['dest_type'] === 'local';

            if ($scope === 'local' && !$isLocal) {
                continue;
            }

            $this->store->updateRunDestination((int) $rd['id'], ['status' => 'deleting', 'delete_after' => $this->store->nowString(), 'attempts' => 0, 'next_attempt_at' => null]);
            $marked++;
        }

        return $marked;
    }
}
