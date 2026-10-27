<?php

declare(strict_types=1);

namespace MGD\Starter\Core\Auth;

final class LoginResult
{
    public const OK = 'ok';
    public const FAILED = 'failed';
    public const THROTTLED = 'throttled';

    private function __construct(
        public readonly string $status,
        public readonly ?User $user = null,
    ) {
    }

    public static function ok(User $user): self
    {
        return new self(self::OK, $user);
    }

    public static function failed(): self
    {
        return new self(self::FAILED);
    }

    public static function throttled(): self
    {
        return new self(self::THROTTLED);
    }
}
