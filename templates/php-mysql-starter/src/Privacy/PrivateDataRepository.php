<?php

declare(strict_types=1);

namespace MGD\Starter\Privacy;

use DateTimeImmutable;
use InvalidArgumentException;
use JsonException;
use PDO;

/**
 * Zugriff auf personenbezogene Profildaten. Nutzt immer die private Verbindung
 * (eigene Datenbank oder – falls nicht konfiguriert – separate Verbindung zur Kerndatenbank).
 * Nur laden, wenn eine Funktion diese Daten wirklich braucht (Datenminimierung).
 */
final class PrivateDataRepository
{
    private const ADDRESS_FIELDS = ['street', 'postal_code', 'city', 'country'];

    public function __construct(private readonly PDO $database)
    {
    }

    public function findProfile(int $userId): ?array
    {
        $statement = $this->database->prepare('SELECT * FROM user_profiles WHERE user_id = :user_id');
        $statement->execute(['user_id' => $userId]);
        $row = $statement->fetch();

        if (!$row) {
            return null;
        }

        try {
            $row['address'] = $row['address_json'] !== null
                ? json_decode((string) $row['address_json'], true, 4, JSON_THROW_ON_ERROR)
                : null;
        } catch (JsonException) {
            $row['address'] = null;
        }

        unset($row['address_json']);

        return $row;
    }

    public function saveProfile(int $userId, array $raw): void
    {
        $data = $this->normalize($raw);
        $this->database->prepare(
            'INSERT INTO user_profiles
                (user_id, display_name, real_name, email_private, address_json, birthdate,
                 consent_newsletter, consent_profile_public, consent_updated_at, created_at, updated_at)
             VALUES
                (:user_id, :display_name, :real_name, :email_private, :address_json, :birthdate,
                 :consent_newsletter, :consent_profile_public, UTC_TIMESTAMP(), UTC_TIMESTAMP(), UTC_TIMESTAMP())
             ON DUPLICATE KEY UPDATE
                display_name = VALUES(display_name), real_name = VALUES(real_name), email_private = VALUES(email_private),
                address_json = VALUES(address_json), birthdate = VALUES(birthdate),
                consent_newsletter = VALUES(consent_newsletter), consent_profile_public = VALUES(consent_profile_public),
                consent_updated_at = UTC_TIMESTAMP(), updated_at = UTC_TIMESTAMP()'
        )->execute(['user_id' => $userId, ...$data]);
    }

    public function deleteProfile(int $userId): void
    {
        $this->database->prepare('DELETE FROM user_profiles WHERE user_id = :user_id')->execute(['user_id' => $userId]);
    }

    private function normalize(array $raw): array
    {
        $email = trim((string) ($raw['email_private'] ?? ''));

        if ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new InvalidArgumentException('Ungültige private E-Mail-Adresse.');
        }

        $birthdate = trim((string) ($raw['birthdate'] ?? ''));

        if ($birthdate !== '') {
            $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $birthdate);

            if ($parsed === false || $parsed->format('Y-m-d') !== $birthdate) {
                throw new InvalidArgumentException('Geburtsdatum muss im Format JJJJ-MM-TT sein.');
            }
        }

        $address = is_array($raw['address'] ?? null)
            ? array_map(static fn (mixed $v): string => mb_substr(trim((string) $v), 0, 200), array_intersect_key($raw['address'], array_flip(self::ADDRESS_FIELDS)))
            : null;

        return [
            'display_name' => mb_substr(trim((string) ($raw['display_name'] ?? '')), 0, 120),
            'real_name' => ($real = mb_substr(trim((string) ($raw['real_name'] ?? '')), 0, 200)) !== '' ? $real : null,
            'email_private' => $email !== '' ? mb_strtolower($email) : null,
            'address_json' => $address !== null ? json_encode($address, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR) : null,
            'birthdate' => $birthdate !== '' ? $birthdate : null,
            'consent_newsletter' => !empty($raw['consent_newsletter']) ? 1 : 0,
            'consent_profile_public' => !empty($raw['consent_profile_public']) ? 1 : 0,
        ];
    }
}
