<?php

declare(strict_types=1);

namespace App\Application\Catalog\UpdateRecurrenceType;

readonly class UpdateRecurrenceTypeCommand
{
    public function __construct(
        public int $recurrenceTypeId,
        public string $householdId,
        public string $name,
        public int $intervalDays
    ) {
    }
}
