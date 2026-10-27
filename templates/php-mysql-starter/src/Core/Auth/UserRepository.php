<?php

declare(strict_types=1);

namespace MGD\Starter\Core\Auth;

use InvalidArgumentException;
use PDO;

/**
 * Datenzugriff für Konten. Geschäftsregeln (letzter Admin, eigenes Konto) liegen in UserManager.
 */
final class UserRepository
{
    public const MIN_PASSWORD_LENGTH = PasswordPolicy::MIN_LENGTH;
    public const STATUSES = ['active' => 'Aktiv', 'disabled' => 'Deaktiviert'];
    public const MAX_PER_PAGE = 100;
    private const USER_COLUMNS = 'id, email, display_name, role, must_change_password';

    public function __construct(private readonly PDO $database)
    {
    }

    public function findActive(int $id): ?User
    {
        $statement = $this->database->prepare(
            'SELECT ' . self::USER_COLUMNS . " FROM users WHERE id = :id AND status = 'active' LIMIT 1"
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch();

        return $row ? self::hydrate($row) : null;
    }

    /**
     * Vollständige Zeile (ohne Passwort-Hash) für die Verwaltung.
     */
    public function find(int $id): ?array
    {
        $statement = $this->database->prepare(
            'SELECT id, email, display_name, role, status, must_change_password, last_login_at,
                    password_changed_at, created_at, updated_at
               FROM users WHERE id = :id LIMIT 1'
        );
        $statement->execute(['id' => $id]);

        return $statement->fetch() ?: null;
    }

    /**
     * @return array{rows: list<array>, total: int, page: int, per_page: int}
     */
    public function list(string $search = '', string $role = '', string $status = '', int $page = 1, int $perPage = 25): array
    {
        $perPage = max(1, min(self::MAX_PER_PAGE, $perPage));
        [$where, $params] = $this->filters($search, $role, $status);
        $count = $this->database->prepare('SELECT COUNT(*) FROM users' . $where);
        $count->execute($params);
        $total = (int) $count->fetchColumn();
        $page = max(1, min($page, (int) max(1, ceil($total / $perPage))));
        $statement = $this->database->prepare(
            'SELECT id, email, display_name, role, status, must_change_password, last_login_at, created_at
               FROM users' . $where . ' ORDER BY display_name, id LIMIT ' . $perPage . ' OFFSET ' . (($page - 1) * $perPage)
        );
        $statement->execute($params);

        return ['rows' => $statement->fetchAll(), 'total' => $total, 'page' => $page, 'per_page' => $perPage];
    }

    /**
     * @return array{user: User, password_hash: string}|null
     */
    public function findCredentials(string $email): ?array
    {
        $statement = $this->database->prepare(
            'SELECT ' . self::USER_COLUMNS . ", password_hash
               FROM users WHERE email = :email AND status = 'active' LIMIT 1"
        );
        $statement->execute(['email' => self::normalizeEmail($email)]);
        $row = $statement->fetch();

        return $row ? ['user' => self::hydrate($row), 'password_hash' => (string) $row['password_hash']] : null;
    }

    public function passwordHash(int $id): ?string
    {
        $statement = $this->database->prepare('SELECT password_hash FROM users WHERE id = :id LIMIT 1');
        $statement->execute(['id' => $id]);
        $hash = $statement->fetchColumn();

        return is_string($hash) ? $hash : null;
    }

    public function emailExists(string $email, int $exceptId = 0): bool
    {
        $statement = $this->database->prepare('SELECT COUNT(*) FROM users WHERE email = :email AND id <> :id');
        $statement->execute(['email' => self::normalizeEmail($email), 'id' => $exceptId]);

        return (int) $statement->fetchColumn() > 0;
    }

    public function create(string $email, string $displayName, string $password, Role $role, bool $mustChangePassword = false): int
    {
        $email = self::validEmail($email);
        $displayName = self::validDisplayName($displayName);
        PasswordPolicy::assertValid($password);

        if ($this->emailExists($email)) {
            throw new InvalidArgumentException('Diese E-Mail-Adresse ist bereits registriert.');
        }

        $statement = $this->database->prepare(
            "INSERT INTO users (email, password_hash, display_name, role, status, must_change_password,
                                password_changed_at, created_at, updated_at)
             VALUES (:email, :password_hash, :display_name, :role, 'active', :must_change,
                     UTC_TIMESTAMP(), UTC_TIMESTAMP(), UTC_TIMESTAMP())"
        );
        $statement->execute([
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'display_name' => $displayName,
            'role' => $role->value,
            'must_change' => $mustChangePassword ? 1 : 0,
        ]);

        return (int) $this->database->lastInsertId();
    }

