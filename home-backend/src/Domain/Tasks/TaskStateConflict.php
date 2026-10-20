<?php

declare(strict_types=1);

namespace App\Domain\Tasks;

use RuntimeException;

/**
 * Raised when a task transition is not allowed from its current status.
 * The message is safe to expose to API clients.
 */
final class TaskStateConflict extends RuntimeException
{
    public static function alreadyCompleted(): self
    {
        return new self('Task is already completed.');
    }

    public static function notCompleted(): self
    {
        return new self('Task is not completed.');
    }
}
