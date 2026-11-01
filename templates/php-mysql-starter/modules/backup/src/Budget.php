<?php

declare(strict_types=1);

namespace MGD\Backup;

use Closure;

/**
 * Zeitbudget eines Cron-Aufrufs mit injizierbarer Uhr (Sekunden als float). Alle langen Schritte fragen exhausted(),
 * bevor sie weiterarbeiten; ein Teil wird nie mittendrin abgebrochen, sondern an einer Satzgrenze geschlossen.
 */
final class Budget
{
    /** @var Closure():float */
    private readonly Closure $clock;

    private readonly float $deadline;

    /** @param (Closure():float)|null $clock Standard: microtime(true) */
    public function __construct(float $seconds, ?Closure $clock = null)
    {
        $this->clock = $clock ?? static fn (): float => microtime(true);
        $this->deadline = ($this->clock)() + max(0.0, $seconds);
    }

    /**
     * Budget fuer einen Cron-Aufruf: hoechstens $seconds, begrenzt durch max_execution_time abzueglich
     * Sicherheitsreserve. Ohne Zeitlimit (CLI) zaehlt nur $seconds.
     */
    public static function forCron(int $seconds, ?Closure $clock = null): self
    {
        $seconds = (float) max(5, min(3600, $seconds));
        $limit = (int) ini_get('max_execution_time');
        if ($limit > 0) {
            $elapsed = microtime(true) - (float) ($_SERVER['REQUEST_TIME_FLOAT'] ?? microtime(true));
            $seconds = min($seconds, $limit - $elapsed - 8.0);
        }

        return new self(max(0.0, $seconds), $clock);
    }

    public function remaining(): float
    {
        return max(0.0, $this->deadline - ($this->clock)());
    }

    public function exhausted(): bool
    {
        return $this->remaining() <= 0.0;
    }
}
