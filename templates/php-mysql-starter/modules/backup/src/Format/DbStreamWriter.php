<?php

declare(strict_types=1);

namespace MGD\Backup\Format;

use Closure;
use DeflateContext;
use JsonException;

/**
 * Schreibt den Klartext des Teils "db": gzip-komprimiertes NDJSON, eine Zeile je Ereignis.
 *   {"t":"users","c":["id","name"]}   Tabellenanfang mit Spalten
 *   {"r":["1","Anna"]}                 Zeile: Werte in Spaltenreihenfolge
 *   {"e":"users","n":123}              Tabellenende; n = Zeilen zwischen Anfang und Ende in DIESEM Strom
 * Werte: null, String oder {"$b64":"..."} fuer ungueltiges UTF-8. Zahlen und Bool werden als String geschrieben.
 * Eine Tabelle darf ueber mehrere Teile laufen (je Teil ein eigener Strom mit eigenem Anfang und Ende).
 *
 * Die gzip-Ausgabe geht streamend an die Senke (z. B. PartWriter::write(...)); es wird nichts im Speicher gesammelt.
 */
final class DbStreamWriter
{
    private const FLUSH_BYTES = 65536;

    private const NAME_REGEX = '/^[A-Za-z0-9_]{1,64}$/D';

    private const JSON_FLAGS = JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR;

    private DeflateContext $deflate;

    private string $pending = '';

    private ?string $table = null;

    /** @var list<string> */
    private array $columns = [];

    private int $rows = 0;

    private bool $finished = false;

    /** @param  Closure(string):void  $sink  bekommt die gzip-Bytes in Reihenfolge */
    public function __construct(private readonly Closure $sink)
    {
        $context = deflate_init(ZLIB_ENCODING_GZIP, ['level' => 6]);
        if ($context === false) {
            throw new BackupFormatException(BackupFormatException::BAD_DB_STREAM);
        }
        $this->deflate = $context;
    }

    /** @param  list<string>  $columns */
    public function beginTable(string $table, array $columns): void
    {
        $this->assertOpen();
        if ($this->table !== null || preg_match(self::NAME_REGEX, $table) !== 1 || $columns === [] || ! array_is_list($columns)) {
            throw new BackupFormatException(BackupFormatException::BAD_DB_STREAM);
        }
        foreach ($columns as $column) {
            if (! is_string($column) || preg_match(self::NAME_REGEX, $column) !== 1) {
                throw new BackupFormatException(BackupFormatException::BAD_DB_STREAM);
            }
        }
        $this->table = $table;
        $this->columns = $columns;
        $this->rows = 0;
        $this->line(['t' => $table, 'c' => $columns]);
    }

    /** @param  list<mixed>  $values  in Spaltenreihenfolge */
    public function row(array $values): void
    {
        $this->assertOpen();
        if ($this->table === null || ! array_is_list($values) || count($values) !== count($this->columns)) {
            throw new BackupFormatException(BackupFormatException::BAD_DB_STREAM);
        }
        $out = [];
        foreach ($values as $value) {
            $out[] = self::encodeValue($value);
        }
        $this->rows++;
        $this->line(['r' => $out]);
    }

    /** Schliesst die Tabelle (in diesem Strom) und liefert die Zahl der Zeilen. */
    public function endTable(): int
    {
        $this->assertOpen();
        if ($this->table === null) {
            throw new BackupFormatException(BackupFormatException::BAD_DB_STREAM);
        }
        $this->line(['e' => $this->table, 'n' => $this->rows]);
        $count = $this->rows;
        $this->table = null;
        $this->columns = [];
        $this->rows = 0;

        return $count;
    }

    /** Beendet den gzip-Strom. Danach ist der Writer unbrauchbar. */
    public function finish(): void
    {
        $this->assertOpen();
        if ($this->table !== null) {
            throw new BackupFormatException(BackupFormatException::BAD_DB_STREAM);
        }
        $this->finished = true;
        $tail = deflate_add($this->deflate, $this->pending, ZLIB_FINISH);
        $this->pending = '';
        if ($tail === false) {
            throw new BackupFormatException(BackupFormatException::BAD_DB_STREAM);
        }
        if ($tail !== '') {
            ($this->sink)($tail);
        }
    }

    /** Wert fuer JSON: null, String oder {"$b64": ...}. */
    private static function encodeValue(mixed $value): mixed
    {
        if ($value === null) {
            return null;
        }
        if (is_resource($value)) {
            $value = stream_get_contents($value);
        }
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }
        if (! is_string($value)) {
            throw new BackupFormatException(BackupFormatException::BAD_DB_STREAM);
        }

        return preg_match('//u', $value) === 1 ? $value : ['$b64' => base64_encode($value)];
    }

    /** @param  array<string,mixed>  $event */
    private function line(array $event): void
    {
        try {
            $this->pending .= json_encode($event, self::JSON_FLAGS)."\n";
        } catch (JsonException) {
            throw new BackupFormatException(BackupFormatException::BAD_DB_STREAM);
        }
        if (strlen($this->pending) >= self::FLUSH_BYTES) {
            $compressed = deflate_add($this->deflate, $this->pending, ZLIB_NO_FLUSH);
            $this->pending = '';
            if ($compressed === false) {
                throw new BackupFormatException(BackupFormatException::BAD_DB_STREAM);
            }
            if ($compressed !== '') {
                ($this->sink)($compressed);
            }
        }
    }

    private function assertOpen(): void
    {
        if ($this->finished) {
            throw new BackupFormatException(BackupFormatException::ALREADY_FINISHED);
        }
    }
}
