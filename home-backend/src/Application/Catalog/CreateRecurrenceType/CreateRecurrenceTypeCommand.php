<?php

declare(strict_types=1);

namespace App\Application\Catalog\CreateRecurrenceType;

readonly class CreateRecurrenceTypeCommand
{
    public function __construct(
        public string $householdId,
        public string $name,
        public int $intervalDays
    ) {
    }
}
