<?php

declare(strict_types=1);

namespace MGD\Starter\Core\Http;

final class SecurityHeaders
{
    private readonly string $nonce;
    /** @var list<string> */
    private array $scriptSources = [];
    /** @var list<string> */
    private array $styleSources = [];
    private bool $inlineStyles = false;

    public function __construct(private readonly bool $hsts = false)
    {
        $this->nonce = base64_encode(random_bytes(16));
    }

    public function nonce(): string
    {
        return $this->nonce;
    }

    /**
     * Erlaubt eine zusätzliche HTTPS-Herkunft (z. B. Editor-CDN) für Skripte.
     */
    public function allowScriptOrigin(string $url): void
    {
        $origin = self::origin($url);

        if ($origin !== null) {
            $this->scriptSources[] = $origin;
        }
    }

    public function allowStyleOrigin(string $url): void
    {
        $origin = self::origin($url);

        if ($origin !== null) {
            $this->styleSources[] = $origin;
        }
    }

    /**
     * Backoffice-Editoren (TinyMCE, GrapesJS, Quill) setzen Inline-Styles.
     */
    public function allowInlineStyles(): void
    {
        $this->inlineStyles = true;
    }

    public function contentSecurityPolicy(): string
    {
        $scripts = array_unique(["'self'", "'nonce-" . $this->nonce . "'", ...$this->scriptSources]);
        $styles = $this->inlineStyles
            ? array_unique(["'self'", "'unsafe-inline'", ...$this->styleSources])
            : array_unique(["'self'", "'nonce-" . $this->nonce . "'", ...$this->styleSources]);

        return implode('; ', [
            "default-src 'self'",
            'script-src ' . implode(' ', $scripts),
            'style-src ' . implode(' ', $styles),
            "img-src 'self' data: blob: https:",
            "font-src 'self' data:",
            "connect-src 'self'",
            "frame-src 'self' blob:",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
            "frame-ancestors 'none'",
        ]);
    }

    public function send(): void
    {
        if (headers_sent()) {
            return;
        }

        header('Content-Security-Policy: ' . $this->contentSecurityPolicy());
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: strict-origin-when-cross-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=(), interest-cohort=()');
        header('Cross-Origin-Opener-Policy: same-origin');

        if ($this->hsts) {
            header('Strict-Transport-Security: max-age=31536000; includeSubDomains');
        }
    }

    private static function origin(string $url): ?string
    {
        $parts = parse_url($url);

        if (!is_array($parts) || ($parts['scheme'] ?? '') !== 'https' || !isset($parts['host'])) {
            return null;
        }

        if (preg_match('/^[a-z0-9.-]+$/i', $parts['host']) !== 1) {
            return null;
        }

        $port = isset($parts['port']) ? ':' . (int) $parts['port'] : '';

        return 'https://' . strtolower($parts['host']) . $port;
    }
}
