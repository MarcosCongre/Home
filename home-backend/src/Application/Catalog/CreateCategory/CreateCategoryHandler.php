<?php

declare(strict_types=1);

namespace App\Application\Catalog\CreateCategory;

use App\Domain\Tasks\Catalog\CatalogName;
use App\Domain\Tasks\Catalog\Category;
use App\Domain\Tasks\Catalog\CategoryRepositoryInterface;
use DateTimeImmutable;

final class CreateCategoryHandler
{
    public function __construct(
        private readonly CategoryRepositoryInterface $categoryRepository
    ) {
    }

    public function handle(CreateCategoryCommand $command): Category
    {
        $category = Category::create(0, $command->householdId, $command->name, $command->color, new DateTimeImmutable());

        foreach ($this->categoryRepository->findByHouseholdId($category->householdId()) as $existing) {
            if (CatalogName::equals($existing->name(), $category->name())) {
                throw new \InvalidArgumentException(sprintf('A category named "%s" already exists.', $category->name()));
            }
        }

        $this->categoryRepository->save($category);

        return $category;
    }
}
