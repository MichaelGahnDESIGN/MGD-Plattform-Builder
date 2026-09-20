<?php

declare(strict_types=1);

namespace MGD\Platform\Core\Auth;

final readonly class Actor
{
    public function __construct(
        public string $id,
        public string $type,
        public array $capabilities,
    ) {
    }

    public function hasCapability(string $capability): bool
    {
        return in_array($capability, $this->capabilities, true);
    }
}
