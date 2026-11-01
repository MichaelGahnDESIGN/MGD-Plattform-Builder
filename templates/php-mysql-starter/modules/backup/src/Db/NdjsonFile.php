<?php

declare(strict_types=1);

namespace MGD\Backup\Db;

use Generator;
use JsonException;
use MGD\Backup\BackupException;
use MGD\Backup\Format\BackupFormat;

/**
 * Liest eine entschluesselte db.ndjson.gz (Ergebnis von `mgd-backup.php decrypt`) als Ereignisfolge im Format von
 * Format\DbStreamReader. Die Datei darf aus mehreren aneinandergehaengten gzip-Strömen (einer je DB-Teil) bestehen;
 * zlib liest solche Dateien am Stueck. Geprueft werden Zeilenlaenge, Reihenfolge, Spaltenzahl und die Zeilenzahl je
 * Tabellenende.
 */
final class NdjsonFile
{
    private const NAME_REGEX = '/^[A-Za-z0-9_]{1,64}$/D';

    private function __construct() {}

    /** @return Generator<int, array<string,mixed>> */
    public static function events(string $path): Generator
    {
        if (!is_file($path) || is_link($path) || !function_exists('gzopen')) {
            throw new BackupException('io_error');
        }

        $handle = @gzopen($path, 'rb');

        if ($handle === false) {
            throw new BackupException('io_error');
        }

        $table = null;
        $columns = [];
        $rows = 0;

        try {
            foreach (self::lines($handle) as $line) {
                if ($line === '') {
                    continue;
                }

                try {
                    $event = json_decode($line, true, 8, JSON_THROW_ON_ERROR);
                } catch (JsonException) {
                    throw new BackupException('format_error');
                }

                if (!is_array($event)) {
                    throw new BackupException('format_error');
                }

                if (isset($event['t'])) {
                    if ($table !== null || !is_string($event['t']) || preg_match(self::NAME_REGEX, $event['t']) !== 1
                        || !isset($event['c']) || !is_array($event['c']) || $event['c'] === []) {
                        throw new BackupException('format_error');
                    }

                    foreach ($event['c'] as $column) {
                        if (!is_string($column) || preg_match(self::NAME_REGEX, $column) !== 1) {
                            throw new BackupException('format_error');
                        }
                    }

                    $table = $event['t'];
                    $columns = array_values($event['c']);
                    $rows = 0;

                    yield ['type' => 'table', 'table' => $table, 'columns' => $columns];
                } elseif (isset($event['r'])) {
                    if ($table === null || !is_array($event['r']) || count($event['r']) !== count($columns)) {
                        throw new BackupException('format_error');
                    }

                    $values = [];

                    foreach ($event['r'] as $value) {
                        $values[] = self::decode($value);
                    }

                    $rows++;

                    yield ['type' => 'row', 'values' => $values];
                } elseif (isset($event['e'])) {
                    if ($table === null || $event['e'] !== $table || ($event['n'] ?? null) !== $rows) {
                        throw new BackupException('format_error');
                    }

                    yield ['type' => 'end', 'table' => $table, 'rows' => $rows];

                    $table = null;
                } else {
                    throw new BackupException('format_error');
                }
            }

            if ($table !== null) {
                throw new BackupException('format_error');
            }
        } finally {
            gzclose($handle);
        }
    }

    /**
     * Zeilen aus einem gz-Handle (ohne Zeilenende). Eine unvollstaendige letzte Zeile oder eine zu lange Zeile ist ein Fehler.
     *
     * @param resource $handle
     * @return Generator<int, string>
     */
    private static function lines($handle): Generator
    {
        $buffer = '';

        while (true) {
            $chunk = gzread($handle, 65536);

            if ($chunk === false) {
                throw new BackupException('io_error');
            }

            if ($chunk === '') {
                if ($buffer !== '') {
                    throw new BackupException('format_error'); // letzte Zeile ohne Zeilenende: abgeschnitten
                }

                return;
            }

            $buffer .= $chunk;

            while (($position = strpos($buffer, "\n")) !== false) {
                yield substr($buffer, 0, $position);
                $buffer = (string) substr($buffer, $position + 1);
            }

            if (strlen($buffer) > BackupFormat::MAX_DB_LINE_BYTES) {
                throw new BackupException('format_error');
            }
        }
    }

    private static function decode(mixed $value): ?string
    {
        if ($value === null || is_string($value)) {
            return $value;
        }

        if (is_array($value) && count($value) === 1 && is_string($value['$b64'] ?? null)) {
            $bytes = base64_decode($value['$b64'], true);

            if ($bytes !== false) {
                return $bytes;
            }
        }

        throw new BackupException('format_error');
    }
}
