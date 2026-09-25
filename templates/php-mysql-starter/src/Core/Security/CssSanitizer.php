<?php

declare(strict_types=1);

namespace MGD\Starter\Core\Security;

use InvalidArgumentException;

/**
 * Bereinigt Editor-CSS (GrapesJS) für die öffentliche Ausgabe in einem <style nonce>-Block.
 *
 * - entfernt Kommentare, Backslash-Escapes, "<", Steuerzeichen,
 * - verwirft @import, @charset, @namespace und alle unbekannten At-Regeln,
 * - verwirft Deklarationen mit expression(), behavior, -moz-binding, javascript:, image-set()
 *   oder url() außerhalb von /assets/ bzw. /uploads/,
 * - verwirft @font-face mit externer Quelle,
 * - begrenzt jeden Selektor auf .page-content (auch innerhalb von @media/@supports).
 */
final class CssSanitizer
{
    public const SCOPE = '.page-content';
    public const MAX_LENGTH = 200_000;
    private const MAX_DEPTH = 3;
    private const SELECTOR_PATTERN = '/^[A-Za-z0-9_\-\s.#:,>+~*\[\]="\'()^$|%]+$/';
    private const PROPERTY_PATTERN = '/^(--[A-Za-z0-9_-]+|-?[a-z][a-z0-9-]*)$/i';
    private const LOCAL_URL_PATTERN = '#^/(assets|uploads)/[A-Za-z0-9._/-]+$#';
    private const FORBIDDEN_VALUE = '/expression\s*\(|javascript\s*:|vbscript\s*:|-moz-binding|behavior|image-set\s*\(|@import/i';
    private const CONDITIONAL_PRELUDE = '/^@(media|supports)\s[A-Za-z0-9\s():,.\-\/%=<>!]+$/i';
    private const KEYFRAMES_PRELUDE = '/^@(-webkit-)?keyframes\s+[A-Za-z0-9_-]+$/i';

    public function sanitize(string $css): string
    {
        if (strlen($css) > self::MAX_LENGTH) {
            throw new InvalidArgumentException('CSS ist zu groß (max. ' . self::MAX_LENGTH . ' Zeichen).');
        }

        $css = (string) preg_replace('#/\*.*?(\*/|$)#s', '', $css);
        $css = str_replace(['\\', '<'], '', $css);
        $css = (string) preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $css);

