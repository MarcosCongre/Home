<?php

declare(strict_types=1);

namespace App\Application\Catalog\DeleteRecurrenceType;

use App\Domain\Tasks\Catalog\CatalogEntryNotFoundException;
use App\Domain\Tasks\Catalog\RecurrenceTypeRepositoryInterface;
use DateTimeImmutable;

final class DeleteRecurrenceTypeHandler
{
    public function __construct(
        private readonly RecurrenceTypeRepositoryInterface $recurrenceTypeRepository
    ) {
    }

    public function handle(DeleteRecurrenceTypeCommand $command): void
    {
        // Already-deleted recurrence types are not returned, so they are reported as not found too.
        $recurrenceType = $this->recurrenceTypeRepository->findById($command->recurrenceTypeId);

        if ($recurrenceType === null || $recurrenceType->householdId() !== $command->householdId) {
            throw CatalogEntryNotFoundException::recurrenceType($command->recurrenceTypeId, $command->householdId);
        }

        $recurrenceType->delete(new DateTimeImmutable());
        $this->recurrenceTypeRepository->save($recurrenceType);
    }
}
