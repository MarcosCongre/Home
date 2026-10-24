<?php

declare(strict_types=1);

namespace App\Application\Catalog\ListCategories;

use App\Application\Shared\ImmediateTransactionRunner;
use App\Application\Shared\TransactionRunner;
use App\Domain\Tasks\Catalog\Category;
use App\Domain\Tasks\Catalog\CategoryRepositoryInterface;
use App\Domain\Tasks\Catalog\DefaultCatalogs;
use DateTimeImmutable;

final class ListCategoriesHandler
{
    private readonly TransactionRunner $transactionRunner;

    public function __construct(
        private readonly CategoryRepositoryInterface $categoryRepository,
        ?TransactionRunner $transactionRunner = null
    ) {
        $this->transactionRunner = $transactionRunner ?? new ImmediateTransactionRunner();
    }

    /**
     * @return array<int, Category>
     */
    public function handle(ListCategoriesQuery $query): array
    {
        if (trim($query->householdId) === '') {
            throw new \InvalidArgumentException('householdId is required.');
        }

        // Seed only a household that never had a category: deleting them all must not bring the defaults back.
        if ($this->categoryRepository->countAllByHouseholdId($query->householdId) === 0) {
            $this->transactionRunner->run(function () use ($query): void {
                $now = new DateTimeImmutable();
                foreach (DefaultCatalogs::CATEGORIES as $name => $color) {
                    $this->categoryRepository->save(Category::create(0, $query->householdId, $name, $color, $now));
                }
            });
        }

        return $this->categoryRepository->findByHouseholdId($query->householdId);
    }
}
