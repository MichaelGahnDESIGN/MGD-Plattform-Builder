<?php

declare(strict_types=1);

namespace MGD\Starter\Core\License;

use PDO;

/**
 * Speichert geprüfte Lizenzschlüssel (Whitelabel pro Projekt, Module pro Modul-ID).
 */
final class LicenseRepository
{
    public const WHITELABEL_SUBJECT = 'project';

    public function __construct(private readonly PDO $database)
    {
    }

    public function find(string $kind, string $subject): ?string
    {
        $statement = $this->database->prepare('SELECT license_key FROM licenses WHERE kind = :kind AND subject = :subject');
        $statement->execute(['kind' => $kind, 'subject' => $subject]);
        $key = $statement->fetchColumn();

        return is_string($key) ? $key : null;
    }

    public function store(string $kind, string $subject, string $key, string $licensee, ?int $userId): void
    {
        $statement = $this->database->prepare(
            'INSERT INTO licenses (kind, subject, license_key, licensee, created_by, created_at)
             VALUES (:kind, :subject, :license_key, :licensee, :created_by, UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE license_key = VALUES(license_key), licensee = VALUES(licensee),
                 created_by = VALUES(created_by), created_at = VALUES(created_at)'
        );
        $statement->execute([
            'kind' => $kind,
            'subject' => $subject,
            'license_key' => $key,
            'licensee' => mb_substr($licensee, 0, 200),
            'created_by' => $userId,
        ]);
    }

    public function remove(string $kind, string $subject): void
    {
        $statement = $this->database->prepare('DELETE FROM licenses WHERE kind = :kind AND subject = :subject');
        $statement->execute(['kind' => $kind, 'subject' => $subject]);
    }
}
