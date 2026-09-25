<?php

declare(strict_types=1);

namespace MGD\Starter\Core\Module;

use InvalidArgumentException;
use MGD\Starter\Core\Auth\Role;

/**
 * Validiertes module.json eines Moduls unter modules/<id>/.
 * Schema: schema/module-package.schema.json im MGD-Plattform-Builder.
 */
final class ModuleManifest
{
    public const ID_PATTERN = '/^[a-z0-9][a-z0-9-]{1,63}$/';
    private const VERSION_PATTERN = '/^\d+\.\d+\.\d+$/';

    /**
     * @param list<array{href: string, label: string, role: Role}> $menu
     */
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly string $version,
        public readonly string $description,
        public readonly string $vendor,
        public readonly bool $paid,
        public readonly string $directory,
        public readonly string $entry,
        public readonly ?string $migrations,
        public readonly array $menu,
        public readonly string $requiresStarter,
    ) {
    }

    public static function fromDirectory(string $directory): self
    {
        $file = $directory . '/module.json';
        $data = is_file($file) ? json_decode((string) file_get_contents($file), true) : null;

        if (!is_array($data)) {
            throw new InvalidArgumentException('module.json fehlt oder ist kein gültiges JSON.');
        }

        $id = self::string($data, 'id');

        if (preg_match(self::ID_PATTERN, $id) !== 1 || $id !== basename($directory)) {
            throw new InvalidArgumentException('Modul-ID muss dem Ordnernamen entsprechen und ' . self::ID_PATTERN . ' erfüllen.');
        }

        $version = self::string($data, 'version');

        if (preg_match(self::VERSION_PATTERN, $version) !== 1) {
            throw new InvalidArgumentException('Modulversion muss MAJOR.MINOR.PATCH sein.');
        }

        $license = $data['license'] ?? 'free';

        if (!in_array($license, ['free', 'paid'], true)) {
            throw new InvalidArgumentException('license muss "free" oder "paid" sein.');
        }

        return new self(
            $id,
            self::string($data, 'name'),
            $version,
            is_string($data['description'] ?? null) ? $data['description'] : '',
            is_string($data['vendor'] ?? null) ? $data['vendor'] : '',
            $license === 'paid',
            $directory,
            self::relativeFile($directory, is_string($data['entry'] ?? null) ? $data['entry'] : 'module.php'),
            isset($data['migrations']) ? self::relativeDirectory($directory, (string) $data['migrations']) : null,
            self::menu($data['menu'] ?? []),
            is_string($data['requires']['starter'] ?? null) ? $data['requires']['starter'] : '',
        );
    }

    private static function string(array $data, string $key): string
    {
        $value = $data[$key] ?? null;

        if (!is_string($value) || trim($value) === '') {
            throw new InvalidArgumentException('Pflichtfeld fehlt: ' . $key);
        }

        return trim($value);
    }

    private static function relativeFile(string $directory, string $relative): string
    {
        $path = realpath($directory . '/' . $relative);
        $root = realpath($directory);

        if ($path === false || $root === false || !str_starts_with($path, $root . DIRECTORY_SEPARATOR) || !str_ends_with($path, '.php')) {
            throw new InvalidArgumentException('entry muss eine PHP-Datei innerhalb des Modulordners sein.');
        }

        return $path;
    }

    private static function relativeDirectory(string $directory, string $relative): string
    {
        $path = realpath($directory . '/' . $relative);
        $root = realpath($directory);

        if ($path === false || $root === false || !is_dir($path) || !str_starts_with($path, $root . DIRECTORY_SEPARATOR)) {
            throw new InvalidArgumentException('migrations muss ein Ordner innerhalb des Modulordners sein.');
        }

        return $path;
    }

    /**
     * @return list<array{href: string, label: string, role: Role}>
     */
    private static function menu(mixed $items): array
    {
        $menu = [];

        foreach (is_array($items) ? $items : [] as $item) {
            $href = is_array($item) ? ($item['href'] ?? '') : '';
            $label = is_array($item) ? ($item['label'] ?? '') : '';

            if (!is_string($href) || !is_string($label) || preg_match('#^/admin/[a-z0-9/_-]+$#', $href) !== 1 || $label === '') {
                throw new InvalidArgumentException('Menüeinträge brauchen href (/admin/...) und label.');
            }

            $menu[] = ['href' => $href, 'label' => mb_substr($label, 0, 60), 'role' => Role::tryFrom((string) ($item['role'] ?? 'admin')) ?? Role::Admin];
        }

        return $menu;
    }
}
