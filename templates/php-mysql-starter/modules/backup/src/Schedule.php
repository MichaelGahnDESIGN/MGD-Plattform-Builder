<?php

declare(strict_types=1);

namespace MGD\Backup;

use DateTimeImmutable;
use DateTimeZone;

/**
 * Berechnet den naechsten Lauf eines Zeitplans. Gerechnet wird in der konfigurierten Zeitzone, das Ergebnis ist UTC.
 * Sommer-/Winterzeit: Eine nicht existierende Ortszeit wird von PHP auf die folgende existierende verschoben, eine
 * doppelte zaehlt nur einmal. Der Cron-Aufruf bestimmt, wie genau der Start ist (Lauf beim ersten Aufruf nach der Zeit).
 */
final class Schedule
{
    public const INTERVALS = ['off', 'daily', 'weekly', 'monthly'];

    private const TIME_REGEX = '/^([01]\d|2[0-3]):([0-5]\d)$/D';

    private function __construct() {}

    public static function isValidTime(string $time): bool
    {
        return preg_match(self::TIME_REGEX, $time) === 1;
    }

    /**
     * Naechster Lauf STRENG nach $after, in UTC; null bei 'off' (und bei einer ungueltigen Uhrzeit).
     *
     * @param int $weekday 1-7 (Montag = 1), nur fuer weekly
     * @param int $monthday 1-28, nur fuer monthly
     */
    public static function nextRun(string $interval, string $time, int $weekday, int $monthday, DateTimeImmutable $after, string $timezone = 'Europe/Berlin'): ?DateTimeImmutable
    {
        if (!in_array($interval, ['daily', 'weekly', 'monthly'], true) || preg_match(self::TIME_REGEX, $time, $m) !== 1) {
            return null;
        }

        $weekday = max(1, min(7, $weekday));
        $monthday = max(1, min(28, $monthday));

        try {
            $zone = new DateTimeZone($timezone);
        } catch (\Exception) {
            $zone = new DateTimeZone('UTC');
        }

        $start = $after->setTimezone($zone)->setTime(0, 0);

        // Hoechstens ~13 Monate vorausschauen; jeder Zeitplan trifft innerhalb von 31 Tagen.
        for ($i = 0; $i <= 400; $i++) {
            $day = $start->modify('+' . $i . ' day');
            $matches = match ($interval) {
                'daily' => true,
                'weekly' => (int) $day->format('N') === $weekday,
                default => (int) $day->format('j') === $monthday,
            };

            if (!$matches) {
                continue;
            }

            $candidate = $day->setTime((int) $m[1], (int) $m[2])->setTimezone(new DateTimeZone('UTC'));

            if ($candidate > $after) {
                return $candidate;
            }
        }

        return null;
    }
}
