<?php

declare(strict_types=1);

namespace App\Application\Catalog\UpdateCategory;

use App\Domain\Tasks\Catalog\CatalogEntryNotFoundException;
use App\Domain\Tasks\Catalog\CatalogName;
use App\Domain\Tasks\Catalog\Category;
use App\Domain\Tasks\Catalog\CategoryRepositoryInterface;
use DateTimeImmutable;

final class UpdateCategoryHandler
{
    public function __construct(
        private readonly CategoryRepositoryInterface $categoryRepository
    ) {
    }

    public function handle(UpdateCategoryCommand $command): Category
    {
        $category = $this->categoryRepository->findById($command->categoryId);

        if ($category === null || $category->householdId() !== $command->householdId) {
            throw CatalogEntryNotFoundException::category($command->categoryId, $command->householdId);
        }

        $category->update($command->name, $command->color, new DateTimeImmutable());

        foreach ($this->categoryRepository->findByHouseholdId($category->householdId()) as $existing) {
            if ($existing->id() !== $category->id() && CatalogName::equals($existing->name(), $category->name())) {
                throw new \InvalidArgumentException(sprintf('A category named "%s" already exists.', $category->name()));
            }
        }

        $this->categoryRepository->save($category);

        return $category;
    }
}
