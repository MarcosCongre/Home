<?php

declare(strict_types=1);

namespace Tests\Integration\Persistence;

use App\Domain\Tasks\Catalog\Category;
use App\Infrastructure\Persistence\PdoCategoryRepository;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class PdoCategoryRepositoryTest extends TestCase
{
    private PdoCategoryRepository $repository;

    protected function setUp(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->repository = new PdoCategoryRepository($pdo);
    }

    public function testItInsertsUpdatesAndReadsCategories(): void
    {
        $category = Category::create(0, 'house-1', 'Errands', null, new DateTimeImmutable('2026-10-24 10:00:00'));
        $this->repository->save($category);
        $this->assertGreaterThan(0, $category->id());

        $category->update('Chores', '#3A8FB5', new DateTimeImmutable('2026-10-25 11:00:00'));
        $this->repository->save($category);

        $found = $this->repository->findById($category->id());
        $this->assertNotNull($found);
        $this->assertSame('Chores', $found->name());
        $this->assertSame('#3A8FB5', $found->color());
        $this->assertSame('house-1', $found->householdId());
        $this->assertSame('2026-10-24 10:00:00', $found->createdAt()->format('Y-m-d H:i:s'));
        $this->assertSame('2026-10-25 11:00:00', $found->updatedAt()->format('Y-m-d H:i:s'));
        $this->assertCount(1, $this->repository->findByHouseholdId('house-1'));
    }

    public function testSoftDeletedCategoriesAreHiddenButStillCounted(): void
    {
        $kept = Category::create(0, 'house-1', 'Errands', null, new DateTimeImmutable());
        $removed = Category::create(0, 'house-1', 'Bills', '#B53A3A', new DateTimeImmutable());
        $this->repository->save($kept);
        $this->repository->save($removed);

        $removed->delete(new DateTimeImmutable());
        $this->repository->save($removed);

        $this->assertNull($this->repository->findById($removed->id()));
        $this->assertSame([$kept->id()], array_map(static fn (Category $c): int => $c->id(), $this->repository->findByHouseholdId('house-1')));
        $this->assertSame(2, $this->repository->countAllByHouseholdId('house-1'));
    }

    public function testCategoriesAreScopedToTheirHouseholdAndKeepInsertionOrder(): void
    {
        foreach (['Waste', 'Bills', 'Kitchen'] as $name) {
            $this->repository->save(Category::create(0, 'house-1', $name, null, new DateTimeImmutable()));
        }
        $this->repository->save(Category::create(0, 'house-2', 'Garden', null, new DateTimeImmutable()));

        $this->assertSame(['Waste', 'Bills', 'Kitchen'], array_map(static fn (Category $c): string => $c->name(), $this->repository->findByHouseholdId('house-1')));
        $this->assertSame(1, $this->repository->countAllByHouseholdId('house-2'));
        $this->assertSame(0, $this->repository->countAllByHouseholdId('house-3'));
    }
}
