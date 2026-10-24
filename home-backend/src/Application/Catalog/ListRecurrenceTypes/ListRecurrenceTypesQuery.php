<?php

declare(strict_types=1);

namespace App\Application\Catalog\ListRecurrenceTypes;

readonly class ListRecurrenceTypesQuery
{
    public function __construct(
        public string $householdId
    ) {
    }
}
