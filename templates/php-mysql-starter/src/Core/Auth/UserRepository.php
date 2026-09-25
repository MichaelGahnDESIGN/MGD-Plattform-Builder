<?php

declare(strict_types=1);

namespace MGD\Starter\Core\Auth;

use InvalidArgumentException;
use PDO;

final class UserRepository
{
    public const MIN_PASSWORD_LENGTH = 12;

    public function __construct(private readonly PDO $database)
    {
    }

    public function findActive(int $id): ?User
    {
        $statement = $this->database->prepare(
            "SELECT id, email, display_name, role FROM users WHERE id = :id AND status = 'active' LIMIT 1"
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row ? self::hydrate($row) : null;
    }

    /**
     * @return array{user: User, password_hash: string}|null
     */
    public function findCredentials(string $email): ?array
    {
        $statement = $this->database->prepare(
            "SELECT id, email, display_name, role, password_hash
               FROM users WHERE email = :email AND status = 'active' LIMIT 1"
        );
        $statement->execute(['email' => self::normalizeEmail($email)]);
        $row = $statement->fetch();

        return $row ? ['user' => self::hydrate($row), 'password_hash' => (string) $row['password_hash']] : null;
    }

    public function emailExists(string $email): bool
    {
        $statement = $this->database->prepare('SELECT COUNT(*) FROM users WHERE email = :email');
        $statement->execute(['email' => self::normalizeEmail($email)]);

        return (int) $statement->fetchColumn() > 0;
    }

    public function create(string $email, string $displayName, string $password, Role $role): int
    {
        $email = self::normalizeEmail($email);
        $displayName = trim($displayName);

        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Ungültige E-Mail-Adresse.');
        }

        if ($displayName === '' || mb_strlen($displayName) > 120) {
            throw new InvalidArgumentException('Anzeigename fehlt oder ist zu lang.');
        }

        if (mb_strlen($password) < self::MIN_PASSWORD_LENGTH) {
            throw new InvalidArgumentException('Das Passwort muss mindestens ' . self::MIN_PASSWORD_LENGTH . ' Zeichen lang sein.');
        }

        if ($this->emailExists($email)) {
            throw new InvalidArgumentException('Diese E-Mail-Adresse ist bereits registriert.');
        }

        $statement = $this->database->prepare(
            "INSERT INTO users (email, password_hash, display_name, role, status, created_at, updated_at)
             VALUES (:email, :password_hash, :display_name, :role, 'active', UTC_TIMESTAMP(), UTC_TIMESTAMP())"
        );
        $statement->execute([
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'display_name' => $displayName,
            'role' => $role->value,
        ]);

        return (int) $this->database->lastInsertId();
    }

    public function touchLogin(int $id): void
    {
        $this->database->prepare('UPDATE users SET last_login_at = UTC_TIMESTAMP() WHERE id = :id')
            ->execute(['id' => $id]);
    }

    public function rehashIfNeeded(int $id, string $password, string $hash): void
    {
        if (!password_needs_rehash($hash, PASSWORD_DEFAULT)) {
            return;
        }

        $this->database->prepare('UPDATE users SET password_hash = :hash, updated_at = UTC_TIMESTAMP() WHERE id = :id')
            ->execute(['hash' => password_hash($password, PASSWORD_DEFAULT), 'id' => $id]);
    }

    public static function normalizeEmail(string $email): string
    {
        return mb_strtolower(trim($email));
    }

    private static function hydrate(array $row): User
    {
        return new User(
            (int) $row['id'],
            (string) $row['email'],
            (string) $row['display_name'],
            Role::fromString((string) $row['role'])
        );
    }
}
