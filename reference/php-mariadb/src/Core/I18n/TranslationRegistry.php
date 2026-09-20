<?php

declare(strict_types=1);

namespace MGD\Platform\Core\I18n;

use InvalidArgumentException;
use MGD\Platform\Core\Audit\AuditLogger;
use MGD\Platform\Core\Auth\Actor;
use PDO;
use RuntimeException;

final class TranslationRegistry
{
    public function __construct(
        private readonly PDO $database,
        private readonly AuditLogger $audit,
    ) {
    }

    public function saveDraft(
        Actor $actor,
        string $key,
        string $locale,
        string $value,
        ?string $description = null,
    ): void {
        $this->requireCapability($actor, 'translations.manage');

        [$key, $locale, $value] = $this->validateEntry($key, $locale, $value);

        $this->database->beginTransaction();

        try {
            $keyId = $this->upsertKey($key, $description);

            $translation = $this->database->prepare(
                'INSERT INTO translations
                    (translation_key_id, locale, value_text, status, updated_by_actor_id)
                 VALUES
                    (:translation_key_id, :locale, :value_text, :status, :actor_id)
                 ON DUPLICATE KEY UPDATE
                    value_text = VALUES(value_text),
                    status = VALUES(status),
                    updated_by_actor_id = VALUES(updated_by_actor_id),
                    submitted_at = NULL,
                    reviewed_at = NULL,
                    reviewed_by_actor_id = NULL,
                    review_note = NULL,
                    updated_at = UTC_TIMESTAMP()'
            );
            $translation->execute([
                'translation_key_id' => $keyId,
                'locale' => $locale,
                'value_text' => $value,
                'status' => 'draft',
                'actor_id' => $actor->id,
            ]);

            $this->audit->record(
                $actor,
                'translations.draft.save',
                'translation',
                $key . ':' . $locale
            );

            $this->database->commit();
        } catch (\Throwable $error) {
            $this->rollBackIfNeeded();
            throw $error;
        }
    }

    public function submitForReview(Actor $actor, string $key, string $locale): void
    {
        $this->requireCapability($actor, 'translations.manage');
        [$key, $locale] = $this->validateIdentity($key, $locale);

        $this->database->beginTransaction();

        try {
            $statement = $this->database->prepare(
                "UPDATE translations t
                  JOIN translation_keys k ON k.id = t.translation_key_id
                   SET t.status = 'review',
                       t.submitted_at = UTC_TIMESTAMP(),
                       t.reviewed_at = NULL,
                       t.reviewed_by_actor_id = NULL,
                       t.review_note = NULL,
                       t.updated_by_actor_id = :actor_id,
                       t.updated_at = UTC_TIMESTAMP()
                 WHERE k.translation_key = :translation_key
                   AND t.locale = :locale
                   AND t.status IN ('draft', 'rejected')"
            );
            $statement->execute([
                'actor_id' => $actor->id,
                'translation_key' => $key,
                'locale' => $locale,
            ]);

            if ($statement->rowCount() !== 1) {
                throw new InvalidArgumentException('Only draft or rejected translations can be submitted.');
            }

            $this->audit->record(
                $actor,
                'translations.review.submit',
                'translation',
                $key . ':' . $locale
            );

            $this->database->commit();
        } catch (\Throwable $error) {
            $this->rollBackIfNeeded();
            throw $error;
        }
    }

    public function review(
        Actor $actor,
        string $key,
        string $locale,
        bool $approve,
        ?string $note = null,
    ): void {
        $this->requireCapability($actor, 'translations.review');
        [$key, $locale] = $this->validateIdentity($key, $locale);

        $status = $approve ? 'published' : 'rejected';
        $note = trim((string) $note);

        $this->database->beginTransaction();

        try {
            $statement = $this->database->prepare(
                "UPDATE translations t
                  JOIN translation_keys k ON k.id = t.translation_key_id
                   SET t.status = :status,
                       t.reviewed_at = UTC_TIMESTAMP(),
                       t.reviewed_by_actor_id = :actor_id,
                       t.review_note = :review_note,
                       t.updated_at = UTC_TIMESTAMP()
                 WHERE k.translation_key = :translation_key
                   AND t.locale = :locale
                   AND t.status = 'review'"
            );
            $statement->execute([
                'status' => $status,
                'actor_id' => $actor->id,
                'review_note' => $note !== '' ? mb_substr($note, 0, 1000) : null,
                'translation_key' => $key,
                'locale' => $locale,
            ]);

            if ($statement->rowCount() !== 1) {
                throw new InvalidArgumentException('Only translations in review can be reviewed.');
            }

            $this->audit->record(
                $actor,
                $approve ? 'translations.review.approve' : 'translations.review.reject',
                'translation',
                $key . ':' . $locale,
                ['note' => $note !== '' ? mb_substr($note, 0, 1000) : null]
            );

            $this->database->commit();
        } catch (\Throwable $error) {
            $this->rollBackIfNeeded();
            throw $error;
        }
    }

    public function importJson(Actor $actor, string $json): int
    {
        $this->requireCapability($actor, 'translations.import');

        $data = json_decode($json, true, flags: JSON_THROW_ON_ERROR);

        if (!is_array($data) || !isset($data['locale'], $data['translations']) || !is_array($data['translations'])) {
            throw new InvalidArgumentException('Import must contain locale and translations.');
        }

        $locale = trim((string) $data['locale']);
        $this->validateLocale($locale);

        $count = 0;

        foreach ($data['translations'] as $key => $entry) {
            if (is_string($entry)) {
                $value = $entry;
                $description = null;
            } elseif (is_array($entry) && isset($entry['value'])) {
                $value = (string) $entry['value'];
                $description = isset($entry['description']) ? (string) $entry['description'] : null;
            } else {
                throw new InvalidArgumentException('Invalid translation import entry for key: ' . $key);
            }

            $this->saveImportedDraft(
                $actor,
                (string) $key,
                $locale,
                $value,
                $description
            );
            $count++;
        }

        $this->audit->record(
            $actor,
            'translations.import',
            'translation_batch',
            $locale,
            ['count' => $count]
        );

        return $count;
    }

