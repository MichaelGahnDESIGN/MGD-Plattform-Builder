<?php

declare(strict_types=1);

namespace MGD\Starter\Core\Auth;

use InvalidArgumentException;

/**
 * Passwortregeln und Erzeugung von Einmal-Passwörtern.
 */
final class PasswordPolicy
{
    public const MIN_LENGTH = 12;
    public const MAX_LENGTH = 200;
    private const GENERATED_LENGTH = 16;
    // Ohne leicht verwechselbare Zeichen (0/O, 1/l/I).
    private const ALPHABET = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';

    public static function assertValid(string $password): void
    {
        $length = mb_strlen($password);

        if ($length < self::MIN_LENGTH) {
            throw new InvalidArgumentException('Das Passwort muss mindestens ' . self::MIN_LENGTH . ' Zeichen lang sein.');
        }

        if ($length > self::MAX_LENGTH) {
            throw new InvalidArgumentException('Das Passwort darf höchstens ' . self::MAX_LENGTH . ' Zeichen lang sein.');
        }
    }

    public static function generate(): string
    {
        $max = strlen(self::ALPHABET) - 1;
        $password = '';

        for ($i = 0; $i < self::GENERATED_LENGTH; $i++) {
            $password .= self::ALPHABET[random_int(0, $max)];
        }

        return $password;
    }
}
