<?php

declare(strict_types=1);

namespace App\Application\Tasks\UpdateTask;

use App\Domain\Tasks\TaskStatus;

/**
 * A null title keeps the current one. The assignee and catalog references have three states:
 * a null id with no clear flag keeps the current value, a non-null id replaces it, and a clear
 * flag removes it.
 */
readonly class UpdateTaskCommand
{
    public function __construct(
        public int $taskId,
        public ?string $title = null,
        public ?int $assignedMemberId = null,
        public ?TaskStatus $status = null,
        public ?string $day = null,
        public ?string $time = null,
        public ?int $categoryId = null,
        public ?int $recurrenceId = null,
        public ?string $priority = null,
        public ?bool $repeatable = null,
        public bool $clearCategory = false,
        public bool $clearRecurrence = false,
        public bool $clearAssignee = false
    ) {
    }
}
