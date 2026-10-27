<?php

declare(strict_types=1);

namespace MGD\Starter\Core\View;

final class Flash
{
    private const SESSION_KEY = '_flash';

    public static function add(string $kind, string $message): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }

        $_SESSION[self::SESSION_KEY][] = ['kind' => $kind, 'message' => $message];
    }

    public static function render(): string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return '';
        }

        $messages = $_SESSION[self::SESSION_KEY] ?? [];
        unset($_SESSION[self::SESSION_KEY]);
        $html = '';

        foreach (is_array($messages) ? $messages : [] as $message) {
            $html .= Ui::notice((string) ($message['message'] ?? ''), (string) ($message['kind'] ?? 'info'));
        }

        return $html;
    }
}
