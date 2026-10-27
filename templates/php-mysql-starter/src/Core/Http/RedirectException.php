<?php

declare(strict_types=1);

namespace MGD\Starter\Core\Http;

use RuntimeException;

final class RedirectException extends RuntimeException
{
    public function __construct(public readonly string $location)
    {
        parent::__construct('Redirect to ' . $location);
    }
}
