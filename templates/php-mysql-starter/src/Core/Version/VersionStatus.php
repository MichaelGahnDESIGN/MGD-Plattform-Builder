<?php

declare(strict_types=1);

namespace MGD\Starter\Core\Version;

enum VersionStatus: string
{
    case PreAlpha = 'pre-alpha';
    case Alpha = 'alpha';
    case Beta = 'beta';
    case PreRelease = 'pre-release';
    case Release = 'release';
    case Stable = 'stable';
    case Staging = 'staging';
    case Hotfix = 'hotfix';
    case Lts = 'lts';
    case Deprecated = 'deprecated';

    public function label(string $locale = 'de'): string
    {
        return $locale === 'en' ? $this->labelEn() : $this->labelDe();
    }

    public function labelDe(): string
    {
        return match ($this) {
            self::PreAlpha => 'Pre-Alpha',
            self::Alpha => 'Alpha',
            self::Beta => 'Beta',
            self::PreRelease => 'Pre-Release',
            self::Release => 'Release',
            self::Stable => 'Stabil',
            self::Staging => 'Staging',
            self::Hotfix => 'Hotfix',
            self::Lts => 'LTS',
            self::Deprecated => 'Veraltet',
        };
    }

    public function labelEn(): string
    {
        return match ($this) {
            self::PreAlpha => 'Pre-Alpha',
            self::Alpha => 'Alpha',
            self::Beta => 'Beta',
            self::PreRelease => 'Pre-Release',
            self::Release => 'Release',
            self::Stable => 'Stable',
            self::Staging => 'Staging',
            self::Hotfix => 'Hotfix',
            self::Lts => 'LTS',
            self::Deprecated => 'Deprecated',
        };
    }

    public function badgeVariant(): string
    {
        return match ($this) {
            self::PreAlpha, self::Alpha, self::Staging => 'warning',
            self::Beta, self::PreRelease => 'info',
            self::Release, self::Stable, self::Lts => 'success',
            self::Hotfix, self::Deprecated => 'danger',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(string $locale = 'de'): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label($locale);
        }

        return $options;
    }
}
