<?php

declare(strict_types=1);

namespace MGD\Starter\Core\Auth;

final class User
{
    public function __construct(
        public readonly int $id,
        public readonly string $email,
        public readonly string $displayName,
        public readonly Role $role,
        public readonly bool $mustChangePassword = false,
    ) {
    }

    public function can(Role $minimum): bool
    {
        return $this->role->atLeast($minimum);
    }

    public function isBackofficeUser(): bool
    {
        return $this->can(Role::Moderator);
    }
}
