<?php

declare(strict_types=1);

namespace MGD\Backup\Format;

use HashContext;
use Throwable;

/**
 * Schreibt eine verschluesselte Teildatei im Format MGDB1 (siehe BackupFormat).
 *
 * Je Teil ein zufaelliger Schluessel K, versiegelt per crypto_box_seal fuer jeden Empfaenger. Die Daten laufen als
 * secretstream (XChaCha20-Poly1305) in Bloecken zu 64 KiB; der letzte Block traegt TAG_FINAL. Die zusaetzlichen
 * authentifizierten Daten (AAD) jedes Blocks sind SHA-256 ueber den gesamten Vorspann (Kopf inkl. secretstream-Header):
 * Aenderungen am Kopf, Tausch zwischen Laeufen/Teilen, Abschneiden und Anhaengen fuehren beim Lesen zu einem Fehler.
 *
 * Die Datei wird exklusiv (kein Ueberschreiben) mit Rechten 0600 angelegt.
 */
final class PartWriter
{
    /** @var resource|null */
    private $handle;

    private string $state;

    private string $aad;

    private string $buffer = '';

    private int $plainBytes = 0;

    private int $cipherBytes = 0;

    private bool $closed = false;

    private HashContext $sha256;

    private function __construct(
        private readonly string $path,
        private readonly int $kind,
        private readonly int $seq,
    ) {}

    /**
     * @param  string  $runUuid  16 Byte binaer
     * @param  list<RecipientKey>  $recipients  1 bis BackupFormat::MAX_RECIPIENTS Empfaenger (verschiedene Schluessel)
     */
    public static function open(string $path, int $kind, string $runUuid, int $seq, array $recipients): self
    {
        if (! BackupFormat::isKnownKind($kind)
            || strlen($runUuid) !== BackupFormat::RUN_UUID_BYTES
            || $seq < 0 || $seq > 0xFFFFFFFF) {
            throw new BackupFormatException(BackupFormatException::BAD_ARGUMENT);
        }
        $recipients = array_values($recipients);
        $count = count($recipients);
        if ($count < 1 || $count > BackupFormat::MAX_RECIPIENTS) {
            throw new BackupFormatException(BackupFormatException::BAD_ARGUMENT);
        }
        $fingerprints = [];
        foreach ($recipients as $recipient) {
            if (! $recipient instanceof RecipientKey || isset($fingerprints[$recipient->fingerprint()])) {
                throw new BackupFormatException(BackupFormatException::BAD_ARGUMENT);
            }
            $fingerprints[$recipient->fingerprint()] = true;
        }
        if (file_exists($path)) {
            throw new BackupFormatException(BackupFormatException::OPEN_FAILED);
        }

        $writer = new self($path, $kind, $seq);

        $key = sodium_crypto_secretstream_xchacha20poly1305_keygen();
        try {
            $head = BackupFormat::MAGIC.chr(BackupFormat::VERSION).chr($kind).$runUuid.pack('N', $seq).chr($count);
            foreach ($recipients as $recipient) {
                try {
                    $sealed = sodium_crypto_box_seal($key, $recipient->raw());
                } catch (Throwable) {
                    throw new BackupFormatException(BackupFormatException::BAD_KEY);
                }
                $head .= BackupFormat::fingerprintRaw($recipient->raw()).$sealed;
            }
            [$writer->state, $ssHeader] = sodium_crypto_secretstream_xchacha20poly1305_init_push($key);
        } finally {
            sodium_memzero($key);
        }
        $head .= $ssHeader;
        $writer->aad = hash('sha256', $head, true);

        $writer->sha256 = hash_init('sha256');

        $old = umask(0177);
        $handle = @fopen($path, 'xb');
        umask($old);
        if ($handle === false) {
            throw new BackupFormatException(BackupFormatException::OPEN_FAILED);
        }
        $writer->handle = $handle;

        try {
            $writer->emit($head);
        } catch (Throwable $e) {
            $writer->abort();
            throw $e;
        }

        return $writer;
    }

