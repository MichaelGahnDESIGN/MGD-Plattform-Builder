<?php

declare(strict_types=1);

namespace MGD\Starter\Site;

use MGD\Starter\Cms\CreditInput;
use MGD\Starter\Core\Settings\SettingsValidator;
use MGD\Starter\Core\View\Ui;
use MGD\Starter\Core\View\View;

final class CreditCard
{
    public function render(array $component, bool $showLogo): string
    {
        $logo = $showLogo && $component['logo_path'] !== '' && SettingsValidator::isLocalPath((string) $component['logo_path'])
            ? '<img class="credit-card__logo" src="' . View::e(View::url((string) $component['logo_path'])) . '" alt="" loading="lazy" width="40" height="40">'
            : '<span class="credit-card__logo credit-card__logo--placeholder" aria-hidden="true">' . View::e(mb_substr((string) $component['name'], 0, 1)) . '</span>';
        $links = '';

        foreach ($component['links'] as $link) {
            if (!is_array($link) || !isset($link['url'], $link['label'])) {
                continue;
            }

            $links .= '<li><a href="' . View::e((string) $link['url']) . '" rel="noopener noreferrer">' . View::e((string) $link['label']) . '</a></li>';
        }

        $facts = array_filter([
            $component['license'] !== '' ? 'Lizenz: ' . $component['license'] : '',
            $component['version'] !== '' ? 'Version ' . $component['version'] : '',
            CreditInput::COMMERCIAL_USE[$component['commercial_use']] ?? '',
            $component['attribution_required'] ? 'Namensnennung erforderlich' : '',
            $component['locally_embedded'] ? 'Lokal eingebettet' : '',
        ]);

        return '<article class="credit-card card">' . $logo
            . '<div class="credit-card__body"><h3>' . View::e($component['name']) . '</h3>'
            . ($component['provider_name'] !== '' ? '<p class="muted">von ' . View::e($component['provider_name']) . '</p>' : '')
            . ($component['description'] !== '' ? '<p>' . View::e($component['description']) . '</p>' : '')
            . ($component['provider_info'] !== '' ? '<p class="credit-card__attribution">' . nl2br(View::e($component['provider_info'])) . '</p>' : '')
            . '<p class="credit-card__facts">' . View::e(implode(' · ', $facts)) . '</p>'
            . Ui::chips($component['tags'])
            . ($links !== '' ? '<ul class="credit-card__links">' . $links . '</ul>' : '')
            . '</div></article>';
    }
}
