<?php

declare(strict_types=1);

namespace MGD\Starter\Core\Auth;

use PDO;

final class LoginThrottle
{
    private const MAX_FAILURES_PER_EMAIL = 5;
    private const MAX_FAILURES_PER_IP = 20;
    private const WINDOW_MINUTES = 15;
    private const RETENTION_DAYS = 30;

    public function __construct(
        private readonly PDO $database,
        private readonly string $secret,
    ) {
    }

    public function isBlocked(string $email, string $ip): bool
    {
        $statement = $this->database->prepare(
            'SELECT
                SUM(CASE WHEN email_hash = :email_hash THEN 1 ELSE 0 END) AS email_failures,
                SUM(CASE WHEN ip_hash = :ip_hash THEN 1 ELSE 0 END) AS ip_failures
               FROM login_attempts
              WHERE success = 0
                AND attempted_at > (UTC_TIMESTAMP() - INTERVAL ' . self::WINDOW_MINUTES . ' MINUTE)
                AND (email_hash = :email_hash_filter OR ip_hash = :ip_hash_filter)'
        );
        $emailHash = $this->hash(UserRepository::normalizeEmail($email));
        $ipHash = $this->hash($ip);
        $statement->execute([
            'email_hash' => $emailHash,
            'ip_hash' => $ipHash,
            'email_hash_filter' => $emailHash,
            'ip_hash_filter' => $ipHash,
        ]);
        $row = $statement->fetch() ?: [];

        return (int) ($row['email_failures'] ?? 0) >= self::MAX_FAILURES_PER_EMAIL
            || (int) ($row['ip_failures'] ?? 0) >= self::MAX_FAILURES_PER_IP;
    }

    public function record(string $email, string $ip, bool $success): void
    {
        $this->database->prepare(
            'INSERT INTO login_attempts (email_hash, ip_hash, success, attempted_at)
             VALUES (:email_hash, :ip_hash, :success, UTC_TIMESTAMP())'
        )->execute([
            'email_hash' => $this->hash(UserRepository::normalizeEmail($email)),
            'ip_hash' => $this->hash($ip),
            'success' => $success ? 1 : 0,
        ]);

        if (random_int(1, 50) === 1) {
            $this->database->exec(
                'DELETE FROM login_attempts WHERE attempted_at < (UTC_TIMESTAMP() - INTERVAL ' . self::RETENTION_DAYS . ' DAY)'
            );
        }
    }

    private function hash(string $value): string
    {
        return hash_hmac('sha256', $value, $this->secret);
    }
}
