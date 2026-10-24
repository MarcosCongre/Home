<?php

declare(strict_types=1);

namespace App\Domain\Tasks\Catalog;

// Categories are soft-deleted (saved with a deletedAt); reads never return them.
interface CategoryRepositoryInterface
{
    public function save(Category $category): void;

    public function findById(int $id): ?Category;

    /**
     * @return array<int, Category>
     */
    public function findByHouseholdId(string $householdId): array;

    // Counts every row of the household, soft-deleted ones included.
    public function countAllByHouseholdId(string $householdId): int;
}
