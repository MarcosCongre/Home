<?php

declare(strict_types=1);

namespace App\Application\Catalog\UpdateRecurrenceType;

use App\Domain\Tasks\Catalog\CatalogEntryNotFoundException;
use App\Domain\Tasks\Catalog\CatalogName;
use App\Domain\Tasks\Catalog\RecurrenceType;
use App\Domain\Tasks\Catalog\RecurrenceTypeRepositoryInterface;
use DateTimeImmutable;

final class UpdateRecurrenceTypeHandler
{
    public function __construct(
        private readonly RecurrenceTypeRepositoryInterface $recurrenceTypeRepository
    ) {
    }

    public function handle(UpdateRecurrenceTypeCommand $command): RecurrenceType
    {
        $recurrenceType = $this->recurrenceTypeRepository->findById($command->recurrenceTypeId);

        if ($recurrenceType === null || $recurrenceType->householdId() !== $command->householdId) {
            throw CatalogEntryNotFoundException::recurrenceType($command->recurrenceTypeId, $command->householdId);
        }

        $recurrenceType->update($command->name, $command->intervalDays, new DateTimeImmutable());

        foreach ($this->recurrenceTypeRepository->findByHouseholdId($recurrenceType->householdId()) as $existing) {
            if ($existing->id() !== $recurrenceType->id() && CatalogName::equals($existing->name(), $recurrenceType->name())) {
                throw new \InvalidArgumentException(sprintf('A recurrence type named "%s" already exists.', $recurrenceType->name()));
            }
        }

        $this->recurrenceTypeRepository->save($recurrenceType);

        return $recurrenceType;
    }
}
