<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Catalog;

use App\Application\Catalog\CreateRecurrenceType\CreateRecurrenceTypeCommand;
use App\Application\Catalog\CreateRecurrenceType\CreateRecurrenceTypeHandler;
use App\Application\Catalog\DeleteRecurrenceType\DeleteRecurrenceTypeCommand;
use App\Application\Catalog\DeleteRecurrenceType\DeleteRecurrenceTypeHandler;
use App\Application\Catalog\ListRecurrenceTypes\ListRecurrenceTypesHandler;
use App\Application\Catalog\ListRecurrenceTypes\ListRecurrenceTypesQuery;
use App\Application\Catalog\UpdateRecurrenceType\UpdateRecurrenceTypeCommand;
use App\Application\Catalog\UpdateRecurrenceType\UpdateRecurrenceTypeHandler;
use App\Domain\Tasks\Catalog\CatalogEntryNotFoundException;
use App\Domain\Tasks\Catalog\RecurrenceType;
use App\Infrastructure\Persistence\InMemoryRecurrenceTypeRepository;
use PHPUnit\Framework\TestCase;

final class RecurrenceTypeHandlersTest extends TestCase
{
    private InMemoryRecurrenceTypeRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new InMemoryRecurrenceTypeRepository();
    }

    public function testListingANewHouseholdSeedsTheDefaultRecurrencesOnce(): void
    {
        $handler = new ListRecurrenceTypesHandler($this->repository);

        $first = $handler->handle(new ListRecurrenceTypesQuery('house-1'));
        $handler->handle(new ListRecurrenceTypesQuery('house-1'));

        $this->assertSame(
            ['Daily' => 1, 'Mon & Thu' => 3, 'Tue & Fri' => 3, 'Weekly' => 7, 'Biweekly' => 14, 'Monthly' => 30],
            array_combine(
                array_map(static fn (RecurrenceType $r): string => $r->name(), $first),
                array_map(static fn (RecurrenceType $r): int => $r->intervalDays(), $first)
            )
        );
        $this->assertSame(6, $this->repository->countAllByHouseholdId('house-1'));
        $this->assertSame([], $this->repository->findByHouseholdId('house-2'));
    }

    public function testDeletingEveryRecurrenceDoesNotReseed(): void
    {
        $list = new ListRecurrenceTypesHandler($this->repository);
        $delete = new DeleteRecurrenceTypeHandler($this->repository);

        foreach ($list->handle(new ListRecurrenceTypesQuery('house-1')) as $recurrence) {
            $delete->handle(new DeleteRecurrenceTypeCommand($recurrence->id(), 'house-1'));
        }

        $this->assertSame([], $list->handle(new ListRecurrenceTypesQuery('house-1')));
    }

    public function testCreateRejectsADuplicateNameIgnoringCase(): void
    {
        $handler = new CreateRecurrenceTypeHandler($this->repository);
        $handler->handle(new CreateRecurrenceTypeCommand('house-1', 'Every 10 days', 10));

        $this->expectException(\InvalidArgumentException::class);
        $handler->handle(new CreateRecurrenceTypeCommand('house-1', 'EVERY 10 DAYS', 10));
    }

    public function testCreateRejectsAnOutOfRangeInterval(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new CreateRecurrenceTypeHandler($this->repository))->handle(new CreateRecurrenceTypeCommand('house-1', 'Never', 0));
    }

    public function testUpdateChangesNameAndInterval(): void
    {
        $created = (new CreateRecurrenceTypeHandler($this->repository))->handle(new CreateRecurrenceTypeCommand('house-1', 'Monthly', 30));

        $updated = (new UpdateRecurrenceTypeHandler($this->repository))->handle(
            new UpdateRecurrenceTypeCommand($created->id(), 'house-1', 'Every four weeks', 28)
        );

        $this->assertSame('Every four weeks', $updated->name());
        $this->assertSame(28, $this->repository->findById($created->id())?->intervalDays());
    }

    public function testUpdateRejectsANameTakenByAnotherRecurrence(): void
    {
        $create = new CreateRecurrenceTypeHandler($this->repository);
        $create->handle(new CreateRecurrenceTypeCommand('house-1', 'Weekly', 7));
        $monthly = $create->handle(new CreateRecurrenceTypeCommand('house-1', 'Monthly', 30));

        $this->expectException(\InvalidArgumentException::class);
        (new UpdateRecurrenceTypeHandler($this->repository))->handle(new UpdateRecurrenceTypeCommand($monthly->id(), 'house-1', 'weekly', 7));
    }

    public function testUpdateOfAnotherHouseholdsRecurrenceIsNotFound(): void
    {
        $created = (new CreateRecurrenceTypeHandler($this->repository))->handle(new CreateRecurrenceTypeCommand('house-1', 'Weekly', 7));

        $this->expectException(CatalogEntryNotFoundException::class);
        (new UpdateRecurrenceTypeHandler($this->repository))->handle(new UpdateRecurrenceTypeCommand($created->id(), 'house-2', 'Weekly', 7));
    }

    public function testDeleteOfAnUnknownRecurrenceIsNotFound(): void
    {
        $this->expectException(CatalogEntryNotFoundException::class);

        (new DeleteRecurrenceTypeHandler($this->repository))->handle(new DeleteRecurrenceTypeCommand(99, 'house-1'));
    }
}
