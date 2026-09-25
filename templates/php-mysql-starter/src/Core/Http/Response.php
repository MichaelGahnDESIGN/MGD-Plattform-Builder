<?php

declare(strict_types=1);

namespace MGD\Starter\Core\Http;

use MGD\Starter\Core\View\View;

final class Response
{
    public function __construct(
        public readonly string $body = '',
        public readonly int $status = 200,
        public readonly array $headers = [],
    ) {
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => 'text/html; charset=utf-8']);
    }

    public static function text(string $body, string $contentType = 'text/plain; charset=utf-8', int $status = 200): self
    {
        return new self($body, $status, ['Content-Type' => $contentType]);
    }

    public static function json(array $data, int $status = 200): self
    {
        return new self(
            json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR),
            $status,
            ['Content-Type' => 'application/json; charset=utf-8']
        );
    }

    public static function redirect(string $path): self
    {
        return new self('', 303, ['Location' => View::url($path)]);
    }

    public static function download(string $body, string $filename, string $contentType): self
    {
        $safeName = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename) ?: 'download';

        return new self($body, 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'attachment; filename="' . $safeName . '"',
        ]);
    }

    public function withHeader(string $name, string $value): self
    {
        return new self($this->body, $this->status, [...$this->headers, $name => $value]);
    }

    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header($name . ': ' . str_replace(["\r", "\n"], '', $value));
        }

        echo $this->body;
    }
}
