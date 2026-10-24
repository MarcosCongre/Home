<?php

declare(strict_types=1);

namespace App\Application\Catalog\DeleteRecurrenceType;

readonly class DeleteRecurrenceTypeCommand
{
    public function __construct(
        public int $recurrenceTypeId,
        public string $householdId
    ) {
    }
}
