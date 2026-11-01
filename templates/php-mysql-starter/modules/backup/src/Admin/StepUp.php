<?php

declare(strict_types=1);

namespace MGD\Backup\Admin;

use MGD\Backup\Store;

/**
 * Passwort-Bestaetigung (Step-up) fuer sicherheitsrelevante Aktionen: Schluessel, Ziele, Zeitplan/Aufbewahrung, Token, Loeschen.
 * Vorbild ist das Starter-Muster beim Passwortwechsel (password_verify gegen den gespeicherten Hash). Fehlversuche werden
 * gezaehlt: nach 5 Fehlversuchen innerhalb einer Stunde ist die Bestaetigung fuer den Rest der Stunde gesperrt, auch bei
 * richtigem Passwort (schuetzt vor Raten mit einer gestohlenen Sitzung).
 */
final class StepUp
{
    public const OK = 'ok';

    public const WRONG = 'wrong';

    public const BLOCKED = 'blocked';

    private const MAX_FAILURES = 5;

    private const WINDOW_SECONDS = 3600;

    public function __construct(private readonly Store $store)
    {
    }

    /**
     * @param string|null $hash gespeicherter Passwort-Hash des Nutzers
     * @return self::OK|self::WRONG|self::BLOCKED
     */
    public function verify(?string $hash, string $password): string
    {
        $settings = $this->store->settings();
        $since = Store::toDate($settings['stepup_fail_since'] !== null ? (string) $settings['stepup_fail_since'] : null);
        $count = (int) $settings['stepup_fail_count'];
        $now = $this->store->now();

        if ($since === null || $since->modify('+' . self::WINDOW_SECONDS . ' seconds') <= $now) {
            $count = 0;
            $since = null;
        }

        if ($count >= self::MAX_FAILURES) {
            return self::BLOCKED;
        }

        if ($hash !== null && $password !== '' && strlen($password) <= 1024 && password_verify($password, $hash)) {
            if ((int) $settings['stepup_fail_count'] !== 0) {
                $this->store->updateSettings(['stepup_fail_count' => 0, 'stepup_fail_since' => null]);
            }

            return self::OK;
        }

        $this->store->updateSettings(['stepup_fail_count' => $count + 1, 'stepup_fail_since' => ($since ?? $now)->format(Store::DATE_FORMAT)]);

        return self::WRONG;
    }
}
