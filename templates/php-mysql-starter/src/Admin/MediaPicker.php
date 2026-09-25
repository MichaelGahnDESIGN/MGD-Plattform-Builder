<?php

declare(strict_types=1);

namespace MGD\Starter\Admin;

use MGD\Starter\Core\App;
use MGD\Starter\Core\View\View;
use Throwable;

/**
 * "Aus Medienbibliothek wählen": eine <datalist> mit Bildpfaden für Felder mit lokalen Pfaden.
 * Felder verweisen per list="media-library-paths" darauf; freie Eingabe bleibt möglich.
 */
final class MediaPicker
{
    public const LIST_ID = 'media-library-paths';

    public static function attribute(): string
    {
        return ' list="' . self::LIST_ID . '"';
    }

    public static function datalist(App $app): string
    {
        try {
            $paths = $app->media()->imagePaths();
        } catch (Throwable $exception) {
            // Migration 003 noch nicht ausgeführt: Formular bleibt ohne Vorschläge nutzbar.
            error_log('[mgd-starter] media picker: ' . $exception->getMessage());
            $paths = [];
        }

        $options = '';

        foreach ($paths as $path) {
            $options .= '<option value="' . View::e($path) . '"></option>';
        }

        return '<datalist id="' . self::LIST_ID . '">' . $options . '</datalist>'
            . '<p class="muted media-picker-hint">Tipp: Im Pfadfeld werden Bilder aus der '
            . '<a href="' . View::e(View::url('/admin/media')) . '">Medienbibliothek</a> vorgeschlagen.</p>';
    }
}
