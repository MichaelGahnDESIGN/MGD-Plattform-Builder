<?php

declare(strict_types=1);

namespace MGD\Starter\Admin;

use InvalidArgumentException;
use MGD\Starter\Core\Auth\Role;
use MGD\Starter\Core\Auth\User;
use MGD\Starter\Core\Http\HttpException;
use MGD\Starter\Core\Http\Request;
use MGD\Starter\Core\Http\Response;
use MGD\Starter\Core\View\Flash;
use MGD\Starter\Core\View\Form;
use MGD\Starter\Core\View\Ui;
use MGD\Starter\Core\View\View;
use RuntimeException;

/**
 * Medienbibliothek (Redaktion und Admins): Upload, Suche, Alternativtext, Pfad kopieren, Löschen.
 */
final class MediaController extends AdminController
{
    private const PATH = '/admin/media';
    private const MAX_FILES_PER_UPLOAD = 20;

    public function index(Request $request): Response
    {
        $user = $this->guard($request, Role::Editor);
        $query = mb_substr($request->query('q'), 0, 100);
        $items = $this->app->media()->list($query);
        $cards = implode('', array_map(fn (array $item): string => $this->card($item), $items));
        $filter = '<form method="get" class="filter-bar" action="' . View::e(View::url(self::PATH)) . '">'
            . Form::text('q', 'Suche', $query, ' type="search" placeholder="Dateiname, Alt-Text oder Pfad"')
            . Form::submit('Suchen', 'button button-secondary') . '</form>';

        return $this->page(
            'Medien',
            Ui::pageHeader('Medien', '', 'Bilder (JPG, PNG, WebP, GIF) und PDFs. Dateien werden geprüft, umbenannt und Bilder ohne Metadaten neu gespeichert.')
                . $this->uploadForm() . $filter
                . ($cards === '' ? Ui::emptyState('Keine Medien gefunden.') : '<ul class="media-grid">' . $cards . '</ul>'),
            $user,
            self::PATH
        );
    }

    public function upload(Request $request): Response
    {
        $user = $this->guard($request, Role::Editor);
        $files = array_slice($request->fileList('files'), 0, self::MAX_FILES_PER_UPLOAD);
        $stored = [];

        foreach ($files as $file) {
            try {
                $id = $this->app->mediaStore()->storeUpload($file, $user->id);
                $stored[] = $id;
                $this->audit($user, 'media.upload', 'media', $id, ['name' => mb_substr((string) $file['name'], 0, 190)]);
            } catch (InvalidArgumentException | RuntimeException $exception) {
                Flash::add('danger', mb_substr((string) $file['name'], 0, 190) . ': ' . $exception->getMessage());
            }
        }

        return $this->redirectWith(self::PATH, $stored === [] ? 'warning' : 'success', count($stored) . ' Datei(en) hochgeladen.');
    }

    public function update(Request $request, array $params): Response
    {
        $user = $this->guard($request, Role::Editor);
        $item = $this->find((int) $params['id']);

        try {
            $this->app->media()->updateAlt((int) $item['id'], $request->input('alt_text'));
        } catch (InvalidArgumentException $exception) {
            return $this->redirectWith(self::PATH, 'danger', $exception->getMessage());
        }

        $this->audit($user, 'media.update', 'media', (int) $item['id'], ['path' => $item['path']]);

        return $this->redirectWith(self::PATH, 'success', 'Alternativtext gespeichert.');
    }

    public function delete(Request $request, array $params): Response
    {
        $user = $this->guard($request, Role::Editor);
        $item = $this->find((int) $params['id']);
        $references = $this->app->media()->references((string) $item['path']);

        if ($references !== []) {
            return $this->redirectWith(self::PATH, 'danger', 'Datei wird noch verwendet: ' . implode(', ', $references) . '.');
        }

        try {
            $this->app->mediaStore()->delete($item);
        } catch (InvalidArgumentException | RuntimeException $exception) {
            return $this->redirectWith(self::PATH, 'danger', $exception->getMessage());
        }

        $this->audit($user, 'media.delete', 'media', (int) $item['id'], ['path' => $item['path'], 'name' => $item['original_name']]);

        return $this->redirectWith(self::PATH, 'success', 'Datei gelöscht.');
    }

    private function uploadForm(): string
    {
        $max = (int) $this->app->config->get('security.max_upload_bytes', 5242880);

        return '<section class="card">'
            . Form::open(self::PATH . '/upload', ' class="stack" enctype="multipart/form-data"')
            . '<label class="field"><span>Dateien hochladen (max. ' . View::e((string) round($max / 1048576, 1)) . ' MB pro Datei, bis zu '
            . self::MAX_FILES_PER_UPLOAD . ' Dateien)</span>'
            . '<input type="file" name="files[]" multiple required accept=".jpg,.jpeg,.png,.webp,.gif,.pdf"></label>'
            . Form::submit('Hochladen') . '</form></section>';
    }

    private function card(array $item): string
    {
        $id = (int) $item['id'];
        $path = (string) $item['path'];
        $isImage = str_starts_with((string) $item['mime'], 'image/');
        $preview = $isImage
            ? '<img src="' . View::e(View::url($path)) . '" alt="' . View::e($item['alt_text']) . '" loading="lazy">'
            : '<span class="media-card__file">PDF</span>';
        $dimensions = $item['width'] !== null ? ' · ' . (int) $item['width'] . '×' . (int) $item['height'] : '';

        return '<li class="media-card">' . $preview
            . '<div class="media-card__body"><strong title="' . View::e($item['original_name']) . '">' . View::e($item['original_name']) . '</strong>'
            . '<span class="muted">' . View::e(self::formatSize((int) $item['size_bytes'])) . $dimensions . ' · ' . View::e(View::date($item['created_at'])) . '</span>'
            . '<label class="field"><span>Pfad</span><input type="text" readonly value="' . View::e($path) . '" data-copy-path></label>'
            . Form::open(self::PATH . '/' . $id . '/edit', ' class="stack"')
            . Form::text('alt_text', 'Alternativtext', (string) $item['alt_text'], ' maxlength="300"')
            . Form::submit('Speichern', 'button button-small button-secondary') . '</form>'
            . '<div class="row-actions"><button type="button" class="button button-small button-ghost" data-copy-target="' . View::e($path) . '">Pfad kopieren</button>'
            . Form::action(self::PATH . '/' . $id . '/delete', 'Löschen', 'Datei endgültig löschen?', 'button button-small button-danger')
            . '</div></div></li>';
    }

    private function find(int $id): array
    {
        return $this->app->media()->find($id) ?? throw new HttpException(404, 'Datei nicht gefunden.');
    }

    private static function formatSize(int $bytes): string
    {
        return $bytes >= 1048576 ? round($bytes / 1048576, 1) . ' MB' : max(1, (int) round($bytes / 1024)) . ' KB';
    }
}
