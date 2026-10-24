<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Tasks\Catalog\RecurrenceType;
use App\Domain\Tasks\Catalog\RecurrenceTypeRepositoryInterface;

final class InMemoryRecurrenceTypeRepository implements RecurrenceTypeRepositoryInterface
{
    /** @var array<int, RecurrenceType> */
    private array $recurrenceTypes = [];

    public function save(RecurrenceType $recurrenceType): void
    {
        if ($recurrenceType->id() === 0) {
            $recurrenceType->assignGeneratedId($this->recurrenceTypes === [] ? 1 : max(array_keys($this->recurrenceTypes)) + 1);
        }

        $this->recurrenceTypes[$recurrenceType->id()] = $recurrenceType;
    }

    public function findById(int $id): ?RecurrenceType
    {
        $recurrenceType = $this->recurrenceTypes[$id] ?? null;

        return $recurrenceType === null || $recurrenceType->isDeleted() ? null : $recurrenceType;
    }

    /**
     * @return array<int, RecurrenceType>
     */
    public function findByHouseholdId(string $householdId): array
    {
        $recurrenceTypes = array_values(array_filter(
            $this->recurrenceTypes,
            static fn (RecurrenceType $type): bool => $type->householdId() === $householdId && !$type->isDeleted()
        ));

        usort($recurrenceTypes, static fn (RecurrenceType $a, RecurrenceType $b): int => $a->id() <=> $b->id());

        return $recurrenceTypes;
    }

    public function countAllByHouseholdId(string $householdId): int
    {
        return count(array_filter(
            $this->recurrenceTypes,
            static fn (RecurrenceType $type): bool => $type->householdId() === $householdId
        ));
    }
}
