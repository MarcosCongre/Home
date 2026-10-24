<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Application\Catalog\CreateCategory\CreateCategoryCommand;
use App\Application\Catalog\CreateCategory\CreateCategoryHandler;
use App\Application\Catalog\DeleteCategory\DeleteCategoryCommand;
use App\Application\Catalog\DeleteCategory\DeleteCategoryHandler;
use App\Application\Catalog\ListCategories\ListCategoriesHandler;
use App\Application\Catalog\ListCategories\ListCategoriesQuery;
use App\Application\Catalog\UpdateCategory\UpdateCategoryCommand;
use App\Application\Catalog\UpdateCategory\UpdateCategoryHandler;
use App\Application\Shared\TransactionRunner;
use App\Domain\Tasks\Catalog\Category;
use App\Domain\Tasks\Catalog\CategoryRepositoryInterface;

final class CategoryController
{
    public function __construct(
        private CategoryRepositoryInterface $categoryRepository,
        private ?TransactionRunner $transactionRunner = null
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function create(array $payload): array
    {
        $handler = new CreateCategoryHandler($this->categoryRepository);
        $category = $handler->handle(new CreateCategoryCommand(
            householdId: (string) ($payload['householdId'] ?? ''),
            name: (string) ($payload['name'] ?? ''),
            color: isset($payload['color']) ? (string) $payload['color'] : null
        ));

        return $this->serializeCategory($category);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function update(int $categoryId, string $householdId, array $payload): array
    {
        $handler = new UpdateCategoryHandler($this->categoryRepository);
        $category = $handler->handle(new UpdateCategoryCommand(
            categoryId: $categoryId,
            householdId: $householdId,
            name: (string) ($payload['name'] ?? ''),
            color: isset($payload['color']) ? (string) $payload['color'] : null
        ));

        return $this->serializeCategory($category);
    }

    public function delete(int $categoryId, string $householdId): void
    {
        (new DeleteCategoryHandler($this->categoryRepository))->handle(new DeleteCategoryCommand($categoryId, $householdId));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(string $householdId): array
    {
        $handler = new ListCategoriesHandler($this->categoryRepository, $this->transactionRunner);
        $categories = $handler->handle(new ListCategoriesQuery($householdId));

        return array_map(fn (Category $category): array => $this->serializeCategory($category), $categories);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeCategory(Category $category): array
    {
        return [
            'id' => $category->id(),
            'householdId' => $category->householdId(),
            'name' => $category->name(),
            'color' => $category->color(),
        ];
    }
}
