<?php

declare(strict_types=1);

namespace MGD\Starter\Core\License;

/**
 * Prüft, ob Lizenztext, Label-Code, Label-CSS und Logos unverändert sind.
 * Die Prüfsummen werden bei jedem Release mit `mgd-platform template check` abgeglichen.
 */
final class LicenseIntegrity
{
    public const FILES = [
        'MGD-Lizenz.md' => 'fe8adc858e1387434d0da6885a0ecfef892b2690e1c94b39753dcc1d00042cb7',
        'src/Core/License/PoweredBy.php' => '9b578a7640e93690b6bd7bd341d5f3f3190b207e1a190dba96d24e8c17ca96f2',
        'public/assets/css/powered-by.css' => 'b0735b5d9778909cd65d941a2a6d46164bef27afdcd13b0dd0cc7ec7ee9f8615',
        'public/assets/brand/mgd-logo-light.svg' => '44f68864d9fbf98bd15d23a82067f17d91fa9361c00c647e4067364f760f48a6',
        'public/assets/brand/mgd-logo-dark.svg' => '63fa802f9b545beec74097148117a113daa11e8ded1ae208483fca651583c12e',
    ];

    public function __construct(private readonly string $root)
    {
    }

    /**
     * @return list<string> relative Pfade, die fehlen oder verändert wurden
     */
    public function violations(): array
    {
        $violations = [];

        foreach (self::FILES as $relative => $expected) {
            $file = $this->root . '/' . $relative;

            if (!is_file($file) || !hash_equals($expected, (string) hash_file('sha256', $file))) {
                $violations[] = $relative;
            }
        }

        return $violations;
    }

    public function isIntact(): bool
    {
        return $this->violations() === [];
    }
}
