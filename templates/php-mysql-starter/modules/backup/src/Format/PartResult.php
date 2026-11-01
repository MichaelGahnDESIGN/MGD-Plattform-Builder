<?php

declare(strict_types=1);

namespace MGD\Backup\Format;

/** Ergebnis von PartWriter::finish(): Kennzahlen und Pruefsummen der fertigen Chiffredatei. */
final readonly class PartResult
{
    public function __construct(
        public string $path,
        public int $kind,
        public int $seq,
        /** Hex der SHA-256 ueber die gesamte Chiffredatei. */
        public string $sha256,
        public int $plainBytes,
        public int $cipherBytes,
    ) {}

    /** @return array{path:string,kind:int,seq:int,sha256:string,plain_bytes:int,cipher_bytes:int} */
    public function toArray(): array
    {
        return [
            'path' => $this->path,
            'kind' => $this->kind,
            'seq' => $this->seq,
            'sha256' => $this->sha256,
            'plain_bytes' => $this->plainBytes,
            'cipher_bytes' => $this->cipherBytes,
        ];
    }
}
