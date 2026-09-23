<?php

declare(strict_types=1);

namespace App\Application\Tasks\CompleteTask;

class CompleteTaskCommand
{
    public function __construct(
        public int $taskId,
        public string $userId
    ) {
    }
}
