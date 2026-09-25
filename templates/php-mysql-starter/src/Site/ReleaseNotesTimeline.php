<?php

declare(strict_types=1);

namespace MGD\Starter\Site;

use MGD\Starter\Cms\ReleaseNoteInput;
use MGD\Starter\Core\Version\VersionStatus;
use MGD\Starter\Core\View\Ui;
use MGD\Starter\Core\View\View;

/**
 * Zeitleiste der Release Notes, gruppiert nach Version (neueste zuerst).
 * Wird öffentlich und im Backoffice verwendet.
 */
final class ReleaseNotesTimeline
{
    public function __construct(private readonly string $locale = 'de')
    {
    }

    /**
     * @param array<string, list<array>> $grouped
     * @param (callable(array): string)|null $actions liefert escaptes HTML für Aktionen je Eintrag
     */
    public function render(array $grouped, ?callable $actions = null, bool $showAudience = true): string
    {
        if ($grouped === []) {
            return Ui::emptyState('Noch keine Release Notes vorhanden.');
        }

        $html = '<ol class="timeline">';

        foreach ($grouped as $version => $entries) {
            $status = VersionStatus::tryFrom($entries[0]['status']);
            $html .= '<li class="timeline__version"><div class="timeline__marker" aria-hidden="true"></div>'
                . '<h2 class="timeline__title">Version ' . View::e((string) $version) . ' '
                . ($status !== null ? Ui::badge($status->label($this->locale), $status->badgeVariant()) : '') . '</h2>';

            foreach ($entries as $entry) {
                $html .= $this->entry($entry, $actions, $showAudience);
            }

            $html .= '</li>';
        }

        return $html . '</ol>';
    }

    private function entry(array $entry, ?callable $actions, bool $showAudience): string
    {
        $items = '';

        foreach ($entry['items'] as $item) {
            $items .= '<li>' . View::e($item) . '</li>';
        }

        $audience = $showAudience
            ? Ui::chips(array_map(static fn (string $a): string => ReleaseNoteInput::AUDIENCES[$a] ?? $a, $entry['audience']))
            : '';

        return '<article class="timeline__entry card">'
            . '<header class="timeline__entry-head">'
            . Ui::badge(ReleaseNoteInput::TYPES[$entry['type']] ?? $entry['type'], ReleaseNoteInput::TYPE_VARIANTS[$entry['type']] ?? 'neutral')
            . '<h3>' . View::e($entry['title']) . '</h3>'
            . '<time datetime="' . View::e($entry['date']) . '">' . View::e(View::date($entry['date'])) . '</time>'
            . '</header>'
            . ($items !== '' ? '<ul>' . $items . '</ul>' : '')
            . $audience
            . ($actions !== null ? '<div class="row-actions">' . $actions($entry) . '</div>' : '')
            . '</article>';
    }
}
