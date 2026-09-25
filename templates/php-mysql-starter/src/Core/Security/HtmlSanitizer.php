<?php

declare(strict_types=1);

namespace MGD\Starter\Core\Security;

use DOMDocument;
use DOMElement;
use DOMNode;

/**
 * Allowlist-Sanitizer für CMS-HTML auf Basis von DOMDocument.
 * Alles, was nicht ausdrücklich erlaubt ist, wird entfernt (Elemente werden
 * entpackt, gefährliche Elemente samt Inhalt gelöscht).
 */
final class HtmlSanitizer
{
    private const GLOBAL_ATTRIBUTES = ['class', 'id', 'title', 'lang'];

    private const ALLOWED = [
        'p' => [], 'h1' => [], 'h2' => [], 'h3' => [], 'h4' => [], 'h5' => [], 'h6' => [],
        'ul' => [], 'ol' => ['start'], 'li' => [],
        'a' => ['href', 'target', 'rel'],
        'strong' => [], 'em' => [], 'b' => [], 'i' => [], 'u' => [], 's' => [], 'small' => [],
        'sub' => [], 'sup' => [], 'mark' => [], 'abbr' => [],
        'blockquote' => ['cite'], 'code' => [], 'pre' => [],
        'img' => ['src', 'alt', 'width', 'height', 'loading'],
        'figure' => [], 'figcaption' => [],
        'table' => [], 'caption' => [], 'thead' => [], 'tbody' => [], 'tfoot' => [], 'tr' => [],
        'th' => ['colspan', 'rowspan', 'scope'], 'td' => ['colspan', 'rowspan'],
        'br' => [], 'hr' => [], 'span' => [], 'div' => [],
        'section' => [], 'article' => [], 'details' => [], 'summary' => [],
    ];

    private const DROP_WITH_CONTENT = [
        'script', 'style', 'iframe', 'frame', 'frameset', 'object', 'embed', 'applet', 'template',
        'noscript', 'form', 'input', 'button', 'textarea', 'select', 'option', 'svg', 'math',
        'link', 'meta', 'base', 'head', 'title', 'video', 'audio', 'source', 'track', 'canvas',
    ];

    private const LINK_SCHEMES = ['http', 'https', 'mailto'];
    private const IMAGE_SCHEMES = ['http', 'https'];

    public function sanitize(string $html): string
    {
        if (trim($html) === '') {
            return '';
        }

        $document = new DOMDocument('1.0', 'UTF-8');
        $previous = libxml_use_internal_errors(true);
        $document->loadHTML(
            '<!DOCTYPE html><html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"></head><body>'
            . $html . '</body></html>',
            LIBXML_NONET
        );
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $body = $document->getElementsByTagName('body')->item(0);

        if ($body === null) {
            return '';
        }

        $this->cleanChildren($body);
        $output = '';

        foreach ($body->childNodes as $child) {
            $output .= $document->saveHTML($child);
        }

        return trim($output);
    }

    private function cleanChildren(DOMNode $parent): void
    {
        foreach (iterator_to_array($parent->childNodes) as $child) {
            if ($child->nodeType === XML_TEXT_NODE) {
                continue;
            }

            if (!$child instanceof DOMElement) {
                $parent->removeChild($child);
                continue;
            }

            $this->cleanElement($parent, $child);
        }
    }

    private function cleanElement(DOMNode $parent, DOMElement $element): void
    {
        $tag = strtolower($element->nodeName);

        if (in_array($tag, self::DROP_WITH_CONTENT, true)) {
            $parent->removeChild($element);

            return;
        }

        $this->cleanChildren($element);

        if (!array_key_exists($tag, self::ALLOWED)) {
            while ($element->firstChild !== null) {
                $parent->insertBefore($element->firstChild, $element);
            }

            $parent->removeChild($element);

            return;
        }

        $this->cleanAttributes($element, $tag);
    }

    private function cleanAttributes(DOMElement $element, string $tag): void
    {
        $allowed = [...self::GLOBAL_ATTRIBUTES, ...self::ALLOWED[$tag]];

        foreach (iterator_to_array($element->attributes) as $attribute) {
            $name = strtolower($attribute->nodeName);
            $value = $attribute->nodeValue ?? '';

            if (!in_array($name, $allowed, true) || !$this->isSafeAttribute($tag, $name, $value)) {
                $element->removeAttribute($attribute->nodeName);
            }
        }

        if ($tag === 'a' && $element->getAttribute('target') !== '') {
            $element->setAttribute('target', '_blank');
            $element->setAttribute('rel', 'noopener noreferrer');
        }
    }

    private function isSafeAttribute(string $tag, string $name, string $value): bool
    {
        return match ($name) {
            'href' => self::isSafeUrl($value, self::LINK_SCHEMES),
            'src' => $tag === 'img' && self::isSafeUrl($value, self::IMAGE_SCHEMES),
            'cite' => self::isSafeUrl($value, ['http', 'https']),
            'class', 'id' => preg_match('/^[A-Za-z0-9 _-]{1,200}$/', $value) === 1,
            'width', 'height', 'colspan', 'rowspan', 'start' => preg_match('/^\d{1,4}$/', $value) === 1,
            'scope' => in_array($value, ['row', 'col', 'rowgroup', 'colgroup'], true),
            'loading' => in_array($value, ['lazy', 'eager'], true),
            'target' => $value === '_blank',
            default => mb_strlen($value) <= 500,
        };
    }

    /**
     * Erlaubt relative URLs und die angegebenen Schemata. Blockiert javascript:, data:, vbscript: usw.
     *
     * @param list<string> $schemes
     */
    public static function isSafeUrl(string $url, array $schemes): bool
    {
        $normalized = (string) preg_replace('/[\x00-\x20\x7F]+/', '', html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8'));

        if ($normalized === '') {
            return false;
        }

        if (preg_match('/^([a-z][a-z0-9+.-]*):/i', $normalized, $match) === 1) {
            return in_array(strtolower($match[1]), $schemes, true);
        }

        return !str_starts_with($normalized, '\\') && !str_starts_with($normalized, '/\\');
    }
}
