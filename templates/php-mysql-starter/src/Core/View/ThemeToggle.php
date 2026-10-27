<?php

declare(strict_types=1);

namespace MGD\Starter\Core\View;

use MGD\Starter\Core\Settings\SettingsRepository;

final class ThemeToggle
{
    public function __construct(private readonly SettingsRepository $settings)
    {
    }

    public function enabledAt(string $location): bool
    {
        return $this->settings->bool('theme.show_toggle')
            && $this->settings->bool('theme.allow_user_choice')
            && in_array($location, $this->settings->list('theme.toggle_locations'), true);
    }

    public function render(string $location): string
    {
        if (!$this->enabledAt($location)) {
            return '';
        }

        $class = 'theme-toggle' . ($location === 'floating' ? ' theme-toggle--floating' : '');

        return '<button type="button" class="' . $class . '" data-theme-toggle aria-label="Farbmodus wechseln (System, Hell, Dunkel)">'
            . '<span class="theme-toggle__icon" aria-hidden="true"></span>'
            . '<span class="theme-toggle__label">Farbmodus</span></button>';
    }
}
