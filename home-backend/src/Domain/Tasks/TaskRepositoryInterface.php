<?php

declare(strict_types=1);

namespace App\Domain\Tasks;

interface TaskRepositoryInterface
{
    public function save(Task $task): void;

    public function findById(string $id): ?Task;

    /**
     * @return array<int, Task>
     */
    public function findByHouseholdId(string $householdId): array;
}
