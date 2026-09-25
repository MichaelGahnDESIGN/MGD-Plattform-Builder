<?php

declare(strict_types=1);

namespace MGD\Platform\Core\Support;

/**
 * Reads the version number from version.json (single source of truth) and
 * formats it as "0.5.1 Pre-Alpha". Looks next to the reference first and then
 * in the foundation root, so the reference works standalone and in the repo.
 */
final class Version
{
    private const STATUS_LABELS = [
        'pre-alpha' => 'Pre-Alpha',
        'alpha' => 'Alpha',
        'beta' => 'Beta',
        'pre-release' => 'Pre-Release',
        'release' => 'Release',
        'stable' => 'Stable',
        'staging' => 'Staging',
        'hotfix' => 'Hotfix',
        'lts' => 'LTS',
        'deprecated' => 'Deprecated',
    ];

    private static ?string $cachedLabel = null;

    public static function label(): string
    {
        if (self::$cachedLabel !== null) {
            return self::$cachedLabel;
        }

        $candidates = [
            dirname(__DIR__, 3) . '/version.json',
            dirname(__DIR__, 5) . '/version.json',
        ];

        foreach ($candidates as $file) {
            $label = is_file($file) ? self::labelFromFile($file) : null;
            if ($label !== null) {
                return self::$cachedLabel = $label;
            }
        }

        return self::$cachedLabel = 'unknown';
    }

    public static function format(string $version, string $status): string
    {
        if (preg_match('/^\d+\.\d+\.\d+$/', $version) !== 1) {
            throw new \InvalidArgumentException('Invalid version number.');
        }

        return $version . ' ' . (self::STATUS_LABELS[$status] ?? ucfirst($status));
    }

    private static function labelFromFile(string $file): ?string
    {
        $data = json_decode((string) file_get_contents($file), true);

        if (!is_array($data) || !is_string($data['version'] ?? null) || !is_string($data['status'] ?? null)) {
            return null;
        }

        try {
            return self::format($data['version'], $data['status']);
        } catch (\InvalidArgumentException) {
            return null;
        }
    }
}