    public function exportJson(Actor $actor, ?string $locale = null): string
    {
        $this->requireCapability($actor, 'translations.export');

        $params = [];
        $where = '';

        if ($locale !== null && trim($locale) !== '') {
            $locale = trim($locale);
            $this->validateLocale($locale);
            $where = 'WHERE t.locale = :locale';
            $params['locale'] = $locale;
        }

        $statement = $this->database->prepare(
            "SELECT k.translation_key, k.description, t.locale, t.value_text, t.status
               FROM translation_keys k
               JOIN translations t ON t.translation_key_id = k.id
               {$where}
              ORDER BY t.locale, k.translation_key"
        );
        $statement->execute($params);

        $export = [
            'format' => 'mgd-translations-v1',
            'exported_at' => gmdate(DATE_ATOM),
            'locales' => [],
        ];

        foreach ($statement->fetchAll() as $row) {
            $currentLocale = (string) $row['locale'];
            $export['locales'][$currentLocale][(string) $row['translation_key']] = [
                'value' => (string) $row['value_text'],
                'description' => $row['description'] !== null ? (string) $row['description'] : null,
                'status' => (string) $row['status'],
            ];
        }

        $this->audit->record(
            $actor,
            'translations.export',
            'translation_batch',
            $locale,
            ['locale' => $locale]
        );

        return json_encode(
            $export,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR
        );
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

    public function list(int $limit = 500): array
    {
        $limit = max(1, min($limit, 1000));

        return $this->database->query(
            "SELECT k.translation_key,
                    k.description,
                    t.locale,
                    t.value_text,
                    t.status,
                    t.submitted_at,
                    t.reviewed_at,
                    t.reviewed_by_actor_id,
                    t.review_note,
                    t.updated_at
               FROM translation_keys k
               LEFT JOIN translations t ON t.translation_key_id = k.id
              ORDER BY k.translation_key, t.locale
              LIMIT {$limit}"
        )->fetchAll();
    }

    private function saveImportedDraft(
        Actor $actor,
        string $key,
        string $locale,
        string $value,
        ?string $description,
    ): void {
        [$key, $locale, $value] = $this->validateEntry($key, $locale, $value);

        $this->database->beginTransaction();

        try {
            $keyId = $this->upsertKey($key, $description);

            $statement = $this->database->prepare(
                'INSERT INTO translations
                    (translation_key_id, locale, value_text, status, updated_by_actor_id)
                 VALUES
                    (:translation_key_id, :locale, :value_text, :status, :actor_id)
                 ON DUPLICATE KEY UPDATE
                    value_text = VALUES(value_text),
                    status = VALUES(status),
                    updated_by_actor_id = VALUES(updated_by_actor_id),
                    submitted_at = NULL,
                    reviewed_at = NULL,
                    reviewed_by_actor_id = NULL,
                    review_note = NULL,
                    updated_at = UTC_TIMESTAMP()'
            );
            $statement->execute([
                'translation_key_id' => $keyId,
                'locale' => $locale,
                'value_text' => $value,
                'status' => 'draft',
                'actor_id' => $actor->id,
            ]);

            $this->database->commit();
        } catch (\Throwable $error) {
            $this->rollBackIfNeeded();
            throw $error;
        }
    }

    private function upsertKey(string $key, ?string $description): int
    {
        $statement = $this->database->prepare(
            'INSERT INTO translation_keys (translation_key, description)
             VALUES (:translation_key, :description)
             ON DUPLICATE KEY UPDATE
                description = COALESCE(VALUES(description), description),
                updated_at = UTC_TIMESTAMP()'
        );
        $statement->execute([
            'translation_key' => $key,
            'description' => $description !== null ? trim($description) : null,
        ]);

        $idStatement = $this->database->prepare(
            'SELECT id FROM translation_keys WHERE translation_key = :translation_key'
        );
        $idStatement->execute(['translation_key' => $key]);

        return (int) $idStatement->fetchColumn();
    }

    private function validateEntry(string $key, string $locale, string $value): array
    {
        [$key, $locale] = $this->validateIdentity($key, $locale);
        $value = trim($value);

        if ($value === '') {
            throw new InvalidArgumentException('Translation value must not be empty.');
        }

        return [$key, $locale, $value];
    }

    private function validateIdentity(string $key, string $locale): array
    {
        $key = trim($key);
        $locale = trim($locale);

        if (!preg_match('/^[a-z0-9][a-z0-9._-]{1,189}$/', $key)) {
            throw new InvalidArgumentException('Invalid translation key.');
        }

        $this->validateLocale($locale);

        return [$key, $locale];
    }

    private function validateLocale(string $locale): void
    {
        if (!preg_match('/^[a-z]{2,3}(?:-[A-Z]{2})?$/', $locale)) {
            throw new InvalidArgumentException('Invalid locale.');
        }
    }

    private function requireCapability(Actor $actor, string $capability): void
    {
        if (!in_array($capability, $actor->capabilities, true)) {
            throw new RuntimeException('Missing capability: ' . $capability);
        }
    }

    private function rollBackIfNeeded(): void
    {
        if ($this->database->inTransaction()) {
            $this->database->rollBack();
        }
    }
}
