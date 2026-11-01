<?php

declare(strict_types=1);

namespace MGD\Backup\Destinations;

/** Ergebnis eines (Teil-)Uploads: fertig (done) oder fortsetzbar (paused) mit zu speicherndem resume_state. */
final readonly class UploadProgress
{
    public function __construct(
        public bool $done,
        public bool $paused = false,
        public ?string $remoteRef = null,
        public ?string $resumeState = null,
        public int $bytesDone = 0,
    ) {
    }

    public static function done(string $remoteRef, int $bytes): self
    {
        return new self(true, false, $remoteRef, null, $bytes);
    }

    public static function paused(string $resumeState, int $bytesDone): self
    {
        return new self(false, true, null, $resumeState, $bytesDone);
    }
}
