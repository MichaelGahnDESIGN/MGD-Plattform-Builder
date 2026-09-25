<?php

declare(strict_types=1);

namespace MGD\Starter\Core\Settings;

use InvalidArgumentException;

final class SettingsValidator
{
    private const DEFAULT_TEXT_LENGTH = 500;
    private const DEFAULT_TEXTAREA_LENGTH = 5000;
    public const COLOR_PATTERN = '/^#[0-9a-f]{6}$/';
    public const LOCAL_PATH_PATTERN = '#^/(assets|uploads)/[A-Za-z0-9._/-]+$#';

    public function normalize(array $definition, mixed $raw): mixed
    {
        return match ($definition['type']) {
            'bool' => in_array($raw, [true, 1, '1', 'on', 'true', 'yes'], true),
            'text' => $this->text($definition, $raw, self::DEFAULT_TEXT_LENGTH),
            'textarea' => $this->text($definition, $raw, self::DEFAULT_TEXTAREA_LENGTH, true),
            'color' => $this->color($definition, $raw),
            'select' => $this->select($definition, $raw),
            'multiselect' => $this->multiselect($definition, $raw),
            'url' => $this->url($definition, $raw),
            'number' => $this->number($definition, $raw),
            default => throw new InvalidArgumentException('Unbekannter Einstellungstyp: ' . $definition['type']),
        };
    }

    public static function isLocalPath(string $path): bool
    {
        return preg_match(self::LOCAL_PATH_PATTERN, $path) === 1 && !str_contains($path, '..');
    }

    private function text(array $definition, mixed $raw, int $defaultLength, bool $multiline = false): string
    {
        $value = is_scalar($raw) ? trim((string) $raw) : '';
        $pattern = $multiline ? '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/u' : '/[\x00-\x1F\x7F]/u';
        $value = (string) preg_replace($pattern, '', $value);
        $constraints = $definition['constraints'];
        $max = (int) ($constraints['max_length'] ?? $defaultLength);

        if (mb_strlen($value) > $max) {
            throw $this->error($definition, 'darf höchstens ' . $max . ' Zeichen lang sein.');
        }

        if ($value !== '' && isset($constraints['pattern']) && preg_match($constraints['pattern'], $value) !== 1) {
            throw $this->error($definition, 'enthält unzulässige Zeichen.');
        }

        if ($value !== '' && !empty($constraints['local_path']) && !self::isLocalPath($value)) {
            throw $this->error($definition, 'muss ein lokaler Pfad unter /assets/ oder /uploads/ sein.');
        }

        return $value;
    }

    private function color(array $definition, mixed $raw): string
    {
        $value = is_string($raw) ? strtolower(trim($raw)) : '';

        if (preg_match(self::COLOR_PATTERN, $value) !== 1) {
            throw $this->error($definition, 'muss eine Hex-Farbe im Format #rrggbb sein.');
        }

        return $value;
    }

    private function select(array $definition, mixed $raw): string
    {
        $value = is_string($raw) ? $raw : '';

        if (!array_key_exists($value, $definition['options'])) {
            throw $this->error($definition, 'hat einen ungültigen Wert.');
        }

        return $value;
    }

    /**
     * @return list<string>
     */
    private function multiselect(array $definition, mixed $raw): array
    {
        $values = is_array($raw) ? array_filter($raw, 'is_string') : [];

        foreach ($values as $value) {
            if (!array_key_exists($value, $definition['options'])) {
                throw $this->error($definition, 'enthält einen ungültigen Wert.');
            }
        }

        return array_values(array_filter(
            array_map('strval', array_keys($definition['options'])),
            static fn (string $option): bool => in_array($option, $values, true)
        ));
    }

    private function url(array $definition, mixed $raw): string
    {
        $value = $this->text($definition, $raw, self::DEFAULT_TEXT_LENGTH);

        if ($value === '') {
            return '';
        }

        $parts = parse_url($value);
        $scheme = is_array($parts) ? strtolower((string) ($parts['scheme'] ?? '')) : '';
        $allowed = !empty($definition['constraints']['https_only']) ? ['https'] : ['http', 'https'];

        if (!in_array($scheme, $allowed, true) || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            $hint = $allowed === ['https'] ? 'muss eine HTTPS-URL sein.' : 'muss eine http(s)-URL sein.';

            throw $this->error($definition, $hint);
        }

        return $value;
    }

    private function number(array $definition, mixed $raw): int
    {
        $value = is_int($raw) ? (string) $raw : (is_string($raw) ? trim($raw) : '');

        if (preg_match('/^-?\d{1,9}$/', $value) !== 1) {
            throw $this->error($definition, 'muss eine ganze Zahl sein.');
        }

        $number = (int) $value;
        $min = $definition['constraints']['min'] ?? PHP_INT_MIN;
        $max = $definition['constraints']['max'] ?? PHP_INT_MAX;

        if ($number < $min || $number > $max) {
            throw $this->error($definition, 'muss zwischen ' . $min . ' und ' . $max . ' liegen.');
        }

        return $number;
    }

    private function error(array $definition, string $message): InvalidArgumentException
    {
        return new InvalidArgumentException('"' . $definition['label'] . '" ' . $message);
    }
}
