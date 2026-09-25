<?php

declare(strict_types=1);

namespace MGD\Starter\Core\Files;

use InvalidArgumentException;
use ParseError;
use RuntimeException;

/**
 * Lesen/Schreiben der frei bearbeitbaren Code-Dateien.
 * Erlaubt sind ausschließlich die in config.php konfigurierten Ziele (realpath-Allowlist).
 * Vor jedem Speichern wird eine Sicherung angelegt.
 */
final class CustomCodeStore
{
    public const LANGUAGES = [
        'css' => ['label' => 'CSS', 'path_key' => 'custom_assets', 'file' => 'custom.css'],
        'js' => ['label' => 'JavaScript', 'path_key' => 'custom_assets', 'file' => 'custom.js'],
        'php' => ['label' => 'PHP (custom/hooks.php)', 'path_key' => 'custom_php', 'file' => null],
    ];

    /**
     * @param array<string, string> $paths Pfade aus config.php
     */
    public function __construct(
        private readonly array $paths,
        private readonly int $maxBytes,
    ) {
    }

    public function path(string $language): string
    {
        $definition = self::LANGUAGES[$language] ?? throw new InvalidArgumentException('Unbekannte Sprache.');
        $base = $this->paths[$definition['path_key']] ?? '';

        if (!is_string($base) || $base === '') {
            throw new RuntimeException('Pfad nicht konfiguriert: ' . $definition['path_key']);
        }

        $target = $definition['file'] === null ? $base : rtrim($base, '/') . '/' . $definition['file'];
        $directory = realpath(dirname($target));
        $root = realpath((string) ($this->paths['root'] ?? ''));

        if ($directory === false || $root === false || !str_starts_with($directory . '/', rtrim($root, '/') . '/')) {
            throw new RuntimeException('Zielverzeichnis liegt außerhalb des Projekts oder fehlt.');
        }

        $resolved = $directory . '/' . basename($target);

        if (is_link($resolved)) {
            throw new RuntimeException('Symbolische Links werden nicht bearbeitet.');
        }

        return $resolved;
    }

    public function read(string $language): string
    {
        $path = $this->path($language);

        if (!is_file($path)) {
            return '';
        }

        $content = file_get_contents($path);

        return $content === false ? '' : $content;
    }

    /**
     * @return string Pfad der Sicherung (leer, wenn es noch keine Datei gab)
     */
    public function save(string $language, string $content): string
    {
        $path = $this->path($language);
        $content = str_replace("\r\n", "\n", $content);

        if (strlen($content) > $this->maxBytes) {
            throw new InvalidArgumentException('Datei ist größer als ' . $this->maxBytes . ' Bytes.');
        }

        if ($language === 'php') {
            $this->assertValidPhp($content);
        }

        $backup = $this->backup($language, $path);
        $temporary = $path . '.tmp-' . bin2hex(random_bytes(4));

        if (file_put_contents($temporary, $content, LOCK_EX) === false || !rename($temporary, $path)) {
            @unlink($temporary);

            throw new RuntimeException('Datei konnte nicht geschrieben werden. Schreibrechte prüfen.');
        }

        return $backup;
    }

    private function backup(string $language, string $path): string
    {
        if (!is_file($path)) {
            return '';
        }

        $directory = (string) ($this->paths['code_backups'] ?? '');

        if ($directory === '' || (!is_dir($directory) && !mkdir($directory, 0750, true))) {
            throw new RuntimeException('Sicherungsverzeichnis fehlt: storage/backups/code');
        }

        $target = rtrim($directory, '/') . '/' . $language . '-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(3)) . '.bak';

        if (!copy($path, $target)) {
            throw new RuntimeException('Sicherung konnte nicht angelegt werden.');
        }

        return $target;
    }

    private function assertValidPhp(string $content): void
    {
        if (!str_starts_with(ltrim($content), '<?php')) {
            throw new InvalidArgumentException('PHP-Datei muss mit <?php beginnen.');
        }

        try {
            token_get_all($content, TOKEN_PARSE);
        } catch (ParseError $error) {
            throw new InvalidArgumentException('PHP-Syntaxfehler in Zeile ' . $error->getLine() . ': ' . $error->getMessage());
        }
    }
}
