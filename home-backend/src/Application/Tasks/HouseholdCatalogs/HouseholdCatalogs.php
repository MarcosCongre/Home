<?php

declare(strict_types=1);

namespace App\Application\Tasks\HouseholdCatalogs;

use App\Domain\Tasks\Catalog\CategoryRepositoryInterface;
use App\Domain\Tasks\Catalog\RecurrenceType;
use App\Domain\Tasks\Catalog\RecurrenceTypeRepositoryInterface;

/**
 * A household's active catalog entries, loaded once per request (one read per catalog)
 * so tasks can be resolved without a query per task. Soft-deleted entries are not
 * loaded, so a task that still points at one resolves to null.
 */
final class HouseholdCatalogs
{
    /**
     * @param array<int, string> $categoryNames
     * @param array<int, RecurrenceType> $recurrenceTypes
     */
    private function __construct(
        private readonly array $categoryNames,
        private readonly array $recurrenceTypes
    ) {
    }

    public static function load(
        ?CategoryRepositoryInterface $categoryRepository,
        ?RecurrenceTypeRepositoryInterface $recurrenceTypeRepository,
        string $householdId
    ): self {
        $categoryNames = [];
        foreach ($categoryRepository?->findByHouseholdId($householdId) ?? [] as $category) {
            $categoryNames[$category->id()] = $category->name();
        }

        $recurrenceTypes = [];
        foreach ($recurrenceTypeRepository?->findByHouseholdId($householdId) ?? [] as $recurrenceType) {
            $recurrenceTypes[$recurrenceType->id()] = $recurrenceType;
        }

        return new self($categoryNames, $recurrenceTypes);
    }

    public function categoryName(?int $categoryId): ?string
    {
        return $categoryId === null ? null : ($this->categoryNames[$categoryId] ?? null);
    }

    public function recurrenceName(?int $recurrenceId): ?string
    {
        return $recurrenceId === null ? null : ($this->recurrenceTypes[$recurrenceId] ?? null)?->name();
    }

    /**
     * @return array<int, int> interval in days keyed by recurrence type id
     */
    public function intervalDaysByRecurrenceId(): array
    {
        return array_map(static fn (RecurrenceType $type): int => $type->intervalDays(), $this->recurrenceTypes);
    }
}
