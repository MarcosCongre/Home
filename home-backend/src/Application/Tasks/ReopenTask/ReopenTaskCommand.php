<?php

declare(strict_types=1);

namespace App\Application\Tasks\ReopenTask;

class ReopenTaskCommand
{
    public function __construct(
        public int $taskId
    ) {
    }
}
