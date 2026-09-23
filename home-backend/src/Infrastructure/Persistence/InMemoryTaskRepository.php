<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Tasks\Task;
use App\Domain\Tasks\TaskRepositoryInterface;

final class InMemoryTaskRepository implements TaskRepositoryInterface
{
    /** @var array<int, Task> */
    private array $tasks = [];

    public function save(Task $task): void
    {
        if ($task->id() === 0) {
            $task->assignGeneratedId($this->nextId());
        }

        $this->tasks[$task->id()] = $task;
    }

    private function nextId(): int
    {
        return $this->tasks === [] ? 1 : max(array_keys($this->tasks)) + 1;
    }

    public function findById(int $id): ?Task
    {
        return $this->tasks[$id] ?? null;
    }

    public function delete(int $id): void
    {
        unset($this->tasks[$id]);
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
