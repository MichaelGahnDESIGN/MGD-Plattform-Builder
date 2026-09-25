<?php

declare(strict_types=1);

namespace MGD\Starter\Admin;

use MGD\Starter\Core\Settings\SettingsRegistry;
use MGD\Starter\Core\View\View;

/**
 * Rendert eine Einstellungszeile passend zum Typ. Jede Zeile trägt Such-Metadaten für settings-search.js.
 */
final class SettingFieldRenderer
{
    public function __construct(private readonly SettingsRegistry $registry)
    {
    }

    public function row(array $definition, mixed $value): string
    {
        $key = $definition['key'];
        $id = 'setting-' . preg_replace('/[^a-z0-9]+/', '-', $key);
        $name = 's[' . $key . ']';

        return '<div class="setting-row" data-category="' . View::e($definition['category']) . '"'
            . ' data-search="' . View::e($this->registry->searchText($definition)) . '">'
            . '<input type="hidden" name="keys[]" value="' . View::e($key) . '">'
            . '<div class="setting-row__meta"><label for="' . View::e($id) . '">' . View::e($definition['label']) . '</label>'
            . '<p class="muted">' . View::e($definition['description']) . '</p>'
            . '<code class="setting-key">' . View::e($key) . '</code>'
            . ($definition['visibility'] === 'public' ? ' <span class="badge badge-neutral">öffentlich</span>' : '')
            . '</div><div class="setting-row__control">' . $this->control($definition, $id, $name, $value) . '</div></div>';
    }

    private function control(array $definition, string $id, string $name, mixed $value): string
    {
        $idAttr = ' id="' . View::e($id) . '"';
        $scalar = is_scalar($value) ? (string) $value : '';
        $constraints = $definition['constraints'];

        return match ($definition['type']) {
            'bool' => '<label class="switch"><input type="checkbox"' . $idAttr . ' name="' . View::e($name) . '" value="1"'
                . ($value === true ? ' checked' : '') . '><span>Aktiv</span></label>',
            'textarea' => '<textarea' . $idAttr . ' name="' . View::e($name) . '" rows="4">' . View::e($scalar) . '</textarea>',
            'color' => '<span class="color-field"><input type="color"' . $idAttr . ' name="' . View::e($name) . '" value="'
                . View::e($scalar) . '" data-swatch-input><code>' . View::e($scalar) . '</code></span>',
            'number' => '<input type="number"' . $idAttr . ' name="' . View::e($name) . '" value="' . View::e($scalar) . '"'
                . (isset($constraints['min']) ? ' min="' . (int) $constraints['min'] . '"' : '')
                . (isset($constraints['max']) ? ' max="' . (int) $constraints['max'] . '"' : '') . '>',
            'select' => $this->select($definition, $idAttr, $name, $scalar),
            'multiselect' => $this->multiselect($definition, $id, $name, is_array($value) ? $value : []),
            'url' => '<input type="url"' . $idAttr . ' name="' . View::e($name) . '" value="' . View::e($scalar) . '"'
                . (!empty($constraints['https_only']) ? ' pattern="https://.*" placeholder="https://…"' : '') . '>',
            default => '<input type="text"' . $idAttr . ' name="' . View::e($name) . '" value="' . View::e($scalar) . '"'
                . (isset($constraints['max_length']) ? ' maxlength="' . (int) $constraints['max_length'] . '"' : '') . '>',
        };
    }

    private function select(array $definition, string $idAttr, string $name, string $selected): string
    {
        $html = '<select' . $idAttr . ' name="' . View::e($name) . '">';

        foreach ($definition['options'] as $option => $label) {
            $html .= '<option value="' . View::e((string) $option) . '"' . ((string) $option === $selected ? ' selected' : '')
                . '>' . View::e($label) . '</option>';
        }

        return $html . '</select>';
    }

    private function multiselect(array $definition, string $id, string $name, array $selected): string
    {
        $html = '<div class="check-list" id="' . View::e($id) . '">';

        foreach ($definition['options'] as $option => $label) {
            $html .= '<label class="check"><input type="checkbox" name="' . View::e($name) . '[]" value="' . View::e((string) $option) . '"'
                . (in_array((string) $option, $selected, true) ? ' checked' : '') . '> ' . View::e($label) . '</label>';
        }

        return $html . '</div>';
    }
}
