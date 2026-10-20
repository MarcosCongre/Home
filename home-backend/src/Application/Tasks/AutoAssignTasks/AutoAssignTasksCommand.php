<?php

declare(strict_types=1);

namespace App\Application\Tasks\AutoAssignTasks;

use DateTimeImmutable;

class AutoAssignTasksCommand
{
    public function __construct(
        public string $householdId,
        public ?DateTimeImmutable $now = null
    ) {
    }
}
