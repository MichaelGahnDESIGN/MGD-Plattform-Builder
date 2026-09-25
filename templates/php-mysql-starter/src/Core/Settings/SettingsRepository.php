<?php

declare(strict_types=1);

namespace MGD\Starter\Core\Settings;

use InvalidArgumentException;
use JsonException;
use PDO;

final class SettingsRepository
{
    /** @var array<string, mixed>|null */
    private ?array $cache = null;

    public function __construct(
        private readonly PDO $database,
        private readonly SettingsRegistry $registry,
        private readonly SettingsValidator $validator = new SettingsValidator(),
    ) {
    }

    public function registry(): SettingsRegistry
    {
        return $this->registry;
    }

    public function get(string $key): mixed
    {
        $definition = $this->registry->definition($key);

        if ($definition === null) {
            throw new InvalidArgumentException('Unbekannte Einstellung: ' . $key);
        }

        $stored = $this->load();

        if (!array_key_exists($key, $stored)) {
            return $definition['default'];
        }

        try {
            return $this->validator->normalize($definition, $stored[$key]);
        } catch (InvalidArgumentException) {
            return $definition['default'];
        }
    }

    public function bool(string $key): bool
    {
        return $this->get($key) === true;
    }

    public function string(string $key): string
    {
        $value = $this->get($key);

        return is_scalar($value) ? (string) $value : '';
    }

    public function int(string $key): int
    {
        $value = $this->get($key);

        return is_int($value) ? $value : 0;
    }

    /**
     * @return list<string>
     */
    public function list(string $key): array
    {
        $value = $this->get($key);

        return is_array($value) ? array_values(array_filter($value, 'is_string')) : [];
    }

    /**
     * Validiert und speichert. Gibt den normalisierten Wert zurück.
     */
    public function save(string $key, mixed $raw, ?int $userId): mixed
    {
        $definition = $this->registry->definition($key);

        if ($definition === null) {
            throw new InvalidArgumentException('Unbekannte Einstellung: ' . $key);
        }

        $value = $this->validator->normalize($definition, $raw);
        $this->database->prepare(
            'INSERT INTO settings (setting_key, value_json, updated_by, updated_at)
             VALUES (:setting_key, :value_json, :updated_by, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE value_json = VALUES(value_json), updated_by = VALUES(updated_by), updated_at = VALUES(updated_at)'
        )->execute([
            'setting_key' => $key,
            'value_json' => json_encode($value, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            'updated_by' => $userId,
        ]);
        $this->cache = null;

        return $value;
    }

    /**
     * Entfernt gespeicherte Werte mit Präfix (z. B. "design."), damit wieder die Standards gelten.
     */
    public function resetPrefix(string $prefix): int
    {
        if (preg_match('/^[a-z_]+\.$/', $prefix) !== 1) {
            throw new InvalidArgumentException('Ungültiges Präfix.');
        }

        $statement = $this->database->prepare('DELETE FROM settings WHERE setting_key LIKE :prefix');
        $statement->execute(['prefix' => addcslashes($prefix, '%_\\') . '%']);
        $this->cache = null;

        return $statement->rowCount();
    }

    /**
     * Schreibt Standardwerte, ohne bestehende Werte zu überschreiben.
     */
    public function seedDefaults(): int
    {
        $statement = $this->database->prepare(
            'INSERT IGNORE INTO settings (setting_key, value_json, updated_by, updated_at)
             VALUES (:setting_key, :value_json, NULL, UTC_TIMESTAMP())'
        );
        $count = 0;

        foreach ($this->registry->all() as $key => $definition) {
            $statement->execute([
                'setting_key' => $key,
                'value_json' => json_encode($definition['default'], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
            ]);
            $count += $statement->rowCount();
        }

        $this->cache = null;

        return $count;
    }

    private function load(): array
    {
        if ($this->cache !== null) {
            return $this->cache;
        }

        $this->cache = [];

        foreach ($this->database->query('SELECT setting_key, value_json FROM settings')->fetchAll() as $row) {
            try {
                $this->cache[(string) $row['setting_key']] = json_decode((string) $row['value_json'], true, 8, JSON_THROW_ON_ERROR);
            } catch (JsonException) {
                continue;
            }
        }

        return $this->cache;
    }
}
