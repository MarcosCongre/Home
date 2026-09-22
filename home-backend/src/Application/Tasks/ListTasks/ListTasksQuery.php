<?php

declare(strict_types=1);

namespace App\Application\Tasks\ListTasks;

class ListTasksQuery
{
    public function __construct(
        public string $householdId
    ) {
    }
}
