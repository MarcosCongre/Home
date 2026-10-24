<?php

declare(strict_types=1);

namespace App\Application\Catalog\UpdateCategory;

readonly class UpdateCategoryCommand
{
    public function __construct(
        public int $categoryId,
        public string $householdId,
        public string $name,
        public ?string $color
    ) {
    }
}
