<?php

declare(strict_types=1);

namespace MGD\Starter\Cms;

use InvalidArgumentException;
use JsonException;
use PDO;

final class ReleaseNoteRepository
{
    public function __construct(private readonly PDO $database)
    {
    }

    /**
     * Neueste Version zuerst, innerhalb einer Version nach Datum absteigend.
     * Filterung nach Zielgruppe in PHP (JSON-Funktionen sind auf Shared Hosting nicht überall verfügbar).
     */
    public function all(string $audience = ''): array
    {
        $rows = array_map(
            fn (array $row): array => $this->hydrate($row),
            $this->database->query('SELECT * FROM release_notes')->fetchAll()
        );

        if ($audience !== '') {
            $rows = array_values(array_filter($rows, static fn (array $row): bool => in_array($audience, $row['audience'], true)));
        }

        usort($rows, static function (array $a, array $b): int {
            return version_compare($b['version'], $a['version'])
                ?: strcmp($b['date'], $a['date'])
                ?: $b['id'] <=> $a['id'];
        });

        return $rows;
    }

    /**
     * @return array<string, list<array>> Version => Einträge
     */
    public function grouped(string $audience = ''): array
    {
        $grouped = [];

        foreach ($this->all($audience) as $row) {
            $grouped[$row['version']][] = $row;
        }

        return $grouped;
    }

    public function find(int $id): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM release_notes WHERE id = :id');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    public function create(array $data): int
    {
        if ($this->findByVersionAndTitle($data['version'], $data['title']) !== null) {
            throw new InvalidArgumentException('Für diese Version existiert bereits ein Eintrag mit diesem Titel.');
        }

        $this->database->prepare(
            'INSERT INTO release_notes (version, status, released_on, note_type, title, items_json, audience_json, created_at, updated_at)
             VALUES (:version, :status, :released_on, :note_type, :title, :items_json, :audience_json, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
        )->execute($this->params($data));

        return (int) $this->database->lastInsertId();
    }

    public function update(int $id, array $data): void
    {
        $other = $this->findByVersionAndTitle($data['version'], $data['title']);

        if ($other !== null && $other['id'] !== $id) {
            throw new InvalidArgumentException('Für diese Version existiert bereits ein Eintrag mit diesem Titel.');
        }

        $this->database->prepare(
            'UPDATE release_notes SET version = :version, status = :status, released_on = :released_on, note_type = :note_type,
                    title = :title, items_json = :items_json, audience_json = :audience_json, updated_at = UTC_TIMESTAMP()
              WHERE id = :id'
        )->execute([...$this->params($data), 'id' => $id]);
    }

    public function delete(int $id): void
    {
        $this->database->prepare('DELETE FROM release_notes WHERE id = :id')->execute(['id' => $id]);
    }

    /**
     * Upsert anhand von Version + Titel.
     *
     * @return 'created'|'updated'|'unchanged'
     */
    public function upsert(array $data): string
    {
        $existing = $this->findByVersionAndTitle($data['version'], $data['title']);

        if ($existing === null) {
            $this->create($data);

            return 'created';
        }

        $current = [
            'version' => $existing['version'], 'status' => $existing['status'], 'date' => $existing['date'],
            'type' => $existing['type'], 'title' => $existing['title'], 'items' => $existing['items'],
            'audience' => $existing['audience'],
        ];

        if ($current == $data) {
            return 'unchanged';
        }

        $this->update($existing['id'], $data);

        return 'updated';
    }

    private function findByVersionAndTitle(string $version, string $title): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM release_notes WHERE version = :version AND title = :title');
        $statement->execute(['version' => $version, 'title' => $title]);
        $row = $statement->fetch();

        return $row ? $this->hydrate($row) : null;
    }

    private function params(array $data): array
    {
        return [
            'version' => $data['version'],
            'status' => $data['status'],
            'released_on' => $data['date'],
            'note_type' => $data['type'],
            'title' => $data['title'],
            'items_json' => json_encode($data['items'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'audience_json' => json_encode($data['audience'], JSON_THROW_ON_ERROR),
        ];
    }

    private function hydrate(array $row): array
    {
        return [
            'id' => (int) $row['id'],
            'version' => (string) $row['version'],
            'status' => (string) $row['status'],
            'date' => (string) $row['released_on'],
            'type' => (string) $row['note_type'],
            'title' => (string) $row['title'],
            'items' => self::decodeList((string) $row['items_json']),
            'audience' => self::decodeList((string) $row['audience_json']),
        ];
    }

    /**
     * @return list<string>
     */
    private static function decodeList(string $json): array
    {
        try {
            $value = json_decode($json, true, 4, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        return is_array($value) ? array_values(array_filter($value, 'is_string')) : [];
    }
}
