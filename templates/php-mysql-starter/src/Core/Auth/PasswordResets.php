<?php

declare(strict_types=1);

namespace MGD\Starter\Core\Auth;

use InvalidArgumentException;
use PDO;
use Throwable;

/**
 * Passwort-Reset per E-Mail-Link.
 * Token: 32 Zufallsbytes (hex), gespeichert wird nur der SHA-256-Hash. Gültig 60 Minuten, einmalig nutzbar.
 * Anfragen werden pro E-Mail und IP gedrosselt (HMAC-Hashes wie bei LoginThrottle).
 */
final class PasswordResets
{
    public const TOKEN_TTL_MINUTES = 60;
    private const MAX_REQUESTS_PER_EMAIL = 3;
    private const MAX_REQUESTS_PER_IP = 10;
    private const WINDOW_MINUTES = 60;
    private const TOKEN_PATTERN = '/^[a-f0-9]{64}$/';

    public function __construct(
        private readonly PDO $database,
        private readonly UserRepository $users,
        private readonly string $secret,
    ) {
    }

    /**
     * Legt ein Token an, falls ein aktives Konto existiert und nicht gedrosselt wird.
     * Der Aufrufer antwortet immer neutral (keine Konto-Enumeration).
     *
     * @return array{user: User, token: string}|null
     */
    public function request(string $email, string $ip): ?array
    {
        $email = UserRepository::normalizeEmail(mb_substr($email, 0, 254));

        if ($this->isThrottled($email, $ip)) {
            return null;
        }

        $this->recordRequest($email, $ip);
        $user = $this->users->findCredentials($email)['user'] ?? null;

        if ($user === null) {
            return null;
        }

        $token = bin2hex(random_bytes(32));
        $this->database->prepare('UPDATE password_resets SET used_at = UTC_TIMESTAMP() WHERE user_id = :id AND used_at IS NULL')
            ->execute(['id' => $user->id]);
        $this->database->prepare(
            'INSERT INTO password_resets (user_id, token_hash, expires_at, created_at)
             VALUES (:user_id, :token_hash, UTC_TIMESTAMP() + INTERVAL ' . self::TOKEN_TTL_MINUTES . ' MINUTE, UTC_TIMESTAMP())'
        )->execute(['user_id' => $user->id, 'token_hash' => self::hashToken($token)]);

        return ['user' => $user, 'token' => $token];
    }

    public function isValid(string $token): bool
    {
        return $this->findOpen($token, false) !== null;
    }

    /**
     * Setzt das neue Passwort und entwertet das Token. Gibt die Konto-ID zurück.
     */
    public function consume(string $token, string $password, string $confirm): int
    {
        if (!hash_equals($password, $confirm)) {
            throw new InvalidArgumentException('Die Passwörter stimmen nicht überein.');
        }

        PasswordPolicy::assertValid($password);
        $this->database->beginTransaction();

        try {
            $row = $this->findOpen($token, true) ?? throw new InvalidArgumentException('Der Link ist ungültig oder abgelaufen.');
            $this->database->prepare('UPDATE password_resets SET used_at = UTC_TIMESTAMP() WHERE user_id = :id AND used_at IS NULL')
                ->execute(['id' => (int) $row['user_id']]);
            $this->users->changePassword((int) $row['user_id'], $password);
            $this->database->commit();

            return (int) $row['user_id'];
        } catch (Throwable $exception) {
            if ($this->database->inTransaction()) {
                $this->database->rollBack();
            }

            throw $exception;
        }
    }

    public static function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }

    private function findOpen(string $token, bool $lock): ?array
    {
        if (preg_match(self::TOKEN_PATTERN, $token) !== 1) {
            return null;
        }

        $statement = $this->database->prepare(
            "SELECT r.id, r.user_id FROM password_resets r JOIN users u ON u.id = r.user_id
              WHERE r.token_hash = :hash AND r.used_at IS NULL AND r.expires_at > UTC_TIMESTAMP() AND u.status = 'active'"
            . ($lock ? ' FOR UPDATE' : '')
        );
        $statement->execute(['hash' => self::hashToken($token)]);

        return $statement->fetch() ?: null;
    }

    private function isThrottled(string $email, string $ip): bool
    {
        $statement = $this->database->prepare(
            'SELECT
                SUM(CASE WHEN email_hash = :email_hash THEN 1 ELSE 0 END) AS email_requests,
                SUM(CASE WHEN ip_hash = :ip_hash THEN 1 ELSE 0 END) AS ip_requests
               FROM password_reset_requests
              WHERE requested_at > (UTC_TIMESTAMP() - INTERVAL ' . self::WINDOW_MINUTES . ' MINUTE)
                AND (email_hash = :email_hash_filter OR ip_hash = :ip_hash_filter)'
        );
        $emailHash = $this->hmac($email);
        $ipHash = $this->hmac($ip);
        $statement->execute([
            'email_hash' => $emailHash,
            'ip_hash' => $ipHash,
            'email_hash_filter' => $emailHash,
            'ip_hash_filter' => $ipHash,
        ]);
        $row = $statement->fetch() ?: [];

        return (int) ($row['email_requests'] ?? 0) >= self::MAX_REQUESTS_PER_EMAIL
            || (int) ($row['ip_requests'] ?? 0) >= self::MAX_REQUESTS_PER_IP;
    }

    private function recordRequest(string $email, string $ip): void
    {
        $this->database->prepare(
            'INSERT INTO password_reset_requests (email_hash, ip_hash, requested_at) VALUES (:email_hash, :ip_hash, UTC_TIMESTAMP())'
        )->execute(['email_hash' => $this->hmac($email), 'ip_hash' => $this->hmac($ip)]);

        if (random_int(1, 50) === 1) {
            $this->database->exec('DELETE FROM password_reset_requests WHERE requested_at < (UTC_TIMESTAMP() - INTERVAL 30 DAY)');
            $this->database->exec('DELETE FROM password_resets WHERE expires_at < (UTC_TIMESTAMP() - INTERVAL 30 DAY)');
        }
    }

    private function hmac(string $value): string
    {
        return hash_hmac('sha256', $value, $this->secret);
    }
}