    public function updateProfile(int $id, string $displayName, ?string $email = null): void
    {
        $params = ['display_name' => self::validDisplayName($displayName), 'id' => $id];
        $emailSql = '';

        if ($email !== null) {
            $params['email'] = self::validEmail($email);
            $emailSql = ', email = :email';

            if ($this->emailExists($params['email'], $id)) {
                throw new InvalidArgumentException('Diese E-Mail-Adresse ist bereits registriert.');
            }
        }

        $this->database->prepare(
            'UPDATE users SET display_name = :display_name' . $emailSql . ', updated_at = UTC_TIMESTAMP() WHERE id = :id'
        )->execute($params);
    }

    public function setRole(int $id, Role $role): void
    {
        $this->database->prepare('UPDATE users SET role = :role, updated_at = UTC_TIMESTAMP() WHERE id = :id')
            ->execute(['role' => $role->value, 'id' => $id]);
    }

    public function setStatus(int $id, string $status): void
    {
        if (!array_key_exists($status, self::STATUSES)) {
            throw new InvalidArgumentException('Ungültiger Status.');
        }

        $this->database->prepare('UPDATE users SET status = :status, updated_at = UTC_TIMESTAMP() WHERE id = :id')
            ->execute(['status' => $status, 'id' => $id]);
    }

    /**
     * Setzt ein neues Passwort. $mustChange = true markiert es als Einmal-Passwort.
     */
    public function changePassword(int $id, string $password, bool $mustChange = false): void
    {
        PasswordPolicy::assertValid($password);
        $this->database->prepare(
            'UPDATE users SET password_hash = :hash, must_change_password = :must_change,
                    password_changed_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP() WHERE id = :id'
        )->execute(['hash' => password_hash($password, PASSWORD_DEFAULT), 'must_change' => $mustChange ? 1 : 0, 'id' => $id]);
    }

    public function delete(int $id): void
    {
        $this->database->prepare('DELETE FROM users WHERE id = :id')->execute(['id' => $id]);
    }

    public function countActiveAdmins(): int
    {
        return (int) $this->database->query("SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active'")->fetchColumn();
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

    private static function validEmail(string $email): string
    {
        $email = self::normalizeEmail($email);

        if (strlen($email) > 254 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Ungültige E-Mail-Adresse.');
        }

        return $email;
    }

    private static function validDisplayName(string $displayName): string
    {
        $displayName = trim((string) preg_replace('/[\x00-\x1F\x7F]/u', '', $displayName));

        if ($displayName === '' || mb_strlen($displayName) > 120) {
            throw new InvalidArgumentException('Anzeigename fehlt oder ist zu lang.');
        }

        return $displayName;
    }

    /**
     * @return array{0: string, 1: array<string, string>}
     */
    private function filters(string $search, string $role, string $status): array
    {
        $where = [];
        $params = [];

        if ($search !== '') {
            $where[] = '(email LIKE :q_email OR display_name LIKE :q_name)';
            $like = '%' . addcslashes($search, '%_\\') . '%';
            $params['q_email'] = $like;
            $params['q_name'] = $like;
        }

        if (Role::tryFrom($role) !== null) {
            $where[] = 'role = :role';
            $params['role'] = $role;
        }

        if (array_key_exists($status, self::STATUSES)) {
            $where[] = 'status = :status';
            $params['status'] = $status;
        }

        return [$where === [] ? '' : ' WHERE ' . implode(' AND ', $where), $params];
    }

    private static function hydrate(array $row): User
    {
        return new User(
            (int) $row['id'],
            (string) $row['email'],
            (string) $row['display_name'],
            Role::fromString((string) $row['role']),
            (bool) ($row['must_change_password'] ?? false)
        );
    }
}
