<?php

declare(strict_types=1);

namespace MGD\Backup;

use MGD\Backup\Format\BackupFormat;

/**
 * Lokale Ablage der Sicherungen: <root>/<ordner>/<teil>.mgdb (Ordner 0700, Dateien 0600). Der Root liegt unter storage/
 * (per .htaccess gesperrt) und ist bewusst KEIN gesicherter Bereich. Pfade entstehen nur aus Datenbankwerten, die gegen
 * die Namens-Regexe geprueft werden; assertInside() verhindert Pfadtricks ('..', Symlinks, fremde Namen).
 */
final class Paths
{
    public const INDEX_NAME = 'files.idx';

    public function __construct(private readonly string $root)
    {
        if ($root === '' || $root[0] !== '/' && preg_match('#^[A-Za-z]:[\\\\/]#', $root) !== 1) {
            throw new BackupException('path_invalid');
        }
    }

    public function root(): string
    {
        return rtrim($this->root, '/');
    }

    /** Legt den Wurzelordner (0700) bei Bedarf an. */
    public function ensureRoot(): string
    {
        $root = $this->root();
        if (!is_dir($root)) {
            $old = umask(0077);
            $created = @mkdir($root, 0700, true);
            umask($old);
            if (!$created && !is_dir($root)) {
                throw new BackupException('io_error');
            }
        }

        return $root;
    }

    /** Ordnername eines Laufs: mgdbk_<JJJJMMTT>T<HHMMSS>Z_<kind>_<uuid8>; $createdUtc ist 'Y-m-d H:i:s' in UTC. */
    public static function folderName(string $createdUtc, string $kind, string $uuid): string
    {
        $time = \DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $createdUtc, new \DateTimeZone('UTC'));
        $name = $time === false ? '' : sprintf('mgdbk_%sZ_%s_%s', $time->format('Ymd\THis'), $kind, substr(str_replace('-', '', $uuid), 0, 8));

        if (!self::isFolderName($name)) {
            throw new BackupException('path_invalid');
        }

        return $name;
    }

    /** Dateiname einer Teildatei: <ordner>_<seq3>_<teilart>.mgdb. */
    public static function partFileName(string $folder, int $seq, string $kind): string
    {
        $name = sprintf('%s_%03d_%s.mgdb', $folder, $seq, $kind);

        if (!self::isFolderName($folder) || !self::isPartName($name)) {
            throw new BackupException('path_invalid');
        }

        return $name;
    }

    public static function isFolderName(string $name): bool
    {
        return preg_match(BackupFormat::FOLDER_NAME_REGEX, $name) === 1;
    }

    public static function isPartName(string $name): bool
    {
        return preg_match(BackupFormat::PART_NAME_REGEX, $name) === 1;
    }

    /** Ordnername zu einem Teildateinamen oder null. */
    public static function folderOfPart(string $partName): ?string
    {
        if (!self::isPartName($partName)) {
            return null;
        }

        return (string) preg_replace('/_\d{3}_(manifest|db|files|keys)\.mgdb$/D', '', $partName);
    }

    public function runDir(string $folder): string
    {
        if (!self::isFolderName($folder)) {
            throw new BackupException('path_invalid');
        }

        return $this->root() . '/' . $folder;
    }

    public function partPath(string $folder, string $partName): string
    {
        if (!self::isPartName($partName) || !str_starts_with($partName, $folder . '_')) {
            throw new BackupException('path_invalid');
        }

        return $this->runDir($folder) . '/' . $partName;
    }

    public function indexPath(string $folder): string
    {
        return $this->runDir($folder) . '/' . self::INDEX_NAME;
    }

    /**
     * Prueft, dass der Pfad ein erlaubtes Ziel unter root() ist: lexikalisch ohne '..', genau <ordner> oder
     * <ordner>/<erlaubter Dateiname>, kein Symlink in irgendeiner Stufe, und (falls vorhanden) realpath im Root.
     *
     * @return string der unveraenderte Pfad
     */
    public function assertInside(string $path): string
    {
        $root = $this->root();

        if ($path === '' || str_contains($path, "\0") || preg_match('#(^|/)\.\.?(/|$)#', $path) === 1
            || !str_starts_with($path, $root . '/')) {
            throw new BackupException('path_invalid');
        }

        $relative = substr($path, strlen($root) + 1);
        $segments = explode('/', $relative);

        if (count($segments) > 2 || in_array('', $segments, true) || !self::isFolderName($segments[0])) {
            throw new BackupException('path_invalid');
        }

        if (isset($segments[1])) {
            $isPart = self::isPartName($segments[1]) && str_starts_with($segments[1], $segments[0] . '_');

            if (!$isPart && $segments[1] !== self::INDEX_NAME) {
                throw new BackupException('path_invalid');
            }
        }

        // Keine Symlinks unterhalb des Roots (der Root selbst darf auf dem Hoster ein Symlink sein).
        $current = $root;

        foreach ($segments as $segment) {
            $current .= '/' . $segment;

            if (is_link($current)) {
                throw new BackupException('path_invalid');
            }

            if (!file_exists($current)) {
                break;
            }
        }

        if (file_exists($path)) {
            $rootReal = realpath($root);
            $real = realpath($path);

            if ($rootReal === false || $real === false || $real !== $rootReal . '/' . $relative) {
                throw new BackupException('path_invalid');
            }
        }

        return $path;
    }

    /** Legt den Ordner des Laufs an (0700). */
    public function ensureRunDir(string $folder): string
    {
        $this->ensureRoot();
        $dir = $this->assertInside($this->runDir($folder));

        if (!is_dir($dir)) {
            $old = umask(0077);
            $created = @mkdir($dir, 0700);
            umask($old);

            if (!$created && !is_dir($dir)) {
                throw new BackupException('io_error');
            }
        }

        return $this->assertInside($dir);
    }

    /**
     * Loescht den Ordner eines Laufs: nur regulaere Dateien mit erlaubten Namen (Teile, files.idx), dann den Ordner.
     * Fremde Dateien bleiben liegen (der Ordner bleibt dann bestehen). Gibt true zurueck, wenn der Ordner danach fehlt.
     */
    public function deleteFolder(string $folder): bool
    {
        $dir = $this->assertInside($this->runDir($folder));

        if (!is_dir($dir)) {
            return true;
        }

        foreach (scandir($dir) ?: [] as $entry) {
            if ($entry === '.' || $entry === '..') {
                continue;
            }

            $allowed = $entry === self::INDEX_NAME || (self::isPartName($entry) && str_starts_with($entry, $folder . '_'));
            $file = $dir . '/' . $entry;

            if ($allowed && is_file($file) && !is_link($file)) {
                @unlink($file);
            }
        }

        return @rmdir($dir) || !file_exists($dir);
    }

    /** Loescht eine einzelne Teildatei (nur mit gueltigem Namen im Lauf-Ordner). */
    public function deleteFile(string $path): void
    {
        $this->assertInside($path);

        if (is_file($path) && !is_link($path)) {
            @unlink($path);
        }
    }

    /**
     * Ordner mit mgdbk_-Namen im Root (fuer die Aufraeumhilfe). Symlinks und fremde Namen werden ignoriert.
     *
     * @return list<string>
     */
    public function folders(): array
    {
        $root = $this->root();

        if (!is_dir($root)) {
            return [];
        }

        $names = [];

        foreach (scandir($root) ?: [] as $entry) {
            if (self::isFolderName($entry) && is_dir($root . '/' . $entry) && !is_link($root . '/' . $entry)) {
                $names[] = $entry;
            }
        }

        return $names;
    }
}
