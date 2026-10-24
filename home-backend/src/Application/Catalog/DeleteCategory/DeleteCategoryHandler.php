<?php

declare(strict_types=1);

namespace App\Application\Catalog\DeleteCategory;

use App\Domain\Tasks\Catalog\CatalogEntryNotFoundException;
use App\Domain\Tasks\Catalog\CategoryRepositoryInterface;
use DateTimeImmutable;

final class DeleteCategoryHandler
{
    public function __construct(
        private readonly CategoryRepositoryInterface $categoryRepository
    ) {
    }

    public function handle(DeleteCategoryCommand $command): void
    {
        // Already-deleted categories are not returned, so they are reported as not found too.
        $category = $this->categoryRepository->findById($command->categoryId);

        if ($category === null || $category->householdId() !== $command->householdId) {
            throw CatalogEntryNotFoundException::category($command->categoryId, $command->householdId);
        }

        $category->delete(new DateTimeImmutable());
        $this->categoryRepository->save($category);
    }
}
