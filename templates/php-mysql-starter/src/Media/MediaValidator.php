<?php

declare(strict_types=1);

namespace MGD\Starter\Media;

use finfo;
use InvalidArgumentException;
use RuntimeException;

/**
 * Prüft Mediendateien ohne Datenbank und ohne is_uploaded_file() (daher separat testbar).
 * Erlaubt nur JPG, PNG, WebP, GIF und PDF (kein SVG, kein HTML). Endung, erkannter MIME-Typ (finfo)
 * und bei Bildern getimagesize() müssen zusammenpassen.
 */
final class MediaValidator
{
    public const DEFAULT_MAX_BYTES = 5242880;
    public const ALLOWED = [
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
        'pdf' => 'application/pdf',
    ];
    private const MAX_PIXELS = 40_000_000;
    private const MAX_NAME_LENGTH = 190;

    public function __construct(private readonly int $maxBytes = self::DEFAULT_MAX_BYTES)
    {
    }

    /**
     * @return array{extension: string, mime: string, width: ?int, height: ?int, original_name: string}
     */
    public function validate(string $path, string $originalName): array
    {
        $name = self::cleanName($originalName);
        $extension = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        $size = is_file($path) ? (int) filesize($path) : 0;

        if (!isset(self::ALLOWED[$extension])) {
            throw new InvalidArgumentException('Dateityp nicht erlaubt (erlaubt: ' . implode(', ', array_keys(self::ALLOWED)) . ').');
        }

        if ($size <= 0 || $size > $this->maxBytes) {
            throw new InvalidArgumentException('Datei ist leer oder größer als ' . round($this->maxBytes / 1048576, 1) . ' MB.');
        }

        $mime = (string) (new finfo(FILEINFO_MIME_TYPE))->file($path);

        if ($mime !== self::ALLOWED[$extension]) {
            throw new InvalidArgumentException('Dateiinhalt passt nicht zur Endung .' . $extension . ' (erkannt: ' . $mime . ').');
        }

        if (self::containsCode($path)) {
            throw new InvalidArgumentException('Datei enthält unzulässigen Code.');
        }

        [$width, $height] = $mime === 'application/pdf' ? [null, null] : self::imageSize($path, $mime);

        return ['extension' => $extension === 'jpeg' ? 'jpg' : $extension, 'mime' => $mime, 'width' => $width, 'height' => $height, 'original_name' => $name];
    }

    /**
     * Kodiert ein Bild per GD neu (entfernt Metadaten und angehängte Daten). Gibt false zurück, wenn GD fehlt,
     * das Format nicht unterstützt wird oder ein animiertes GIF vorliegt – dann bleibt das Original.
     */
    public static function reencode(string $path, string $mime): bool
    {
        $functions = [
            'image/jpeg' => ['imagecreatefromjpeg', 'imagejpeg', 90],
            'image/png' => ['imagecreatefrompng', 'imagepng', 6],
            'image/webp' => ['imagecreatefromwebp', 'imagewebp', 90],
            'image/gif' => ['imagecreatefromgif', 'imagegif', null],
        ];

        if (!isset($functions[$mime]) || ($mime === 'image/gif' && self::isAnimatedGif($path))) {
            return false;
        }

        [$read, $write, $quality] = $functions[$mime];

        if (!function_exists($read) || !function_exists($write)) {
            return false;
        }

        $image = @$read($path);

        if ($image === false) {
            throw new InvalidArgumentException('Bild konnte nicht gelesen werden.');
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);
        $target = $path . '.reencode';
        $ok = $quality === null ? $write($image, $target) : $write($image, $target, $quality);

        if (!$ok || !rename($target, $path)) {
            @unlink($target);

            throw new RuntimeException('Bild konnte nicht neu kodiert werden.');
        }

        return true;
    }

    /**
     * @return array{0: int, 1: int}
     */
    private static function imageSize(string $path, string $mime): array
    {
        $size = @getimagesize($path);

        if ($size === false || ($size['mime'] ?? '') !== $mime || $size[0] < 1 || $size[1] < 1) {
            throw new InvalidArgumentException('Datei ist kein gültiges Bild.');
        }

        if ($size[0] * $size[1] > self::MAX_PIXELS) {
            throw new InvalidArgumentException('Bild ist zu groß (maximal ' . (self::MAX_PIXELS / 1_000_000) . ' Megapixel).');
        }

        return [(int) $size[0], (int) $size[1]];
    }

    private static function containsCode(string $path): bool
    {
        return preg_match('/<\?php|<\?=|<script/i', (string) file_get_contents($path)) === 1;
    }

    private static function isAnimatedGif(string $path): bool
    {
        return substr_count((string) file_get_contents($path), "\x00\x21\xF9\x04") > 1;
    }

    private static function cleanName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = trim((string) preg_replace('/[\x00-\x1F\x7F<>"]/u', '', $name));

        return mb_substr($name !== '' ? $name : 'datei', -self::MAX_NAME_LENGTH);
    }
}
