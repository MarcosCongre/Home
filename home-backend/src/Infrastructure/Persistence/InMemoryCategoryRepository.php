<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Tasks\Catalog\Category;
use App\Domain\Tasks\Catalog\CategoryRepositoryInterface;

final class InMemoryCategoryRepository implements CategoryRepositoryInterface
{
    /** @var array<int, Category> */
    private array $categories = [];

    public function save(Category $category): void
    {
        if ($category->id() === 0) {
            $category->assignGeneratedId($this->categories === [] ? 1 : max(array_keys($this->categories)) + 1);
        }

        $this->categories[$category->id()] = $category;
    }

    public function findById(int $id): ?Category
    {
        $category = $this->categories[$id] ?? null;

        return $category === null || $category->isDeleted() ? null : $category;
    }

    /**
     * @return array<int, Category>
     */
    public function findByHouseholdId(string $householdId): array
    {
        $categories = array_values(array_filter(
            $this->categories,
            static fn (Category $category): bool => $category->householdId() === $householdId && !$category->isDeleted()
        ));

        usort($categories, static fn (Category $a, Category $b): int => $a->id() <=> $b->id());

        return $categories;
    }

    public function countAllByHouseholdId(string $householdId): int
    {
        return count(array_filter(
            $this->categories,
            static fn (Category $category): bool => $category->householdId() === $householdId
        ));
    }
}
