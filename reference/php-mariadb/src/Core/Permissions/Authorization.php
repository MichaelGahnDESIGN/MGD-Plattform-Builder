<?php

declare(strict_types=1);

namespace MGD\Platform\Core\Permissions;

use MGD\Platform\Core\Auth\Actor;
use RuntimeException;

final class Authorization
{
    public function requireCapability(Actor $actor, string $capability): void
    {
        if (!$actor->hasCapability($capability)) {
            throw new RuntimeException('Forbidden');
        }
    }
}
