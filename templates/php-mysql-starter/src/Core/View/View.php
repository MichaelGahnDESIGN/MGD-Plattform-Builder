<?php

declare(strict_types=1);

namespace MGD\Starter\Core\View;

final class View
{
    private static string $basePath = '';

    public static function setBasePath(string $basePath): void
    {
        $basePath = rtrim($basePath, '/');
        self::$basePath = preg_match('#^(/[A-Za-z0-9._-]+)*$#', $basePath) === 1 ? $basePath : '';
    }

    public static function e(string|int|float|null $value): string
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    /**
     * Interne URL inkl. Basis-Pfad. Externe URLs bleiben unverändert.
     */
    public static function url(string $path): string
    {
        if (preg_match('#^(https?:)?//#i', $path) === 1 || str_starts_with($path, 'mailto:')) {
            return $path;
        }

        return self::$basePath . '/' . ltrim($path, '/');
    }

    public static function asset(string $path): string
    {
        return self::url('/assets/' . ltrim($path, '/'));
    }

    public static function date(?string $value, string $format = 'd.m.Y'): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        $timestamp = strtotime($value . (strlen($value) > 10 ? ' UTC' : ''));

        return $timestamp === false ? '' : date($format, $timestamp);
    }
}
