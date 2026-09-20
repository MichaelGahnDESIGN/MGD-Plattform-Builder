<?php

declare(strict_types=1);

namespace MGD\Platform\Core\Auth;

use MGD\Platform\Core\Permissions\CapabilityRepository;
use PDO;

final class SessionAuth
{
    private const DUMMY_HASH = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';

    public function __construct(
        private readonly PDO $database,
        private readonly CapabilityRepository $capabilities,
    ) {
    }

    public function attempt(string $email, string $password): ?Actor
    {
        $statement = $this->database->prepare(
            'SELECT id, public_id, password_hash, status
               FROM accounts
              WHERE email = :email
              LIMIT 1'
        );
        $statement->execute(['email' => mb_strtolower(trim($email))]);
        $account = $statement->fetch();

        if (!$account || $account['status'] !== 'active') {
            password_verify($password, self::DUMMY_HASH);
            return null;
        }

        if (!password_verify($password, (string) $account['password_hash'])) {
            return null;
        }

        session_regenerate_id(true);
        $_SESSION['account_id'] = (int) $account['id'];
        Csrf::rotate();

        $update = $this->database->prepare(
            'UPDATE accounts SET last_login_at = UTC_TIMESTAMP() WHERE id = :id'
        );
        $update->execute(['id' => (int) $account['id']]);

        return new Actor(
            (string) $account['public_id'],
            'account',
            $this->capabilities->forAccountId((int) $account['id'])
        );
    }

    public function currentActor(): ?Actor
    {
        $accountId = $_SESSION['account_id'] ?? null;

        if (!is_int($accountId) && !ctype_digit((string) $accountId)) {
            return null;
        }

        $statement = $this->database->prepare(
            'SELECT id, public_id, status
               FROM accounts
              WHERE id = :id
              LIMIT 1'
        );
        $statement->execute(['id' => (int) $accountId]);
        $account = $statement->fetch();

        if (!$account || $account['status'] !== 'active') {
            $this->logout();
            return null;
        }

        return new Actor(
            (string) $account['public_id'],
            'account',
            $this->capabilities->forAccountId((int) $account['id'])
        );
    }

    public function logout(): void
    {
        $_SESSION = [];

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $params['path'],
                $params['domain'],
                (bool) $params['secure'],
                (bool) $params['httponly']
            );
        }

        session_destroy();
    }
}
