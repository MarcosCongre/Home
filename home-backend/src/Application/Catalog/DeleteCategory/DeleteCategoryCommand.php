<?php

declare(strict_types=1);

namespace App\Application\Catalog\DeleteCategory;

readonly class DeleteCategoryCommand
{
    public function __construct(
        public int $categoryId,
        public string $householdId
    ) {
    }
}
