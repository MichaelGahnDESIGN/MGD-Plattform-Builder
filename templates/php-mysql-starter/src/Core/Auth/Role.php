<?php

declare(strict_types=1);

namespace MGD\Starter\Core\Auth;

/**
 * Rollenhierarchie: admin > editor > moderator > user.
 */
enum Role: string
{
    case User = 'user';
    case Moderator = 'moderator';
    case Editor = 'editor';
    case Admin = 'admin';

    public function level(): int
    {
        return match ($this) {
            self::User => 0,
            self::Moderator => 10,
            self::Editor => 20,
            self::Admin => 30,
        };
    }

    public function atLeast(self $minimum): bool
    {
        return $this->level() >= $minimum->level();
    }

    public function label(): string
    {
        return match ($this) {
            self::User => 'Benutzer',
            self::Moderator => 'Moderator',
            self::Editor => 'Redaktion',
            self::Admin => 'Administration',
        };
    }

    public static function fromString(string $value): self
    {
        return self::tryFrom($value) ?? self::User;
    }
}
