<?php

declare(strict_types=1);

namespace MGD\Starter\Core\License;

use Throwable;

/**
 * Beantwortet: Ist für dieses Projekt eine Whitelabel-Lizenz aktiv? Ist ein Modul lizenziert?
 * Ohne gültigen, signierten Schlüssel für den aktuellen Host bleibt das Label Pflicht.
 */
final class LicenseService
{
    private ?LicenseGrant $whitelabel = null;
    private bool $whitelabelResolved = false;

    public function __construct(
        private readonly LicenseRepository $repository,
        private readonly LicenseKey $keys,
        private readonly string $host,
        private readonly string $configuredWhitelabelKey = '',
    ) {
    }

    public static function hostFrom(string $baseUrl, string $requestHost): string
    {
        $host = (string) (parse_url($baseUrl, PHP_URL_HOST) ?: '');

        if ($host === '') {
            $host = (string) preg_replace('/:\d+$/', '', $requestHost);
        }

        return strtolower($host);
    }

    public function host(): string
    {
        return $this->host;
    }

    public function whitelabel(): ?LicenseGrant
    {
        if ($this->whitelabelResolved) {
            return $this->whitelabel;
        }

        $this->whitelabelResolved = true;
        $key = $this->configuredWhitelabelKey !== '' ? $this->configuredWhitelabelKey : $this->storedWhitelabelKey();
        $grant = $key !== '' ? $this->validate($key, LicenseGrant::TYPE_WHITELABEL) : null;

        return $this->whitelabel = $grant;
    }

    public function isWhitelabel(): bool
    {
        return $this->whitelabel() !== null;
    }

    /**
     * Prüft Signatur, Typ, Ablauf, Host und – bei Modulen – die Modul-ID.
     */
    public function validate(string $key, string $type, ?string $moduleId = null): ?LicenseGrant
    {
        $grant = $this->keys->parse($key);

        if ($grant === null || $grant->type !== $type || $grant->isExpired() || !$grant->coversHost($this->host)) {
            return null;
        }

        if ($type === LicenseGrant::TYPE_MODULE && $grant->moduleId !== $moduleId) {
            return null;
        }

        return $grant;
    }

    public function moduleGrant(string $moduleId): ?LicenseGrant
    {
        $key = $this->safeFind(LicenseGrant::TYPE_MODULE, $moduleId);

        return $key !== '' ? $this->validate($key, LicenseGrant::TYPE_MODULE, $moduleId) : null;
    }

    private function storedWhitelabelKey(): string
    {
        return $this->safeFind(LicenseGrant::TYPE_WHITELABEL, LicenseRepository::WHITELABEL_SUBJECT);
    }

    private function safeFind(string $kind, string $subject): string
    {
        try {
            return $this->repository->find($kind, $subject) ?? '';
        } catch (Throwable) {
            // Vor der Installation existiert die Tabelle noch nicht: dann gilt das Label.
            return '';
        }
    }
}
