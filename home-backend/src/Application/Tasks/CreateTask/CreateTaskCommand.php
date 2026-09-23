<?php

declare(strict_types=1);

namespace App\Application\Tasks\CreateTask;

readonly class CreateTaskCommand
{
    public function __construct(
        public string $title,
        public string $householdId,
        public string $userId,
        public ?int $assignedMemberId = null
    ) {
    }
}
