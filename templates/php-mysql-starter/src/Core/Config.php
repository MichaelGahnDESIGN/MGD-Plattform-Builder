<?php

declare(strict_types=1);

namespace MGD\Starter\Core;

use RuntimeException;

final class Config
{
    public function __construct(private readonly array $values)
    {
    }

    public static function load(string $file): self
    {
        if (!is_file($file)) {
            throw new RuntimeException(
                'config/config.php fehlt. Bitte config/config.example.php kopieren und anpassen.'
            );
        }

        $values = require $file;

        if (!is_array($values)) {
            throw new RuntimeException('config/config.php muss ein Array zurückgeben.');
        }

        return new self($values);
    }

    public function get(string $path, mixed $default = null): mixed
    {
        $current = $this->values;

        foreach (explode('.', $path) as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return $default;
            }

            $current = $current[$segment];
        }

        return $current;
    }

    public function string(string $path, string $default = ''): string
    {
        $value = $this->get($path, $default);

        return is_scalar($value) ? (string) $value : $default;
    }

    public function bool(string $path, bool $default = false): bool
    {
        return $this->get($path, $default) === true;
    }

    public function path(string $name): string
    {
        $path = $this->get('paths.' . $name);

        if (!is_string($path) || $path === '') {
            throw new RuntimeException('Pfad nicht konfiguriert: ' . $name);
        }

        return $path;
    }

    public function paths(): array
    {
        $paths = $this->get('paths', []);

        return is_array($paths) ? $paths : [];
    }
}
