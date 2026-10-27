<?php

declare(strict_types=1);

namespace MGD\Starter\Admin;

use InvalidArgumentException;
use MGD\Starter\Core\Auth\Role;
use MGD\Starter\Core\Auth\User;
use MGD\Starter\Core\Http\Request;
use MGD\Starter\Core\Http\Response;
use MGD\Starter\Core\View\Flash;
use MGD\Starter\Core\View\Form;
use MGD\Starter\Core\View\Ui;
use MGD\Starter\Core\View\View;

final class SettingsController extends AdminController
{
    public function index(Request $request): Response
    {
        $user = $this->guard($request, Role::Admin);
        $registry = $this->app->registry();
        $query = mb_substr($request->query('q'), 0, 100);
        $category = array_key_exists($request->query('category'), $registry->categories()) ? $request->query('category') : '';
        $groups = $registry->search($query, $category);
        $renderer = new SettingFieldRenderer($registry);
        $body = '';

        foreach ($groups as $groupCategory => $definitions) {
            $rows = '';

            foreach ($definitions as $definition) {
                $rows .= $renderer->row($definition, $this->app->settings()->get($definition['key']));
            }

            $body .= '<fieldset class="settings-group" id="cat-' . View::e($groupCategory) . '" data-category="' . View::e($groupCategory) . '">'
                . '<legend>' . View::e($registry->categoryLabel($groupCategory)) . '</legend>' . $rows
                . ($groupCategory === 'design' ? $this->designPreview() : '') . '</fieldset>';
        }

        $form = $groups === []
            ? Ui::emptyState('Keine Einstellungen gefunden.')
            : Form::open('/admin/settings' . $this->filterQuery($query, $category), ' class="settings-form"') . $body
                . '<p class="settings-empty" data-settings-empty hidden>Keine Einstellungen passen zur Suche.</p>'
                . '<div class="sticky-actions">' . Form::submit('Einstellungen speichern') . '</div></form>'
                . MediaPicker::datalist($this->app);
        $reset = isset($groups['design'])
            ? '<section class="card">' . '<h2>Design zurücksetzen</h2><p class="muted">Setzt alle Farben, Radius und Schrift auf die Standardwerte.</p>'
                . Form::action('/admin/settings/design-reset', 'Auf Standard zurücksetzen', 'Alle Design-Einstellungen zurücksetzen?') . '</section>'
            : '';

        return $this->page(
            'Einstellungen',
            Ui::pageHeader('Einstellungen', $this->app->versionDisplay()->render('settings')
                . Ui::link('/admin/license', 'Lizenz', 'button button-ghost'))
                . $this->app->poweredBy()->render('settings')
                . '<div class="settings-layout">' . $this->sidebar($query, $category) . '<div class="settings-main">' . $form . $reset . '</div></div>',
            $user,
            '/admin/settings',
            ['scripts' => [View::asset('js/settings-search.js')]]
        );
    }

    public function save(Request $request): Response
    {
        $user = $this->guard($request, Role::Admin);
        $settings = $this->app->settings();
        $registry = $this->app->registry();
        $submitted = $request->raw('s');
        $submitted = is_array($submitted) ? $submitted : [];
        $values = [];
        $errors = [];

        foreach (array_unique($request->inputList('keys')) as $key) {
            $definition = $registry->definition($key);

            if ($definition === null) {
                continue;
            }

            $raw = $submitted[$key] ?? match ($definition['type']) {
                'bool' => false,
                'multiselect' => [],
                default => '',
            };
            $values[$key] = $raw;
        }

        $changed = [];

        foreach ($values as $key => $raw) {
            $before = $settings->get($key);

            try {
                if ($settings->save($key, $raw, $user->id) !== $before) {
                    $changed[] = $key;
                }
            } catch (InvalidArgumentException $exception) {
                $errors[] = $exception->getMessage();
            }
        }

        if ($changed !== []) {
            $this->audit($user, 'settings.update', 'settings', null, ['keys' => $changed]);
        }

        foreach ($errors as $error) {
            Flash::add('danger', $error);
        }

        $target = '/admin/settings' . $this->filterQuery($request->query('q'), $request->query('category'));

        return $this->redirectWith($target, $errors === [] ? 'success' : 'warning', count($changed) . ' Einstellung(en) gespeichert.');
    }

    public function resetDesign(Request $request): Response
    {
        $user = $this->guard($request, Role::Admin);
        $count = $this->app->settings()->resetPrefix('design.');
        $this->audit($user, 'settings.design.reset', 'settings', 'design', ['removed' => $count]);

        return $this->redirectWith('/admin/settings?category=design', 'success', 'Design auf Standardwerte zurückgesetzt.');
    }

    private function sidebar(string $query, string $category): string
    {
        $registry = $this->app->registry();
        $counts = array_map('count', $registry->search('', ''));
        $links = '<li><a href="' . View::e(View::url('/admin/settings')) . '" data-settings-category=""'
            . ($category === '' ? ' aria-current="page"' : '') . '>Alle Kategorien</a></li>';

        foreach ($registry->categories() as $id => $label) {
            $links .= '<li><a href="' . View::e(View::url('/admin/settings?category=' . rawurlencode($id))) . '" data-settings-category="' . View::e($id) . '"'
                . ($category === $id ? ' aria-current="page"' : '') . '>' . View::e($label)
                . ' <span class="count">' . (int) ($counts[$id] ?? 0) . '</span></a></li>';
        }

        return '<aside class="settings-sidebar"><form method="get" action="' . View::e(View::url('/admin/settings')) . '" class="stack" role="search">'
            . '<label class="field"><span>Einstellungen durchsuchen</span><input type="search" name="q" id="settings-search" value="' . View::e($query)
            . '" placeholder="z. B. dark, footer, cdn" autocomplete="off"></label>'
            . Form::select('category', 'Kategorie', ['' => 'Alle Kategorien', ...$registry->categories()], $category, ' id="settings-category"')
            . Form::submit('Filtern', 'button button-secondary') . '</form>'
            . '<nav aria-label="Kategorien"><ul class="settings-categories">' . $links . '</ul></nav></aside>';
    }

    private function designPreview(): string
    {
        $settings = $this->app->settings();
        $html = '<div class="design-preview">';

        foreach (['light' => 'Hell', 'dark' => 'Dunkel'] as $mode => $label) {
            $html .= '<div class="design-preview__mode"><h3>' . View::e($label) . '</h3><div class="swatches">';

            foreach ($this->app->registry()->palette() as $name) {
                $color = $settings->string('design.' . $mode . '.' . $name);
                $html .= '<span class="swatch" title="' . View::e($name) . '" data-swatch="design.' . View::e($mode . '.' . $name) . '"'
                    . ' style="background:' . View::e($color) . '"></span>';
            }

            $html .= '</div></div>';
        }

        return $html . '</div>';
    }

    private function filterQuery(string $query, string $category): string
    {
        $params = array_filter(['q' => mb_substr($query, 0, 100), 'category' => preg_replace('/[^a-z_]/', '', $category)]);

        return $params === [] ? '' : '?' . http_build_query($params);
    }
}
