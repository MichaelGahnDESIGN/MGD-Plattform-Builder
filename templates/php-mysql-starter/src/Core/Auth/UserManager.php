<?php

declare(strict_types=1);

namespace MGD\Starter\Core\Auth;

use InvalidArgumentException;

/**
 * Geschäftsregeln der Benutzerverwaltung:
 * - niemand darf sich selbst herabstufen, deaktivieren oder löschen,
 * - der letzte aktive Admin darf weder herabgestuft noch deaktiviert noch gelöscht werden.
 */
final class UserManager
{
    public function __construct(private readonly UserRepository $users)
    {
    }

    /**
     * Legt ein Konto mit Einmal-Passwort an. Ohne Vorgabe wird eines erzeugt.
     *
     * @return array{id: int, password: string, generated: bool}
     */
    public function create(string $email, string $displayName, Role $role, string $initialPassword = ''): array
    {
        $generated = $initialPassword === '';
        $password = $generated ? PasswordPolicy::generate() : $initialPassword;
        $id = $this->users->create($email, $displayName, $password, $role, true);

        return ['id' => $id, 'password' => $password, 'generated' => $generated];
    }

    /**
     * Ändert Name, E-Mail, Rolle und Status. Gibt die geänderten Felder zurück.
     *
     * @return list<string>
     */
    public function update(User $actor, int $targetId, string $displayName, string $email, Role $role, string $status): array
    {
        $target = $this->target($targetId);
        $this->assertAllowedChange($actor, $target, $role, $status);
        $changed = [];

        if ($target['display_name'] !== trim($displayName) || $target['email'] !== UserRepository::normalizeEmail($email)) {
            $this->users->updateProfile($targetId, $displayName, $email);
            $changed[] = 'profile';
        }

        if ($target['role'] !== $role->value) {
            $this->users->setRole($targetId, $role);
            $changed[] = 'role';
        }

        if ($target['status'] !== $status) {
            $this->users->setStatus($targetId, $status);
            $changed[] = 'status';
        }

        return $changed;
    }

    /**
     * Admin-Reset: neues Einmal-Passwort, das beim nächsten Login geändert werden muss.
     */
    public function resetPassword(int $targetId): string
    {
        $this->target($targetId);
        $password = PasswordPolicy::generate();
        $this->users->changePassword($targetId, $password, true);

        return $password;
    }

    public function delete(User $actor, int $targetId): array
    {
        $target = $this->target($targetId);

        if ($actor->id === $targetId) {
            throw new InvalidArgumentException('Das eigene Konto kann nicht gelöscht werden.');
        }

        if ($this->isLastActiveAdmin($target)) {
            throw new InvalidArgumentException('Der letzte aktive Admin kann nicht gelöscht werden.');
        }

        $this->users->delete($targetId);

        return $target;
    }

    /**
     * Eigenes Passwort ändern (Mein Konto, Pflichtwechsel). Das aktuelle Passwort ist immer nötig.
     */
    public function changeOwnPassword(User $user, string $current, string $new, string $confirm): void
    {
        $hash = $this->users->passwordHash($user->id);

        if ($hash === null || !password_verify($current, $hash)) {
            throw new InvalidArgumentException('Das aktuelle Passwort ist falsch.');
        }

        if (!hash_equals($new, $confirm)) {
            throw new InvalidArgumentException('Die neuen Passwörter stimmen nicht überein.');
        }

        if (password_verify($new, $hash)) {
            throw new InvalidArgumentException('Das neue Passwort muss sich vom bisherigen unterscheiden.');
        }

        $this->users->changePassword($user->id, $new);
    }

    private function assertAllowedChange(User $actor, array $target, Role $role, string $status): void
    {
        $demotes = $target['role'] === Role::Admin->value && $role !== Role::Admin;
        $disables = $target['status'] === 'active' && $status !== 'active';

        if ($actor->id === (int) $target['id'] && ($role !== $actor->role || $disables)) {
            throw new InvalidArgumentException('Die eigene Rolle und den eigenen Status kann man nicht ändern.');
        }

        if (($demotes || $disables) && $this->isLastActiveAdmin($target)) {
            throw new InvalidArgumentException('Der letzte aktive Admin kann nicht herabgestuft oder deaktiviert werden.');
        }
    }

    private function isLastActiveAdmin(array $target): bool
    {
        return $target['role'] === Role::Admin->value
            && $target['status'] === 'active'
            && $this->users->countActiveAdmins() <= 1;
    }

    private function target(int $id): array
    {
        return $this->users->find($id) ?? throw new InvalidArgumentException('Konto nicht gefunden.');
    }
}