    /** Haengt Klartext an. Vollstaendige Bloecke gehen sofort verschluesselt in die Datei. */
    public function write(string $data): void
    {
        $this->assertOpen();
        if ($data === '') {
            return;
        }
        $this->plainBytes += strlen($data);
        $this->buffer .= $data;

        // Erst ab mehr als einem vollen Block schreiben: der letzte Block muss FINAL tragen (finish()).
        $size = BackupFormat::BLOCK_SIZE;
        $pos = 0;
        $len = strlen($this->buffer);
        try {
            while ($len - $pos > $size) {
                $this->pushBlock(substr($this->buffer, $pos, $size), false);
                $pos += $size;
            }
        } catch (Throwable $e) {
            $this->abort();
            throw $e;
        }
        if ($pos > 0) {
            $this->buffer = (string) substr($this->buffer, $pos);
        }
    }

    /** Bisher in die Datei geschriebene Chiffre-Bytes (Kopf inkl.); der gepufferte Rest (< 64 KiB) ist noch nicht enthalten. */
    public function bytesWritten(): int
    {
        return $this->cipherBytes;
    }

    /** Bisher angenommene Klartext-Bytes. */
    public function plainBytes(): int
    {
        return $this->plainBytes;
    }

    public function finish(): PartResult
    {
        $this->assertOpen();

        try {
            $this->pushBlock($this->buffer, true);
            $this->buffer = '';

            $handle = $this->handle;
            if ($handle === null || ! fflush($handle) || (function_exists('fsync') && ! @fsync($handle))) {
                throw new BackupFormatException(BackupFormatException::WRITE_FAILED);
            }
        } catch (Throwable $e) {
            $this->abort();
            throw $e instanceof BackupFormatException ? $e : new BackupFormatException(BackupFormatException::WRITE_FAILED);
        }

        $this->closeHandle();
        $this->closed = true;
        $this->state = '';

        return new PartResult(
            path: $this->path,
            kind: $this->kind,
            seq: $this->seq,
            sha256: hash_final($this->sha256),
            plainBytes: $this->plainBytes,
            cipherBytes: $this->cipherBytes,
        );
    }

    /** Bricht ab: schliesst und loescht die unvollstaendige Datei. */
    public function abort(): void
    {
        if ($this->closed) {
            return;
        }
        $this->closed = true;
        $this->closeHandle();
        @unlink($this->path);
        $this->buffer = '';
        $this->state = '';
    }

    public function __destruct()
    {
        // Eine nicht abgeschlossene Datei bleibt liegen (der Aufrufer raeumt sie auf); nur das Handle wird freigegeben.
        $this->closeHandle();
    }

    private function pushBlock(string $plain, bool $final): void
    {
        $cipher = sodium_crypto_secretstream_xchacha20poly1305_push(
            $this->state,
            $plain,
            $this->aad,
            $final ? SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_FINAL : SODIUM_CRYPTO_SECRETSTREAM_XCHACHA20POLY1305_TAG_MESSAGE
        );
        $this->emit(pack('N', strlen($cipher)).$cipher);
    }

    private function emit(string $bytes): void
    {
        $handle = $this->handle;
        if ($handle === null) {
            throw new BackupFormatException(BackupFormatException::WRITE_FAILED);
        }
        $len = strlen($bytes);
        for ($written = 0; $written < $len;) {
            $n = fwrite($handle, $written === 0 ? $bytes : substr($bytes, $written));
            if ($n === false || $n === 0) {
                throw new BackupFormatException(BackupFormatException::WRITE_FAILED);
            }
            $written += $n;
        }
        hash_update($this->sha256, $bytes);
        $this->cipherBytes += $len;
    }

    private function assertOpen(): void
    {
        if ($this->closed) {
            throw new BackupFormatException(BackupFormatException::ALREADY_FINISHED);
        }
    }

    private function closeHandle(): void
    {
        if (is_resource($this->handle)) {
            fclose($this->handle);
        }
        $this->handle = null;
    }
}
