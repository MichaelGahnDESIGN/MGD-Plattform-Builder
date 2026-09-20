<?php

declare(strict_types=1);

namespace MGD\Platform\Core\Jobs;

use RuntimeException;

final class JobHandlerRegistry
{
    /** @var array<string, callable(array): void> */
    private array $handlers = [];

    public function register(string $topic, callable $handler): void
    {
        $topic = trim($topic);

        if ($topic === '') {
            throw new RuntimeException('Job topic must not be empty.');
        }

        if (isset($this->handlers[$topic])) {
            throw new RuntimeException('Handler already registered for topic: ' . $topic);
        }

        $this->handlers[$topic] = $handler;
    }

    public function handle(string $topic, array $payload): void
    {
        $handler = $this->handlers[$topic] ?? null;

        if (!$handler) {
            throw new RuntimeException('No handler registered for topic: ' . $topic);
        }

        $handler($payload);
    }

    public function topics(): array
    {
        $topics = array_keys($this->handlers);
        sort($topics, SORT_STRING);

        return $topics;
    }
}
