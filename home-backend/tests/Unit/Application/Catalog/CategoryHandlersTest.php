<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Catalog;

use App\Application\Catalog\CreateCategory\CreateCategoryCommand;
use App\Application\Catalog\CreateCategory\CreateCategoryHandler;
use App\Application\Catalog\DeleteCategory\DeleteCategoryCommand;
use App\Application\Catalog\DeleteCategory\DeleteCategoryHandler;
use App\Application\Catalog\ListCategories\ListCategoriesHandler;
use App\Application\Catalog\ListCategories\ListCategoriesQuery;
use App\Application\Catalog\UpdateCategory\UpdateCategoryCommand;
use App\Application\Catalog\UpdateCategory\UpdateCategoryHandler;
use App\Domain\Tasks\Catalog\CatalogEntryNotFoundException;
use App\Domain\Tasks\Catalog\Category;
use App\Domain\Tasks\Catalog\DefaultCatalogs;
use App\Infrastructure\Persistence\InMemoryCategoryRepository;
use PHPUnit\Framework\TestCase;

final class CategoryHandlersTest extends TestCase
{
    private InMemoryCategoryRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new InMemoryCategoryRepository();
    }

    public function testListingANewHouseholdSeedsTheDefaultCategoriesOnce(): void
    {
        $handler = new ListCategoriesHandler($this->repository);

        $first = $handler->handle(new ListCategoriesQuery('house-1'));
        $second = $handler->handle(new ListCategoriesQuery('house-1'));

        $this->assertSame(array_keys(DefaultCatalogs::CATEGORIES), array_map(static fn (Category $c): string => $c->name(), $first));
        $this->assertSame('#C4623A', $first[0]->color());
        $this->assertCount(count(DefaultCatalogs::CATEGORIES), $second);
        $this->assertSame(count(DefaultCatalogs::CATEGORIES), $this->repository->countAllByHouseholdId('house-1'));
    }

    public function testDeletingEveryCategoryDoesNotReseed(): void
    {
        $list = new ListCategoriesHandler($this->repository);
        $delete = new DeleteCategoryHandler($this->repository);

        foreach ($list->handle(new ListCategoriesQuery('house-1')) as $category) {
            $delete->handle(new DeleteCategoryCommand($category->id(), 'house-1'));
        }

        $this->assertSame([], $list->handle(new ListCategoriesQuery('house-1')));
    }

    public function testAHouseholdThatAlreadyCreatedACategoryIsNotSeeded(): void
    {
        (new CreateCategoryHandler($this->repository))->handle(new CreateCategoryCommand('house-1', 'Errands', null));

        $listed = (new ListCategoriesHandler($this->repository))->handle(new ListCategoriesQuery('house-1'));

        $this->assertCount(1, $listed);
        $this->assertSame('Errands', $listed[0]->name());
    }

    public function testListingRequiresAHouseholdId(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new ListCategoriesHandler($this->repository))->handle(new ListCategoriesQuery(''));
    }

    public function testCreateRejectsANameAlreadyUsedInTheHouseholdIgnoringCase(): void
    {
        $handler = new CreateCategoryHandler($this->repository);
        $handler->handle(new CreateCategoryCommand('house-1', 'Errands', null));

        $this->expectException(\InvalidArgumentException::class);
        $handler->handle(new CreateCategoryCommand('house-1', ' errands ', null));
    }

    public function testTheSameNameIsAllowedInAnotherHouseholdAndAfterDeletion(): void
    {
        $create = new CreateCategoryHandler($this->repository);
        $errands = $create->handle(new CreateCategoryCommand('house-1', 'Errands', null));
        $other = $create->handle(new CreateCategoryCommand('house-2', 'Errands', null));

        (new DeleteCategoryHandler($this->repository))->handle(new DeleteCategoryCommand($errands->id(), 'house-1'));
        $again = $create->handle(new CreateCategoryCommand('house-1', 'ERRANDS', '#3A8FB5'));

        $this->assertNotSame($errands->id(), $other->id());
        $this->assertSame('ERRANDS', $again->name());
    }

    public function testUpdateRenamesAndRecolorsTheCategory(): void
    {
        $created = (new CreateCategoryHandler($this->repository))->handle(new CreateCategoryCommand('house-1', 'Errands', null));

        $updated = (new UpdateCategoryHandler($this->repository))->handle(
            new UpdateCategoryCommand($created->id(), 'house-1', 'Chores', '#3A8FB5')
        );

        $this->assertSame('Chores', $updated->name());
        $this->assertSame('#3A8FB5', $this->repository->findById($created->id())?->color());
    }

    public function testUpdateMayKeepTheSameNameWithDifferentCase(): void
    {
        $created = (new CreateCategoryHandler($this->repository))->handle(new CreateCategoryCommand('house-1', 'Errands', null));

        $updated = (new UpdateCategoryHandler($this->repository))->handle(
            new UpdateCategoryCommand($created->id(), 'house-1', 'errands', null)
        );

        $this->assertSame('errands', $updated->name());
    }

    public function testUpdateRejectsANameTakenByAnotherCategory(): void
    {
        $create = new CreateCategoryHandler($this->repository);
        $create->handle(new CreateCategoryCommand('house-1', 'Errands', null));
        $chores = $create->handle(new CreateCategoryCommand('house-1', 'Chores', null));

        $this->expectException(\InvalidArgumentException::class);
        (new UpdateCategoryHandler($this->repository))->handle(new UpdateCategoryCommand($chores->id(), 'house-1', 'ERRANDS', null));
    }

    public function testUpdateOfAnotherHouseholdsCategoryIsNotFound(): void
    {
        $created = (new CreateCategoryHandler($this->repository))->handle(new CreateCategoryCommand('house-1', 'Errands', null));

        $this->expectException(CatalogEntryNotFoundException::class);
        (new UpdateCategoryHandler($this->repository))->handle(new UpdateCategoryCommand($created->id(), 'house-2', 'Hijacked', null));
    }

    public function testDeleteOfAnUnknownOrAlreadyDeletedCategoryIsNotFound(): void
    {
        $created = (new CreateCategoryHandler($this->repository))->handle(new CreateCategoryCommand('house-1', 'Errands', null));
        $delete = new DeleteCategoryHandler($this->repository);
        $delete->handle(new DeleteCategoryCommand($created->id(), 'house-1'));

        $this->expectException(CatalogEntryNotFoundException::class);
        $delete->handle(new DeleteCategoryCommand($created->id(), 'house-1'));
    }

    public function testDeleteOfAnotherHouseholdsCategoryIsNotFoundAndKeepsIt(): void
    {
        $created = (new CreateCategoryHandler($this->repository))->handle(new CreateCategoryCommand('house-1', 'Errands', null));

        try {
            (new DeleteCategoryHandler($this->repository))->handle(new DeleteCategoryCommand($created->id(), 'house-2'));
            $this->fail('Expected the category to be reported as not found.');
        } catch (CatalogEntryNotFoundException) {
        }

        $this->assertNotNull($this->repository->findById($created->id()));
    }
}
