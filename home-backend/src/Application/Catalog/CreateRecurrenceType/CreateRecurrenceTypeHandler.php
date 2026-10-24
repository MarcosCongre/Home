<?php

declare(strict_types=1);

namespace App\Application\Catalog\CreateRecurrenceType;

use App\Domain\Tasks\Catalog\CatalogName;
use App\Domain\Tasks\Catalog\RecurrenceType;
use App\Domain\Tasks\Catalog\RecurrenceTypeRepositoryInterface;
use DateTimeImmutable;

final class CreateRecurrenceTypeHandler
{
    public function __construct(
        private readonly RecurrenceTypeRepositoryInterface $recurrenceTypeRepository
    ) {
    }

    public function handle(CreateRecurrenceTypeCommand $command): RecurrenceType
    {
        $recurrenceType = RecurrenceType::create(0, $command->householdId, $command->name, $command->intervalDays, new DateTimeImmutable());

        foreach ($this->recurrenceTypeRepository->findByHouseholdId($recurrenceType->householdId()) as $existing) {
            if (CatalogName::equals($existing->name(), $recurrenceType->name())) {
                throw new \InvalidArgumentException(sprintf('A recurrence type named "%s" already exists.', $recurrenceType->name()));
            }
        }

        $this->recurrenceTypeRepository->save($recurrenceType);

        return $recurrenceType;
    }
}
