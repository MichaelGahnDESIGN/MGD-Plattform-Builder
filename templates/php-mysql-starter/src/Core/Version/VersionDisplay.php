<?php

declare(strict_types=1);

namespace MGD\Starter\Core\Version;

use MGD\Starter\Core\Settings\SettingsRepository;
use MGD\Starter\Core\View\View;

/**
 * Entscheidet anhand der Einstellungen "Versionsnummern", wo die Version erscheint.
 */
final class VersionDisplay
{
    public const LOCATIONS = [
        'login' => 'Login-Seite',
        'settings' => 'Einstellungen',
        'backoffice_footer' => 'Backoffice-Footer',
        'public_footer' => 'Öffentlicher Footer',
        'public_header' => 'Öffentlicher Header',
        'landing_public' => 'Öffentliche Startseite',
        'landing_private' => 'Backoffice-Startseite',
    ];

    private const PUBLIC_ZONE = ['public_footer', 'public_header', 'landing_public'];

    public function __construct(
        private readonly Version $version,
        private readonly SettingsRepository $settings,
        private readonly string $locale = 'de',
    ) {
    }

    public function shouldShow(string $location): bool
    {
        if (!$this->settings->bool('version.display_enabled')) {
            return false;
        }

        if (!in_array($location, $this->settings->list('version.locations'), true)) {
            return false;
        }

        $audience = $this->settings->string('version.audience');
        $isPublicZone = in_array($location, self::PUBLIC_ZONE, true);

        return $audience === 'both'
            || ($audience === 'public' && $isPublicZone)
            || ($audience === 'private' && !$isPublicZone);
    }

    public function render(string $location): string
    {
        if (!$this->shouldShow($location)) {
            return '';
        }

        $label = $this->version->label($this->settings->bool('version.show_status'), $this->locale);

        return '<span class="version-label" data-version-location="' . View::e($location) . '">Version '
            . View::e($label) . '</span>';
    }
}
