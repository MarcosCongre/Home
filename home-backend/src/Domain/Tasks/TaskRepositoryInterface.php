<?php

declare(strict_types=1);

namespace App\Domain\Tasks;

// Tasks are soft-deleted (saved with a deletedAt); reads never return them.
interface TaskRepositoryInterface
{
    public function save(Task $task): void;

    public function findById(int $id): ?Task;

    /**
     * @return array<int, Task>
     */
    public function findByHouseholdId(string $householdId): array;
}
