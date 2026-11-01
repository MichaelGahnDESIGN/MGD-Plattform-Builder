<?php

declare(strict_types=1);

namespace MGD\Backup\Format;

use Generator;

/**
 * Liest die Datensaetze des Teils "files" (siehe FileRecordWriter) streamend aus den entschluesselten Chunks.
 *
 * Jeder Pfad wird gegen BackupFormat::FILE_PATH_REGEX geprueft (Zip-Slip-Schutz: kein '..', kein absoluter Pfad,
 * kein NUL), bevor der Datensatz weitergegeben wird. Ergebnisse:
 *   ['type'=>1,'path'=>string,'total'=>int,'offset'=>int,'data'=>string]
 *   ['type'=>2,'path'=>string,'sha256'=>hex]
 * Ein Datensatz, der mitten im Strom abbricht, ist ein Fehler (bad_file_record). Ob Dateien vollstaendig sind und die
 * SHA-256-Summe passt, prueft FileRecordAssembler.
 */
final class FileRecordReader
{
    private Generator $source;

    private string $buffer = '';

    private int $pos = 0;

    /** @param  iterable<string>  $chunks */
    public function __construct(iterable $chunks)
    {
        $this->source = (static function () use ($chunks): Generator {
            yield from $chunks;
        })();
    }

    /** @return Generator<int, array<string,mixed>> */
    public function records(): Generator
    {
        while (true) {
            if (! $this->fill(1)) {
                return; // sauberes Ende an einer Datensatzgrenze
            }
            $type = ord($this->take(1));
            if ($type !== 1 && $type !== 2) {
                throw new BackupFormatException(BackupFormatException::BAD_FILE_RECORD);
            }

            $pathLength = unpack('n', $this->need(2))[1];
            if ($pathLength < 1) {
                throw new BackupFormatException(BackupFormatException::BAD_PATH);
            }
            $path = $this->need($pathLength);
            if (! BackupFormat::isSafeFilePath($path)) {
                throw new BackupFormatException(BackupFormatException::BAD_PATH);
            }

            if ($type === 2) {
                yield ['type' => 2, 'path' => $path, 'sha256' => bin2hex($this->need(32))];

                continue;
            }

            $fields = unpack('Jtotal/Joffset/Nlength', $this->need(20));
            if ($fields === false || $fields['total'] < 0 || $fields['offset'] < 0
                || $fields['length'] > BackupFormat::MAX_SEGMENT_BYTES
                || $fields['total'] < $fields['offset'] + $fields['length']) {
                throw new BackupFormatException(BackupFormatException::BAD_FILE_RECORD);
            }
            $data = $fields['length'] > 0 ? $this->need($fields['length']) : '';

            yield ['type' => 1, 'path' => $path, 'total' => $fields['total'], 'offset' => $fields['offset'], 'data' => $data];
        }
    }

    /** Stellt sicher, dass mindestens $length ungelesene Bytes im Puffer liegen; false bei Ende des Stroms. */
    private function fill(int $length): bool
    {
        if ($this->pos > 0) {
            $this->buffer = (string) substr($this->buffer, $this->pos);
            $this->pos = 0;
        }
        while (strlen($this->buffer) < $length) {
            if (! $this->source->valid()) {
                return false;
            }
            $this->buffer .= $this->source->current();
            $this->source->next();
        }

        return true;
    }

    private function take(int $length): string
    {
        $bytes = substr($this->buffer, $this->pos, $length);
        $this->pos += $length;

        return $bytes;
    }

    /** Wie take(), aber das Stromende mitten im Datensatz ist ein Fehler. */
    private function need(int $length): string
    {
        if (! $this->fill($length)) {
            throw new BackupFormatException(BackupFormatException::BAD_FILE_RECORD);
        }

        return $this->take($length);
    }
}
