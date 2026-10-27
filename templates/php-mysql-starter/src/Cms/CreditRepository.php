<?php

declare(strict_types=1);

namespace MGD\Starter\Cms;

use JsonException;
use PDO;

final class CreditRepository
{
    public function __construct(private readonly PDO $database)
    {
    }

    public function people(): array
    {
        return $this->database->query('SELECT * FROM credit_people ORDER BY sort_order, name')->fetchAll();
    }

    public function findPerson(int $id): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM credit_people WHERE id = :id');
        $statement->execute(['id' => $id]);

        return $statement->fetch() ?: null;
    }

    public function createPerson(array $data): int
    {
        $this->database->prepare(
            'INSERT INTO credit_people (name, role, link_url, sort_order, created_at, updated_at)
             VALUES (:name, :role, :link_url, :sort_order, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
        )->execute($data);

        return (int) $this->database->lastInsertId();
    }

    public function updatePerson(int $id, array $data): void
    {
        $this->database->prepare(
            'UPDATE credit_people SET name = :name, role = :role, link_url = :link_url, sort_order = :sort_order,
                    updated_at = UTC_TIMESTAMP() WHERE id = :id'
        )->execute([...$data, 'id' => $id]);
    }

    public function deletePerson(int $id): void
    {
        $this->database->prepare('DELETE FROM credit_people WHERE id = :id')->execute(['id' => $id]);
    }

    public function components(string $category = ''): array
    {
        if ($category !== '') {
            $statement = $this->database->prepare(
                'SELECT * FROM credit_components WHERE category = :category ORDER BY sort_order, name'
            );
            $statement->execute(['category' => $category]);
            $rows = $statement->fetchAll();
        } else {
            $rows = $this->database->query('SELECT * FROM credit_components ORDER BY category, sort_order, name')->fetchAll();
        }

        return array_map(fn (array $row): array => $this->hydrateComponent($row), $rows);
    }

    public function findComponent(int $id): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM credit_components WHERE id = :id');
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row ? $this->hydrateComponent($row) : null;
    }

    public function createComponent(array $data): int
    {
        $this->database->prepare(
            'INSERT INTO credit_components
                (name, category, logo_path, description, provider_name, provider_info, links_json, license, tags_json,
                 commercial_use, attribution_required, locally_embedded, version, notes, sort_order, created_at, updated_at)
             VALUES
                (:name, :category, :logo_path, :description, :provider_name, :provider_info, :links_json, :license, :tags_json,
                 :commercial_use, :attribution_required, :locally_embedded, :version, :notes, :sort_order, UTC_TIMESTAMP(), UTC_TIMESTAMP())'
        )->execute($this->componentParams($data));

        return (int) $this->database->lastInsertId();
    }

    public function updateComponent(int $id, array $data): void
    {
        $this->database->prepare(
            'UPDATE credit_components SET name = :name, category = :category, logo_path = :logo_path,
                    description = :description, provider_name = :provider_name, provider_info = :provider_info,
                    links_json = :links_json, license = :license, tags_json = :tags_json, commercial_use = :commercial_use,
                    attribution_required = :attribution_required, locally_embedded = :locally_embedded,
                    version = :version, notes = :notes, sort_order = :sort_order, updated_at = UTC_TIMESTAMP()
              WHERE id = :id'
        )->execute([...$this->componentParams($data), 'id' => $id]);
    }

    public function deleteComponent(int $id): void
    {
        $this->database->prepare('DELETE FROM credit_components WHERE id = :id')->execute(['id' => $id]);
    }

    public function componentCount(): int
    {
        return (int) $this->database->query('SELECT COUNT(*) FROM credit_components')->fetchColumn();
    }

    private function componentParams(array $data): array
    {
        return [
            'name' => $data['name'],
            'category' => $data['category'],
            'logo_path' => $data['logo_path'],
            'description' => $data['description'],
            'provider_name' => $data['provider_name'],
            'provider_info' => $data['provider_info'],
            'links_json' => json_encode($data['links'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'license' => $data['license'],
            'tags_json' => json_encode($data['tags'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'commercial_use' => $data['commercial_use'],
            'attribution_required' => $data['attribution_required'] ? 1 : 0,
            'locally_embedded' => $data['locally_embedded'] ? 1 : 0,
            'version' => $data['version'],
            'notes' => $data['notes'],
            'sort_order' => $data['sort_order'],
        ];
    }

    private function hydrateComponent(array $row): array
    {
        return [
            ...$row,
            'id' => (int) $row['id'],
            'links' => self::decode((string) $row['links_json']),
            'tags' => self::decode((string) $row['tags_json']),
            'attribution_required' => (bool) $row['attribution_required'],
            'locally_embedded' => (bool) $row['locally_embedded'],
        ];
    }

    private static function decode(string $json): array
    {
        try {
            $value = json_decode($json, true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            return [];
        }

        return is_array($value) ? $value : [];
    }
}
