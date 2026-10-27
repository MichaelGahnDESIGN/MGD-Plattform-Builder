<?php

declare(strict_types=1);

namespace MGD\Starter\Core\Auth;

final class SessionAuth
{
    private const SESSION_KEY = 'user_id';
    // Hash eines zufälligen Wertes: gleicht Laufzeiten bei unbekannten Konten an.
    private const DUMMY_HASH = '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2uheWG/igi.';

    private ?User $current = null;
    private bool $resolved = false;

    public function __construct(
        private readonly UserRepository $users,
        private readonly LoginThrottle $throttle,
    ) {
    }

    public function attempt(string $email, string $password, string $ip): LoginResult
    {
        if ($this->throttle->isBlocked($email, $ip)) {
            password_verify($password, self::DUMMY_HASH);

            return LoginResult::throttled();
        }

        $credentials = $this->users->findCredentials($email);
        $hash = $credentials['password_hash'] ?? self::DUMMY_HASH;
        $valid = password_verify($password, $hash) && $credentials !== null;
        $this->throttle->record($email, $ip, $valid);

        if (!$valid) {
            return LoginResult::failed();
        }

        $user = $credentials['user'];
        session_regenerate_id(true);
        $_SESSION[self::SESSION_KEY] = $user->id;
        Csrf::rotate();
        $this->users->touchLogin($user->id);
        $this->users->rehashIfNeeded($user->id, $password, $hash);
        $this->current = $user;
        $this->resolved = true;

        return LoginResult::ok($user);
    }

    public function currentUser(): ?User
    {
        if ($this->resolved) {
            return $this->current;
        }

        $this->resolved = true;
        $id = $_SESSION[self::SESSION_KEY] ?? null;

        if (!is_int($id)) {
            return null;
        }

        $this->current = $this->users->findActive($id);

        if ($this->current === null) {
            unset($_SESSION[self::SESSION_KEY]);
        }

        return $this->current;
    }

    public function logout(): void
    {
        $_SESSION = [];
        $this->current = null;

        if (ini_get('session.use_cookies')) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'],
                'domain' => $params['domain'],
                'secure' => (bool) $params['secure'],
                'httponly' => true,
                'samesite' => 'Strict',
            ]);
        }

        session_destroy();
    }
}