        return trim($this->block($css, 0));
    }

    private function block(string $css, int $depth): string
    {
        $output = [];

        foreach ($this->rules($css) as [$prelude, $body]) {
            $rule = $this->rule($prelude, $body, $depth);

            if ($rule !== '') {
                $output[] = $rule;
            }
        }

        return implode("\n", $output);
    }

    private function rule(string $prelude, ?string $body, int $depth): string
    {
        if ($body === null) {
            return ''; // At-Anweisungen ohne Block (@import, @charset, @namespace …) werden verworfen.
        }

        if (!str_starts_with($prelude, '@')) {
            $selectors = $this->scopeSelectors($prelude);
            $declarations = $this->declarations($body);

            return $selectors === '' || $declarations === '' ? '' : $selectors . '{' . $declarations . '}';
        }

        if ($depth < self::MAX_DEPTH && preg_match(self::CONDITIONAL_PRELUDE, $prelude) === 1) {
            $inner = $this->block($body, $depth + 1);

            return $inner === '' ? '' : preg_replace('/\s+/', ' ', $prelude) . '{' . $inner . '}';
        }

        if (preg_match(self::KEYFRAMES_PRELUDE, $prelude) === 1) {
            return $this->keyframes($prelude, $body);
        }

        if (strcasecmp($prelude, '@font-face') === 0) {
            $declarations = $this->declarations($body, true);

            return $declarations === '' ? '' : '@font-face{' . $declarations . '}';
        }

        return '';
    }

    private function keyframes(string $prelude, string $body): string
    {
        $frames = [];

        foreach ($this->rules($body) as [$selector, $frameBody]) {
            $declarations = $frameBody === null ? '' : $this->declarations($frameBody);

            if ($declarations !== '' && preg_match('/^(from|to|\d{1,3}(\.\d+)?%)(\s*,\s*(from|to|\d{1,3}(\.\d+)?%))*$/i', $selector) === 1) {
                $frames[] = $selector . '{' . $declarations . '}';
            }
        }

        return $frames === [] ? '' : $prelude . '{' . implode('', $frames) . '}';
    }

    private function scopeSelectors(string $prelude): string
    {
        if (preg_match(self::SELECTOR_PATTERN, $prelude) !== 1) {
            return '';
        }

        $scoped = [];

        foreach (self::splitTopLevel($prelude, ',') as $selector) {
            $selector = trim((string) preg_replace('/\s+/', ' ', $selector));

            if ($selector === '') {
                continue;
            }

            if ($selector === self::SCOPE || str_starts_with($selector, self::SCOPE . ' ')) {
                $scoped[] = $selector;
                continue;
            }

            $root = preg_replace('/^(html|body|:root)(?=$|[\s.#:\[>+~])/i', self::SCOPE, $selector, 1, $count);
            $scoped[] = $count > 0 ? (string) $root : self::SCOPE . ' ' . $selector;
        }

        return implode(',', array_unique($scoped));
    }

    private function declarations(string $body, bool $fontFace = false): string
    {
        $result = [];

        foreach (self::splitTopLevel($body, ';') as $declaration) {
            $parts = explode(':', $declaration, 2);

            if (count($parts) !== 2) {
                continue;
            }

            $property = strtolower(trim($parts[0]));
            $value = trim((string) preg_replace('/\s+/', ' ', $parts[1]));

            if ($value === '' || preg_match(self::PROPERTY_PATTERN, $property) !== 1 || !$this->isSafeValue($property, $value)) {
                if ($fontFace && $property === 'src') {
                    return ''; // @font-face mit externer oder unsicherer Quelle komplett verwerfen.
                }

                continue;
            }

            $result[] = $property . ':' . $value;
        }

        return implode(';', $result);
    }

    private function isSafeValue(string $property, string $value): bool
    {
        if (in_array($property, ['behavior', '-moz-binding'], true) || preg_match(self::FORBIDDEN_VALUE, $value) === 1) {
            return false;
        }

        if (str_contains($value, '{') || str_contains($value, '}')) {
            return false;
        }

        preg_match_all('/url\s*\(\s*([\'"]?)(.*?)\1\s*\)/i', $value, $matches);
        $urlCount = preg_match_all('/url\s*\(/i', $value);

        if ($urlCount !== count($matches[2])) {
            return false;
        }

        foreach ($matches[2] as $url) {
            if (preg_match(self::LOCAL_URL_PATTERN, $url) !== 1 || str_contains($url, '..')) {
                return false;
            }
        }

        return true;
    }

    /**
     * Zerlegt CSS in [prelude, body|null]. body = null bedeutet At-Anweisung ohne Block.
     *
     * @return list<array{0: string, 1: ?string}>
     */
    private function rules(string $css): array
    {
        $rules = [];
        $length = strlen($css);
        $start = 0;
        $i = 0;

        while ($i < $length) {
            $char = $css[$i];

            if ($char === '"' || $char === "'") {
                $i = self::skipString($css, $i);
                continue;
            }

            if ($char === ';') {
                $prelude = trim(substr($css, $start, $i - $start));

                if ($prelude !== '') {
                    $rules[] = [$prelude, null];
                }

                $start = ++$i;
                continue;
            }

            if ($char === '{') {
                $end = self::matchingBrace($css, $i);
                $rules[] = [trim(substr($css, $start, $i - $start)), substr($css, $i + 1, $end - $i - 1)];
                $start = $i = $end + 1;
                continue;
            }

            $i++;
        }

        return $rules;
    }

    private static function matchingBrace(string $css, int $open): int
    {
        $depth = 0;
        $length = strlen($css);

        for ($i = $open; $i < $length; $i++) {
            $char = $css[$i];

            if ($char === '"' || $char === "'") {
                $i = self::skipString($css, $i) - 1;
                continue;
            }

            if ($char === '{') {
                $depth++;
            } elseif ($char === '}' && --$depth === 0) {
                return $i;
            }
        }

        return $length; // Ungeschlossener Block: bis zum Ende lesen.
    }

    private static function skipString(string $css, int $start): int
    {
        $end = strpos($css, $css[$start], $start + 1);

        return $end === false ? strlen($css) : $end + 1;
    }

    /**
     * @return list<string>
     */
    private static function splitTopLevel(string $value, string $separator): array
    {
        $parts = [];
        $depth = 0;
        $current = '';
        $quote = '';

        foreach (str_split($value) as $char) {
            if ($quote !== '') {
                $quote = $char === $quote ? '' : $quote;
            } elseif ($char === '"' || $char === "'") {
                $quote = $char;
            } elseif ($char === '(' || $char === '[') {
                $depth++;
            } elseif (($char === ')' || $char === ']') && $depth > 0) {
                $depth--;
            } elseif ($char === $separator && $depth === 0) {
                $parts[] = $current;
                $current = '';
                continue;
            }

            $current .= $char;
        }

        $parts[] = $current;

        return $parts;
    }
}
