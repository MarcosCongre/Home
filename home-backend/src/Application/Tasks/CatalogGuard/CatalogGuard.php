<?php

declare(strict_types=1);

namespace App\Application\Tasks\CatalogGuard;

use App\Domain\Tasks\Catalog\CategoryRepositoryInterface;
use App\Domain\Tasks\Catalog\RecurrenceTypeRepositoryInterface;

// Catalog entries are soft-deleted, so the foreign keys alone do not reject a deleted
// or foreign entry; the repository read (which hides deleted rows) plus the household check does.
final class CatalogGuard
{
    public function __construct(
        private readonly ?CategoryRepositoryInterface $categoryRepository,
        private readonly ?RecurrenceTypeRepositoryInterface $recurrenceTypeRepository
    ) {
    }

    /**
     * Null means "no reference" and is always accepted. Without a catalog repository
     * a reference cannot be checked, so it is rejected rather than trusted.
     *
     * @throws \InvalidArgumentException when an entry is unknown, deleted, or in another household
     */
    public function ensureReferences(?int $categoryId, ?int $recurrenceId, string $householdId): void
    {
        if ($categoryId !== null) {
            $category = $this->categoryRepository?->findById($categoryId);
            if ($category === null || $category->householdId() !== $householdId) {
                throw new \InvalidArgumentException('Category does not exist in this household.');
            }
        }

        if ($recurrenceId !== null) {
            $recurrenceType = $this->recurrenceTypeRepository?->findById($recurrenceId);
            if ($recurrenceType === null || $recurrenceType->householdId() !== $householdId) {
                throw new \InvalidArgumentException('Recurrence type does not exist in this household.');
            }
        }
    }
}
