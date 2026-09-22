<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Tasks\Task;
use App\Domain\Tasks\TaskRepositoryInterface;

final class InMemoryTaskRepository implements TaskRepositoryInterface
{
    /** @var array<string, Task> */
    private array $tasks = [];

    public function save(Task $task): void
    {
        $this->tasks[$task->id()] = $task;
    }

    public function findById(string $id): ?Task
    {
        return $this->tasks[$id] ?? null;
    }

    /**
     * @return array<int, Task>
     */
    public function findByHouseholdId(string $householdId): array
    {
        $tasks = array_values(array_filter(
            $this->tasks,
            static fn (Task $task): bool => $task->householdId() === $householdId
        ));

        usort(
            $tasks,
            static fn (Task $a, Task $b): int => $a->createdAt() <=> $b->createdAt()
        );

        return $tasks;
    }
}
