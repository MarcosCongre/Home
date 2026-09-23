<?php

declare(strict_types=1);

namespace App\Domain\Tasks;

interface TaskRepositoryInterface
{
    public function save(Task $task): void;

    public function findById(int $id): ?Task;

    public function delete(int $id): void;

    /**
     * @return array<int, Task>
     */
    public function findByHouseholdId(string $householdId): array;
}
