<?php

declare(strict_types=1);

namespace App\Domain\Tasks\Catalog;

// Recurrence types are soft-deleted (saved with a deletedAt); reads never return them.
interface RecurrenceTypeRepositoryInterface
{
    public function save(RecurrenceType $recurrenceType): void;

    public function findById(int $id): ?RecurrenceType;

    /**
     * @return array<int, RecurrenceType>
     */
    public function findByHouseholdId(string $householdId): array;

    // Counts every row of the household, soft-deleted ones included.
    public function countAllByHouseholdId(string $householdId): int;
}
