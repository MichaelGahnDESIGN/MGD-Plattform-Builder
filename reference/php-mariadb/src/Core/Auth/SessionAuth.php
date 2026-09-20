<?php

declare(strict_types=1);

namespace MGD\Platform\Core\Auth;

use MGD\Platform\Core\Permissions\CapabilityRepository;
use PDO;

final class SessionAuth
{
    public function __construct(
        private readonly PDO $database,
        private readonly CapabilityRepository $capabilities,
    ) {
    }

    public function attempt(string $email, string $password): bool
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
            password_verify($password, '$2y$12$usesomesillystringforsalt$');
            return false;
        }

        if (!password_verify($password, (string) $account['password_hash'])) {
            return false;
        }

        session_regenerate_id(true);
        $_SESSION['account_id'] = (int) $account['id'];
        Csrf::rotate();

        return true;
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
