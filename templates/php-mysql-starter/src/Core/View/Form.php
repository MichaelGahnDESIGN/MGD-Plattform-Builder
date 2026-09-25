<?php

declare(strict_types=1);

namespace MGD\Starter\Core\View;

use MGD\Starter\Core\Auth\Csrf;

final class Form
{
    public static function open(string $action, string $extra = ''): string
    {
        return '<form method="post" action="' . View::e(View::url($action)) . '"' . $extra . '>' . self::csrf();
    }

    public static function csrf(): string
    {
        return '<input type="hidden" name="_csrf" value="' . View::e(Csrf::token()) . '">';
    }

    public static function text(string $name, string $label, string $value = '', string $attributes = '', string $type = 'text'): string
    {
        return '<label class="field"><span>' . View::e($label) . '</span>'
            . '<input type="' . View::e($type) . '" name="' . View::e($name) . '" value="' . View::e($value) . '"' . $attributes . '></label>';
    }

    public static function textarea(string $name, string $label, string $value = '', string $attributes = ''): string
    {
        return '<label class="field"><span>' . View::e($label) . '</span>'
            . '<textarea name="' . View::e($name) . '"' . $attributes . '>' . View::e($value) . '</textarea></label>';
    }

    /**
     * @param array<string, string> $options
     */
    public static function select(string $name, string $label, array $options, string $selected = '', string $attributes = ''): string
    {
        $html = '<label class="field"><span>' . View::e($label) . '</span><select name="' . View::e($name) . '"' . $attributes . '>';

        foreach ($options as $value => $optionLabel) {
            $isSelected = (string) $value === $selected ? ' selected' : '';
            $html .= '<option value="' . View::e((string) $value) . '"' . $isSelected . '>' . View::e($optionLabel) . '</option>';
        }

        return $html . '</select></label>';
    }

    /**
     * @param array<string, string> $options
     * @param list<string> $selected
     */
    public static function checkboxes(string $name, string $label, array $options, array $selected): string
    {
        $html = '<fieldset class="field checkbox-group"><legend>' . View::e($label) . '</legend>';

        foreach ($options as $value => $optionLabel) {
            $checked = in_array((string) $value, $selected, true) ? ' checked' : '';
            $html .= '<label class="check"><input type="checkbox" name="' . View::e($name) . '[]" value="'
                . View::e((string) $value) . '"' . $checked . '> ' . View::e($optionLabel) . '</label>';
        }

        return $html . '</fieldset>';
    }

    public static function checkbox(string $name, string $label, bool $checked): string
    {
        return '<label class="check"><input type="checkbox" name="' . View::e($name) . '" value="1"'
            . ($checked ? ' checked' : '') . '> ' . View::e($label) . '</label>';
    }

    public static function submit(string $label, string $class = 'button'): string
    {
        return '<button type="submit" class="' . View::e($class) . '">' . View::e($label) . '</button>';
    }

    /**
     * Einzelner POST-Button (z. B. Löschen) mit optionaler Bestätigung (admin.js).
     */
    public static function action(string $action, string $label, string $confirm = '', string $class = 'button button-secondary'): string
    {
        $confirmAttr = $confirm !== '' ? ' data-confirm="' . View::e($confirm) . '"' : '';

        return self::open($action, ' class="inline-form"' . $confirmAttr) . self::submit($label, $class) . '</form>';
    }
}
