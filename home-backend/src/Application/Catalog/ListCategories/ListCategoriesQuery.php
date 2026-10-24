<?php

declare(strict_types=1);

namespace App\Application\Catalog\ListCategories;

readonly class ListCategoriesQuery
{
    public function __construct(
        public string $householdId
    ) {
    }
}
