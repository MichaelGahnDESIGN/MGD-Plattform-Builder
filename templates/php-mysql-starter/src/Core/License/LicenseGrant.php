<?php

declare(strict_types=1);

namespace MGD\Starter\Core\License;

/**
 * Inhalt eines vom Lizenzgeber signierten Lizenzschlüssels.
 */
final class LicenseGrant
{
    public const TYPE_WHITELABEL = 'whitelabel';
    public const TYPE_MODULE = 'module';

    /**
     * @param list<string> $domains
     */
    public function __construct(
        public readonly string $type,
        public readonly string $projectId,
        public readonly array $domains,
        public readonly string $licensee,
        public readonly string $issuedAt,
        public readonly ?string $moduleId = null,
        public readonly ?string $expiresAt = null,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public static function fromPayload(array $payload): ?self
    {
        $type = $payload['type'] ?? null;
        $domains = $payload['domains'] ?? null;

        if (!in_array($type, [self::TYPE_WHITELABEL, self::TYPE_MODULE], true) || !is_array($domains) || $domains === []) {
            return null;
        }

        foreach (['project_id', 'licensee', 'issued_at'] as $field) {
            if (!is_string($payload[$field] ?? null) || $payload[$field] === '') {
                return null;
            }
        }

        $moduleId = is_string($payload['module_id'] ?? null) ? $payload['module_id'] : null;

        if ($type === self::TYPE_MODULE && $moduleId === null) {
            return null;
        }

        return new self(
            $type,
            $payload['project_id'],
            array_values(array_filter(array_map('strval', $domains), static fn (string $d): bool => $d !== '')),
            $payload['licensee'],
            $payload['issued_at'],
            $moduleId,
            is_string($payload['expires_at'] ?? null) ? $payload['expires_at'] : null,
        );
    }

    public function isExpired(?int $now = null): bool
    {
        if ($this->expiresAt === null) {
            return false;
        }

        $expires = strtotime($this->expiresAt);

        return $expires !== false && $expires < ($now ?? time());
    }

    /**
     * Exakte Domain oder Wildcard "*.example.org" (nicht die Apex-Domain selbst).
     */
    public function coversHost(string $host): bool
    {
        $host = strtolower(rtrim($host, '.'));

        if ($host === '') {
            return false;
        }

        foreach ($this->domains as $domain) {
            $domain = strtolower(rtrim($domain, '.'));

            if ($domain === $host) {
                return true;
            }

            if (str_starts_with($domain, '*.') && str_ends_with($host, substr($domain, 1))) {
                return true;
            }
        }

        return false;
    }
}
