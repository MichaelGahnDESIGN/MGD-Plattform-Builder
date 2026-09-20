<?php

declare(strict_types=1);

namespace MGD\Platform\Core\I18n;

use MGD\Platform\Core\Audit\AuditLogger;
use MGD\Platform\Core\Auth\Actor;
use PDO;
use InvalidArgumentException;

final class TranslationRegistry
{
    public function __construct(
        private readonly PDO $database,
        private readonly AuditLogger $audit,
    ) {
    }

    public function save(
        Actor $actor,
        string $key,
        string $locale,
        string $value,
        string $status = 'draft',
        ?string $description = null,
    ): void {
        $key = trim($key);
        $locale = trim($locale);
        $value = trim($value);

        if (!preg_match('/^[a-z0-9][a-z0-9._-]{1,189}$/', $key)) {
            throw new InvalidArgumentException('Invalid translation key.');
        }

        if (!preg_match('/^[a-z]{2,3}(?:-[A-Z]{2})?$/', $locale)) {
            throw new InvalidArgumentException('Invalid locale.');
        }

        if (!in_array($status, ['draft', 'published'], true)) {
            throw new InvalidArgumentException('Invalid translation status.');
        }

        if ($value === '') {
            throw new InvalidArgumentException('Translation value must not be empty.');
        }

        $this->database->beginTransaction();

        try {
            $keyStatement = $this->database->prepare(
                'INSERT INTO translation_keys (translation_key, description)
                 VALUES (:translation_key, :description)
                 ON DUPLICATE KEY UPDATE
                    description = COALESCE(VALUES(description), description),
                    updated_at = UTC_TIMESTAMP()'
            );
            $keyStatement->execute([
                'translation_key' => $key,
                'description' => $description,
            ]);

            $idStatement = $this->database->prepare(
                'SELECT id FROM translation_keys WHERE translation_key = :translation_key'
            );
            $idStatement->execute(['translation_key' => $key]);
            $keyId = (int) $idStatement->fetchColumn();

            $translation = $this->database->prepare(
                'INSERT INTO translations
                    (translation_key_id, locale, value_text, status, updated_by_actor_id)
                 VALUES
                    (:translation_key_id, :locale, :value_text, :status, :actor_id)
                 ON DUPLICATE KEY UPDATE
                    value_text = VALUES(value_text),
                    status = VALUES(status),
                    updated_by_actor_id = VALUES(updated_by_actor_id),
                    updated_at = UTC_TIMESTAMP()'
            );
            $translation->execute([
                'translation_key_id' => $keyId,
                'locale' => $locale,
                'value_text' => $value,
                'status' => $status,
                'actor_id' => $actor->id,
            ]);

            $this->audit->record(
                $actor,
                'translations.save',
                'translation',
                $key . ':' . $locale,
                ['status' => $status]
            );

            $this->database->commit();
        } catch (\Throwable $error) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }
            throw $error;
        }
    }

    public function published(string $key, string $locale, ?string $fallbackLocale = null): ?string
    {
        $statement = $this->database->prepare(
            "SELECT t.value_text
               FROM translations t
               JOIN translation_keys k ON k.id = t.translation_key_id
              WHERE k.translation_key = :translation_key
                AND t.locale = :locale
                AND t.status = 'published'
              LIMIT 1"
        );
        $statement->execute([
            'translation_key' => $key,
            'locale' => $locale,
        ]);

        $value = $statement->fetchColumn();

        if ($value !== false) {
            return (string) $value;
        }

        if ($fallbackLocale && $fallbackLocale !== $locale) {
            return $this->published($key, $fallbackLocale);
        }

        return null;
    }

    public function list(int $limit = 200): array
    {
        $limit = max(1, min($limit, 500));

        return $this->database->query(
            "SELECT k.translation_key, k.description, t.locale, t.value_text, t.status, t.updated_at
               FROM translation_keys k
               LEFT JOIN translations t ON t.translation_key_id = k.id
              ORDER BY k.translation_key, t.locale
              LIMIT {$limit}"
        )->fetchAll();
    }
}
