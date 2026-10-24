<?php

declare(strict_types=1);

namespace App\Application\Catalog\CreateCategory;

readonly class CreateCategoryCommand
{
    public function __construct(
        public string $householdId,
        public string $name,
        public ?string $color
    ) {
    }
}
