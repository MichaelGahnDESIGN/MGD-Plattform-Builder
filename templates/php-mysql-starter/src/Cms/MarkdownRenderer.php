<?php

declare(strict_types=1);

namespace MGD\Starter\Cms;

/**
 * Bewusst kleiner Markdown-Renderer (Überschriften, Absätze, Listen, Zitate,
 * Codeblöcke, Trennlinien, fett/kursiv/Code/Links). Die Ausgabe wird danach
 * immer noch durch den HtmlSanitizer geschickt.
 */
final class MarkdownRenderer
{
    public function render(string $markdown): string
    {
        $lines = preg_split('/\R/', str_replace("\t", '    ', $markdown)) ?: [];
        $html = [];
        $paragraph = [];
        $list = null;
        $inCode = false;
        $code = [];

        $flushParagraph = function () use (&$paragraph, &$html): void {
            if ($paragraph !== []) {
                $html[] = '<p>' . $this->inline(implode(' ', $paragraph)) . '</p>';
                $paragraph = [];
            }
        };
        $closeList = function () use (&$list, &$html): void {
            if ($list !== null) {
                $html[] = '</' . $list . '>';
                $list = null;
            }
        };

        foreach ($lines as $line) {
            if (str_starts_with(trim($line), '```')) {
                if ($inCode) {
                    $html[] = '<pre><code>' . $this->escape(implode("\n", $code)) . '</code></pre>';
                    $code = [];
                    $inCode = false;
                } else {
                    $flushParagraph();
                    $closeList();
                    $inCode = true;
                }
                continue;
            }

            if ($inCode) {
                $code[] = $line;
                continue;
            }

            $trimmed = trim($line);

            if ($trimmed === '') {
                $flushParagraph();
                $closeList();
                continue;
            }

            if (preg_match('/^(#{1,6})\s+(.+)$/', $trimmed, $m) === 1) {
                $flushParagraph();
                $closeList();
                $level = strlen($m[1]);
                $html[] = '<h' . $level . '>' . $this->inline($m[2]) . '</h' . $level . '>';
                continue;
            }

            if (preg_match('/^(-{3,}|\*{3,})$/', $trimmed) === 1) {
                $flushParagraph();
                $closeList();
                $html[] = '<hr>';
                continue;
            }

            if (preg_match('/^>\s?(.*)$/', $trimmed, $m) === 1) {
                $flushParagraph();
                $closeList();
                $html[] = '<blockquote><p>' . $this->inline($m[1]) . '</p></blockquote>';
                continue;
            }

            if (preg_match('/^([-*+]|\d+\.)\s+(.+)$/', $trimmed, $m) === 1) {
                $flushParagraph();
                $type = ctype_digit(rtrim($m[1], '.')) ? 'ol' : 'ul';

                if ($list !== $type) {
                    $closeList();
                    $html[] = '<' . $type . '>';
                    $list = $type;
                }

                $html[] = '<li>' . $this->inline($m[2]) . '</li>';
                continue;
            }

            $closeList();
            $paragraph[] = $trimmed;
        }

        if ($inCode) {
            $html[] = '<pre><code>' . $this->escape(implode("\n", $code)) . '</code></pre>';
        }

        $flushParagraph();
        $closeList();

        return implode("\n", $html);
    }

    private function inline(string $text): string
    {
        $text = $this->escape($text);
        $text = (string) preg_replace('/`([^`]+)`/', '<code>$1</code>', $text);
        $text = (string) preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $text);
        $text = (string) preg_replace('/(?<![*\w])\*(?!\s)(.+?)(?<!\s)\*(?![*\w])/', '<em>$1</em>', $text);

        return (string) preg_replace_callback(
            '/\[([^\]]+)\]\(([^)\s]+)\)/',
            static fn (array $m): string => '<a href="' . $m[2] . '">' . $m[1] . '</a>',
            $text
        );
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
