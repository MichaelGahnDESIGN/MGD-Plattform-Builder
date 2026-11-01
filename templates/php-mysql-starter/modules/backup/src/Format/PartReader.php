<?php

declare(strict_types=1);

namespace MGD\Backup\Format;

use Generator;
use Throwable;

/**
 * Liest und entschluesselt eine Teildatei im Format MGDB1 streamend (Block fuer Block).
 *
 * Erkannt werden: unbekannte Kennung/Version, falscher Schluessel (kein passender Empfaenger), gekippte Bits in Kopf,
 * secretstream-Header und Bloecken (AAD = SHA-256 ueber den Vorspann), Abschneiden (kein Block mit TAG_FINAL),
 * angehaengte Bytes nach FINAL. Fehler sind immer BackupFormatException mit festem Code, nie mit Dateiinhalt.
 * Klartext von Bloecken, die vor dem Fehler gelesen wurden, kann der Aufrufer bereits erhalten haben: Ergebnisse erst
 * nach vollstaendigem Durchlauf ohne Ausnahme als gueltig behandeln.
 */
final class PartReader
{
    /** @var resource|null */
    private $handle;

    private bool $consumed = false;

    /**
     * @param  array{version:int,kind:int,run_uuid:string,run_uuid_hex:string,seq:int,recipients:list<string>,head_bytes:int}  $header
     */
    private function __construct(
        $handle,
        private readonly array $header,
        private string $state,
        private readonly string $aad,
    ) {
        $this->handle = $handle;
    }

    /**
     * Kopf ohne Schluessel lesen (fuer `info`). Prueft Kennung, Version, Teilart und Empfaengerzahl.
     *
     * @return array{version:int,kind:int,run_uuid:string,run_uuid_hex:string,seq:int,recipients:list<string>,head_bytes:int}
     */
    public static function readHeader(string $path): array
    {
        $handle = self::openFile($path);
        try {
            return self::parseHead($handle)['header'];
        } finally {
            fclose($handle);
        }
    }

    /**
     * @param  string  $secretKey  32 Byte X25519 (privater Schluessel)
     * @param  string|null  $expectedRunUuid  16 Byte binaer; weicht der Kopf ab: Fehler run_mismatch
     */
    public static function open(string $path, string $secretKey, ?string $expectedRunUuid = null): self
    {
        if (strlen($secretKey) !== BackupFormat::SECRET_KEY_BYTES) {
            throw new BackupFormatException(BackupFormatException::BAD_KEY);
        }

        return self::fromHandle(self::openFile($path), $secretKey, $expectedRunUuid);
    }

    /**
     * Wie open(), aber ueber ein bereits geoeffnetes Handle (Position 0, Lesemodus). Der Reader uebernimmt das Handle und
     * schliesst es (auch bei einem Fehler). Der Restore prueft Groesse und SHA-256 ueber dasselbe Handle, damit zwischen Pruefung
     * und Lesen kein anderer Dateiinhalt untergeschoben werden kann.
     *
     * @param  resource  $handle
     */
    public static function fromHandle($handle, string $secretKey, ?string $expectedRunUuid = null): self
    {
        if (strlen($secretKey) !== BackupFormat::SECRET_KEY_BYTES) {
            fclose($handle);
            throw new BackupFormatException(BackupFormatException::BAD_KEY);
        }

        try {
            $head = self::parseHead($handle);
            if ($expectedRunUuid !== null && ! hash_equals($expectedRunUuid, $head['header']['run_uuid'])) {
                throw new BackupFormatException(BackupFormatException::RUN_MISMATCH);
            }

            $publicKey = sodium_crypto_box_publickey_from_secretkey($secretKey);
            $fingerprint = bin2hex(BackupFormat::fingerprintRaw($publicKey));
            $sealed = $head['sealed'][$fingerprint] ?? null;
            if ($sealed === null) {
                throw new BackupFormatException(BackupFormatException::NOT_A_RECIPIENT);
            }

            $keyPair = sodium_crypto_box_keypair_from_secretkey_and_publickey($secretKey, $publicKey);
            $key = sodium_crypto_box_seal_open($sealed, $keyPair);
            sodium_memzero($keyPair);
            if ($key === false || strlen($key) !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_KEYBYTES) {
                throw new BackupFormatException(BackupFormatException::KEY_UNSEAL_FAILED);
            }
            try {
                $state = sodium_crypto_secretstream_xchacha20poly1305_init_pull($head['stream_header'], $key);
            } catch (Throwable) {
                throw new BackupFormatException(BackupFormatException::BAD_HEADER);
            } finally {
                sodium_memzero($key);
            }
        } catch (Throwable $e) {
            fclose($handle);
            throw $e instanceof BackupFormatException ? $e : new BackupFormatException(BackupFormatException::BAD_HEADER);
        }

        return new self($handle, $head['header'], $state, hash('sha256', $head['raw'], true));
    }

    /** @return array{version:int,kind:int,run_uuid:string,run_uuid_hex:string,seq:int,recipients:list<string>,head_bytes:int} */
    public function header(): array
    {
        return $this->header;
    }

