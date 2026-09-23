<?php

declare(strict_types=1);

namespace App\Application\Tasks\UpdateTask;

use App\Domain\Tasks\TaskStatus;

readonly class UpdateTaskCommand
{
    public function __construct(
        public string $taskId,
        public string $title,
        public ?string $assignedMemberId = null,
        public ?TaskStatus $status = null
    ) {
    }
}