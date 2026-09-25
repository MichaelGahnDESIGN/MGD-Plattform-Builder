<?php

declare(strict_types=1);

namespace MGD\Starter\Media;

use InvalidArgumentException;
use RuntimeException;
use Throwable;

/**
 * Speichert geprüfte Uploads unter paths.uploads/YYYY/MM/<zufall>.<ext> (öffentlich /uploads/YYYY/MM/…).
 * Prüfung: MediaValidator. Bilder werden optional per GD neu kodiert (entfernt EXIF/Anhängsel).
 */
final class MediaStore
{
    public const DEFAULT_MAX_BYTES = MediaValidator::DEFAULT_MAX_BYTES;
    public const PUBLIC_PREFIX = '/uploads';

    private readonly MediaValidator $validator;

    public function __construct(
        private readonly MediaRepository $repository,
        private readonly string $uploadsDirectory,
        int $maxBytes = self::DEFAULT_MAX_BYTES,
        private readonly bool $reencodeImages = true,
    ) {
        $this->validator = new MediaValidator($maxBytes);
    }

    /**
     * @param array{tmp_name?: string, name?: string, size?: int, error?: int} $file Eintrag aus $_FILES
     */
    public function storeUpload(array $file, ?int $uploadedBy): int
    {
        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new InvalidArgumentException(self::uploadError((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE)));
        }

        $tmp = (string) ($file['tmp_name'] ?? '');

        if ($tmp === '' || !is_uploaded_file($tmp)) {
            throw new InvalidArgumentException('Ungültiger Upload.');
        }

        return $this->persist($tmp, $this->validator->validate($tmp, (string) ($file['name'] ?? '')), $uploadedBy);
    }

    public function delete(array $media): void
    {
        $relative = substr((string) $media['path'], strlen(self::PUBLIC_PREFIX));

        if (preg_match('#^/\d{4}/\d{2}/[a-f0-9]{32}\.[a-z]{3,4}$#', $relative) !== 1) {
            throw new InvalidArgumentException('Ungültiger Medienpfad.');
        }

        $file = rtrim($this->uploadsDirectory, '/') . $relative;

        if (is_file($file) && !unlink($file)) {
            throw new RuntimeException('Datei konnte nicht gelöscht werden.');
        }

        $this->repository->delete((int) $media['id']);
    }

    private function persist(string $source, array $info, ?int $uploadedBy): int
    {
        $subdir = gmdate('Y') . '/' . gmdate('m');
        $directory = rtrim($this->uploadsDirectory, '/') . '/' . $subdir;

        if (!is_dir($directory) && !mkdir($directory, 0755, true) && !is_dir($directory)) {
            throw new RuntimeException('Upload-Verzeichnis konnte nicht angelegt werden.');
        }

        $fileName = bin2hex(random_bytes(16)) . '.' . $info['extension'];
        $target = $directory . '/' . $fileName;

        if (!move_uploaded_file($source, $target)) {
            throw new RuntimeException('Datei konnte nicht gespeichert werden.');
        }

        @chmod($target, 0644);

        try {
            $metadataFormats = ['image/jpeg', 'image/png', 'image/webp'];

            if ($this->reencodeImages && $info['mime'] !== 'application/pdf'
                && !MediaValidator::reencode($target, $info['mime']) && in_array($info['mime'], $metadataFormats, true)) {
                // Zusage "ohne Metadaten" nicht still brechen: ohne GD wird das Bild abgelehnt.
                throw new InvalidArgumentException('Metadaten (z. B. EXIF/GPS) konnten nicht entfernt werden, weil die PHP-Erweiterung GD fehlt. '
                    . 'Bitte GD aktivieren oder media.reencode_images in config.php bewusst auf false setzen.');
            }

            clearstatcache(true, $target);

            return $this->repository->create([
                'path' => self::PUBLIC_PREFIX . '/' . $subdir . '/' . $fileName,
                'original_name' => $info['original_name'],
                'mime' => $info['mime'],
                'size_bytes' => (int) filesize($target),
                'width' => $info['width'],
                'height' => $info['height'],
            ], $uploadedBy);
        } catch (Throwable $exception) {
            @unlink($target);

            throw $exception;
        }
    }

    private static function uploadError(int $code): string
    {
        return match ($code) {
            UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => 'Datei ist zu groß (Server-Limit).',
            UPLOAD_ERR_PARTIAL => 'Datei wurde nur teilweise hochgeladen.',
            UPLOAD_ERR_NO_FILE => 'Keine Datei ausgewählt.',
            default => 'Upload fehlgeschlagen (Code ' . $code . ').',
        };
    }
}
