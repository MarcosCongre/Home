<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use RuntimeException;

final class RouteNotFoundException extends RuntimeException
{
    public static function for(string $method, string $path): self
    {
        return new self(sprintf('No route matches %s %s.', $method, $path));
    }
}
