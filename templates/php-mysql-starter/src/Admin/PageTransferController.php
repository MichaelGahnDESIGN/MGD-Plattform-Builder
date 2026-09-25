<?php

declare(strict_types=1);

namespace MGD\Starter\Admin;

use InvalidArgumentException;
use MGD\Starter\Core\Auth\Role;
use MGD\Starter\Core\Http\Request;
use MGD\Starter\Core\Http\Response;
use MGD\Starter\Core\View\Flash;
use MGD\Starter\Core\View\Form;
use MGD\Starter\Core\View\Ui;
use MGD\Starter\Core\View\View;

final class PageTransferController extends AdminController
{
    public function index(Request $request): Response
    {
        $user = $this->guard($request, Role::Editor);
        $options = '';

        foreach ($this->app->pages()->list() as $page) {
            $options .= '<label class="check"><input type="checkbox" name="ids[]" value="' . (int) $page['id'] . '"> '
                . View::e($page['title']) . ' <code>' . View::e($page['slug']) . '</code></label>';
        }

        $export = '<section class="card"><h2>Export</h2>'
            . Form::open('/admin/pages/export', ' class="stack"')
            . '<p class="muted">Keine Auswahl = alle Seiten (ohne Papierkorb).</p><div class="check-list">' . $options . '</div>'
            . Form::checkbox('with_revisions', 'Revisionen mitexportieren', false)
            . Form::submit('JSON herunterladen') . '</form></section>';

        $import = '<section class="card"><h2>Import</h2>'
            . Form::open('/admin/pages/import', ' class="stack" enctype="multipart/form-data"')
            . '<p class="muted">Format "mgd-cms-pages" v1. Bestehende Slugs erhalten eine neue Revision, neue Slugs werden angelegt. HTML wird immer bereinigt.</p>'
            . '<label class="field"><span>JSON-Datei</span><input type="file" name="file" accept="application/json,.json"></label>'
            . Form::textarea('json', 'oder JSON einfügen', '', ' rows="8" class="code-input"')
            . Form::submit('Importieren') . '</form></section>';

        return $this->page('Import / Export', Ui::pageHeader('Import / Export') . '<div class="grid-2">' . $export . $import . '</div>', $user, '/admin/pages/transfer');
    }

    public function export(Request $request): Response
    {
        $user = $this->guard($request, Role::Editor);
        $ids = array_map('intval', array_filter($request->inputList('ids'), 'ctype_digit'));
        $data = $this->app->pageTransfer()->export($ids, $request->input('with_revisions') === '1');
        $this->audit($user, 'page.export', 'page', null, ['count' => count($data['pages'])]);

        return Response::download(
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            'cms-pages-' . gmdate('Ymd-His') . '.json',
            'application/json; charset=utf-8'
        );
    }

    public function import(Request $request): Response
    {
        $user = $this->guard($request, Role::Editor);
        $maxBytes = (int) $this->app->config->get('security.max_import_bytes', 5242880);
        $file = $request->file('file');
        $json = $file !== null && is_uploaded_file($file['tmp_name'])
            ? (string) file_get_contents($file['tmp_name'], false, null, 0, $maxBytes + 1)
            : $request->input('json');

        try {
            $transfer = $this->app->pageTransfer();
            $summary = $transfer->import($transfer->decode($json, $maxBytes), $user);
        } catch (InvalidArgumentException $exception) {
            return $this->redirectWith('/admin/pages/transfer', 'danger', $exception->getMessage());
        }

        $this->audit($user, 'page.import', 'page', null, ['created' => $summary['created'], 'updated' => $summary['updated'], 'errors' => count($summary['errors'])]);
        $message = 'Import: ' . $summary['created'] . ' neu, ' . $summary['updated'] . ' aktualisiert, ' . count($summary['errors']) . ' Fehler.';

        foreach (array_slice($summary['errors'], 0, 10) as $error) {
            Flash::add('warning', $error);
        }

        return $this->redirectWith('/admin/pages/transfer', $summary['errors'] === [] ? 'success' : 'warning', $message);
    }
}
