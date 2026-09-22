<?php

declare(strict_types=1);

namespace App\Application\Tasks\ListTasks;

use App\Domain\Tasks\Task;
use App\Domain\Tasks\TaskRepositoryInterface;

final class ListTasksHandler
{
    public function __construct(
        private TaskRepositoryInterface $taskRepository
    ) {
    }

    /**
     * @return array<int, Task>
     */
    public function handle(ListTasksQuery $query): array
    {
        $tasks = $this->taskRepository->findByHouseholdId($query->householdId);

        usort(
            $tasks,
            static fn (Task $a, Task $b): int => $a->createdAt() <=> $b->createdAt()
        );

        return $tasks;
    }
}
