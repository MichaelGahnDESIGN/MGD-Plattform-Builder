<?php

declare(strict_types=1);

namespace MGD\Starter\Cms;

use InvalidArgumentException;
use JsonException;

/**
 * Synchronisiert release-notes.json in die Datenbank (Upsert nach Version + Titel).
 */
final class ReleaseNoteSync
{
    public function __construct(
        private readonly ReleaseNoteRepository $repository,
        private readonly ReleaseNoteInput $input = new ReleaseNoteInput(),
    ) {
    }

    /**
     * @return array{created: int, updated: int, unchanged: int, errors: list<string>}
     */
    public function syncFile(string $file): array
    {
        $json = is_file($file) ? file_get_contents($file) : false;

        if ($json === false) {
            throw new InvalidArgumentException('release-notes.json nicht gefunden: ' . basename($file));
        }

        try {
            $data = json_decode($json, true, 16, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new InvalidArgumentException('release-notes.json ist kein gültiges JSON.');
        }

        $entries = is_array($data) ? ($data['entries'] ?? null) : null;

        if (!is_array($entries) || !array_is_list($entries)) {
            throw new InvalidArgumentException('release-notes.json braucht eine Liste "entries".');
        }

        return $this->syncEntries($entries);
    }

    /**
     * @return array{created: int, updated: int, unchanged: int, errors: list<string>}
     */
    public function syncEntries(array $entries): array
    {
        $summary = ['created' => 0, 'updated' => 0, 'unchanged' => 0, 'errors' => []];

        foreach ($entries as $index => $entry) {
            try {
                $result = $this->repository->upsert($this->input->normalize(is_array($entry) ? $entry : []));
                $summary[$result]++;
            } catch (InvalidArgumentException $exception) {
                $summary['errors'][] = 'Eintrag ' . ($index + 1) . ': ' . $exception->getMessage();
            }
        }

        return $summary;
    }
}
