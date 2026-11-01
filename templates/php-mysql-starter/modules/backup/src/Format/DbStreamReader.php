<?php

declare(strict_types=1);

namespace MGD\Backup\Format;

use Generator;
use JsonException;

/**
 * Liest den Klartext des Teils "db" (gzip-NDJSON, siehe DbStreamWriter) streamend und pruefend.
 *
 * Eingabe: die entschluesselten Chunks (z. B. PartReader::chunks()). Ereignisse:
 *   ['type'=>'table','table'=>string,'columns'=>list<string>]
 *   ['type'=>'row','values'=>list<?string>]            ($b64-Werte sind bereits zu Bytes dekodiert)
 *   ['type'=>'end','table'=>string,'rows'=>int]
 * Geprueft werden: gzip vollstaendig (Ende erreicht, keine angehaengten Daten), Zeilenlaenge, Reihenfolge, Spaltenzahl,
 * Zeilenzahl n je Tabellenende, keine offene Tabelle am Ende.
 */
final class DbStreamReader
{
    /** @param  iterable<string>  $chunks */
    public function __construct(private readonly iterable $chunks) {}

    /** @return Generator<int, array<string,mixed>> */
    public function events(): Generator
    {
        $context = inflate_init(ZLIB_ENCODING_GZIP);
        if ($context === false) {
            throw new BackupFormatException(BackupFormatException::BAD_DB_STREAM);
        }

        $buffer = '';
        $table = null;
        $columnCount = 0;
        $rows = 0;
        $ended = false;
        $inputBytes = 0;

        foreach ($this->chunks as $chunk) {
            if ($chunk === '') {
                continue;
            }
            $inputBytes += strlen($chunk);
            if ($ended) {
                throw new BackupFormatException(BackupFormatException::BAD_DB_STREAM);
            }
            $plain = @inflate_add($context, $chunk, ZLIB_SYNC_FLUSH);
            if ($plain === false) {
                throw new BackupFormatException(BackupFormatException::BAD_DB_STREAM);
            }
            $ended = inflate_get_status($context) === ZLIB_STREAM_END;
            $buffer .= $plain;

            $pos = 0;
            while (($nl = strpos($buffer, "\n", $pos)) !== false) {
                $event = $this->parse(substr($buffer, $pos, $nl - $pos), $table, $columnCount, $rows);
                $pos = $nl + 1;
                yield $event;
            }
            $buffer = (string) substr($buffer, $pos);
            if (strlen($buffer) > BackupFormat::MAX_DB_LINE_BYTES) {
                throw new BackupFormatException(BackupFormatException::BAD_DB_STREAM);
            }
        }

        if (! $ended || $buffer !== '' || $table !== null || inflate_get_read_len($context) !== $inputBytes) {
            throw new BackupFormatException(BackupFormatException::BAD_DB_STREAM);
        }
    }

    /** @return array<string,mixed> */
    private function parse(string $line, ?string &$table, int &$columnCount, int &$rows): array
    {
        try {
            $data = json_decode($line, true, 8, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            throw new BackupFormatException(BackupFormatException::BAD_DB_STREAM);
        }
        if (! is_array($data) || count($data) < 1 || count($data) > 2) {
            throw new BackupFormatException(BackupFormatException::BAD_DB_STREAM);
        }

        if (array_key_exists('t', $data)) {
            $columns = $data['c'] ?? null;
            if ($table !== null || count($data) !== 2 || ! is_string($data['t']) || ! self::validName($data['t'])
                || ! is_array($columns) || $columns === [] || ! array_is_list($columns)) {
                throw new BackupFormatException(BackupFormatException::BAD_DB_STREAM);
            }
            foreach ($columns as $column) {
                if (! is_string($column) || ! self::validName($column)) {
                    throw new BackupFormatException(BackupFormatException::BAD_DB_STREAM);
                }
            }
            $table = $data['t'];
            $columnCount = count($columns);
            $rows = 0;

            return ['type' => 'table', 'table' => $table, 'columns' => $columns];
        }

        if (array_key_exists('r', $data)) {
            $values = $data['r'];
            if ($table === null || count($data) !== 1 || ! is_array($values) || ! array_is_list($values) || count($values) !== $columnCount) {
                throw new BackupFormatException(BackupFormatException::BAD_DB_STREAM);
            }
            foreach ($values as $i => $value) {
                if ($value === null || is_string($value)) {
                    continue;
                }
                if (is_array($value) && array_keys($value) === ['$b64'] && is_string($value['$b64'])) {
                    $bytes = base64_decode($value['$b64'], true);
                    if ($bytes === false) {
                        throw new BackupFormatException(BackupFormatException::BAD_DB_STREAM);
                    }
                    $values[$i] = $bytes;

                    continue;
                }
                throw new BackupFormatException(BackupFormatException::BAD_DB_STREAM);
            }
            $rows++;

            return ['type' => 'row', 'values' => $values];
        }

        if (array_key_exists('e', $data)) {
            if ($table === null || count($data) !== 2 || $data['e'] !== $table || ! is_int($data['n'] ?? null) || $data['n'] !== $rows) {
                throw new BackupFormatException(BackupFormatException::BAD_DB_STREAM);
            }
            $name = $table;
            $table = null;

            return ['type' => 'end', 'table' => $name, 'rows' => $rows];
        }

        throw new BackupFormatException(BackupFormatException::BAD_DB_STREAM);
    }

    private static function validName(string $name): bool
    {
        return preg_match('/^[A-Za-z0-9_]{1,64}$/D', $name) === 1;
    }
}
