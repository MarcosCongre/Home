<?php

declare(strict_types=1);

namespace App\Application\Tasks\UpdateTask;

use App\Domain\Tasks\TaskStatus;

readonly class UpdateTaskCommand
{
    public function __construct(
        public int $taskId,
        public string $title,
        public ?int $assignedMemberId = null,
        public ?TaskStatus $status = null
    ) {
    }
}