    /**
     * Entschluesselt streamend. Wirft bei Manipulation, Abschneiden oder angehaengten Bytes.
     * Darf nur einmal durchlaufen werden; schliesst die Datei am Ende (auch bei Fehler).
     *
     * @return Generator<int, string>
     */
    public function chunks(): Generator
    {
        if ($this->consumed || ! is_resource($this->handle)) {
            throw new BackupFormatException(BackupFormatException::ALREADY_CONSUMED);
        }
        $this->consumed = true;
        $handle = $this->handle;
        $max = BackupFormat::BLOCK_SIZE + BackupFormat::BLOCK_OVERHEAD;

        try {
            while (true) {
                $lenBytes = self::readExactly($handle, 4);
                if ($lenBytes === null) {
                    throw new BackupFormatException(BackupFormatException::TRUNCATED);
                }
                $len = unpack('N', $lenBytes)[1];
                if ($len < BackupFormat::BLOCK_OVERHEAD || $len > $max) {
                    throw new BackupFormatException(BackupFormatException::CORRUPT_BLOCK);
                }
                $cipher = self::readExactly($handle, $len);
                if ($cipher === null) {
                    throw new BackupFormatException(BackupFormatException::TRUNCATED);
                }

                try {
                    $result = sodium_crypto_secretstream_xchacha20poly1305_pull($this->state, $cipher, $this->aad);
                } catch (Throwable) {
                    $result = false;
                }
                if ($result === false) {
                    throw new BackupFormatException(BackupFormatException::CORRUPT_BLOCK);
                }
                [$plain, $tag] = $result;

                if ($tag === SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL) {
                    if (strlen($plain) > BackupFormat::BLOCK_SIZE) {
                        throw new BackupFormatException(BackupFormatException::CORRUPT_BLOCK);
                    }
                    $extra = fread($handle, 1);
                    if ($extra !== false && $extra !== '') {
                        throw new BackupFormatException(BackupFormatException::TRAILING_DATA);
                    }
                    yield $plain;

                    return;
                }
                if ($tag !== SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE || strlen($plain) !== BackupFormat::BLOCK_SIZE) {
                    throw new BackupFormatException(BackupFormatException::CORRUPT_BLOCK);
                }
                yield $plain;
            }
        } finally {
            $this->close();
        }
    }

    public function close(): void
    {
        if (is_resource($this->handle)) {
            fclose($this->handle);
        }
        $this->handle = null;
        $this->state = '';
    }

    public function __destruct()
    {
        $this->close();
    }

    /** @return resource */
    private static function openFile(string $path)
    {
        $handle = is_file($path) ? @fopen($path, 'rb') : false;
        if ($handle === false) {
            throw new BackupFormatException(BackupFormatException::OPEN_FAILED);
        }

        return $handle;
    }

    /**
     * @param  resource  $handle
     * @return array{header:array{version:int,kind:int,run_uuid:string,run_uuid_hex:string,seq:int,recipients:list<string>,head_bytes:int},sealed:array<string,string>,stream_header:string,raw:string}
     */
    private static function parseHead($handle): array
    {
        $fixed = self::readExactly($handle, BackupFormat::FIXED_HEAD_BYTES);
        if ($fixed === null) {
            throw new BackupFormatException(BackupFormatException::TRUNCATED);
        }
        if (! hash_equals(BackupFormat::MAGIC, substr($fixed, 0, 5))) {
            throw new BackupFormatException(BackupFormatException::BAD_MAGIC);
        }
        $version = ord($fixed[5]);
        if ($version !== BackupFormat::VERSION) {
            throw new BackupFormatException(BackupFormatException::UNSUPPORTED_VERSION);
        }
        $kind = ord($fixed[6]);
        $count = ord($fixed[27]);
        if (! BackupFormat::isKnownKind($kind) || $count < 1 || $count > BackupFormat::MAX_RECIPIENTS) {
            throw new BackupFormatException(BackupFormatException::BAD_HEADER);
        }
        $runUuid = substr($fixed, 7, BackupFormat::RUN_UUID_BYTES);
        $seq = unpack('N', substr($fixed, 23, 4))[1];

        $entryLen = BackupFormat::FINGERPRINT_BYTES + BackupFormat::SEALED_KEY_BYTES;
        $entries = self::readExactly($handle, $count * $entryLen);
        $streamHeader = $entries === null ? null : self::readExactly($handle, SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_HEADERBYTES);
        if ($entries === null || $streamHeader === null) {
            throw new BackupFormatException(BackupFormatException::TRUNCATED);
        }

        $sealed = [];
        for ($i = 0; $i < $count; $i++) {
            $entry = substr($entries, $i * $entryLen, $entryLen);
            $fingerprint = bin2hex(substr($entry, 0, BackupFormat::FINGERPRINT_BYTES));
            if (isset($sealed[$fingerprint])) {
                throw new BackupFormatException(BackupFormatException::BAD_HEADER);
            }
            $sealed[$fingerprint] = substr($entry, BackupFormat::FINGERPRINT_BYTES);
        }

        $raw = $fixed.$entries.$streamHeader;

        return [
            'header' => [
                'version' => $version,
                'kind' => $kind,
                'run_uuid' => $runUuid,
                'run_uuid_hex' => bin2hex($runUuid),
                'seq' => $seq,
                // Rein numerische Fingerabdruecke werden von PHP zu int-Schluesseln: explizit als String zurueck.
                'recipients' => array_map('strval', array_keys($sealed)),
                'head_bytes' => strlen($raw),
            ],
            'sealed' => $sealed,
            'stream_header' => $streamHeader,
            'raw' => $raw,
        ];
    }

    /** @param  resource  $handle */
    private static function readExactly($handle, int $length): ?string
    {
        $buffer = '';
        while (strlen($buffer) < $length) {
            $part = fread($handle, $length - strlen($buffer));
            if ($part === false || $part === '') {
                return null;
            }
            $buffer .= $part;
        }

        return $buffer;
    }
}
