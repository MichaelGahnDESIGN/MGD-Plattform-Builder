<?php

declare(strict_types=1);

namespace MGD\Starter\Core\Http;

final class Request
{
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        private readonly array $query = [],
        private readonly array $post = [],
        private readonly array $files = [],
        private readonly array $server = [],
    ) {
    }

    public static function fromGlobals(string $basePath): self
    {
        $uriPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $path = is_string($uriPath) ? rawurldecode($uriPath) : '/';
        $basePath = rtrim($basePath, '/');

        if ($basePath !== '' && str_starts_with($path, $basePath)) {
            $path = substr($path, strlen($basePath));
        }

        return new self(
            strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')),
            self::normalizePath($path),
            $_GET,
            $_POST,
            $_FILES,
            $_SERVER,
        );
    }

    public static function normalizePath(string $path): string
    {
        $path = '/' . ltrim($path, '/');

        if ($path !== '/' && str_ends_with($path, '/')) {
            $path = rtrim($path, '/');
        }

        return $path;
    }

    public function query(string $key, string $default = ''): string
    {
        $value = $this->query[$key] ?? $default;

        return is_string($value) ? trim($value) : $default;
    }

    public function input(string $key, string $default = ''): string
    {
        $value = $this->post[$key] ?? $default;

        return is_string($value) ? $value : $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->post);
    }

    /**
     * @return list<string>
     */
    public function inputList(string $key): array
    {
        $value = $this->post[$key] ?? [];

        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, 'is_string'));
    }

    /**
     * Rohwert für verschachtelte Formulare (z. B. s[design.light.primary]).
     */
    public function raw(string $key): mixed
    {
        return $this->post[$key] ?? null;
    }

    public function file(string $key): ?array
    {
        $file = $this->files[$key] ?? null;

        if (!is_array($file) || !isset($file['error'], $file['tmp_name']) || is_array($file['error'])) {
            return null;
        }

        return $file['error'] === UPLOAD_ERR_OK ? $file : null;
    }

    public function ip(): string
    {
        return (string) ($this->server['REMOTE_ADDR'] ?? '0.0.0.0');
    }

    public function isPost(): bool
    {
        return $this->method === 'POST';
    }
}
