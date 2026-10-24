<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Tasks;

use App\Application\Tasks\CatalogGuard\CatalogGuard;
use App\Application\Tasks\CreateTask\CreateTaskCommand;
use App\Application\Tasks\CreateTask\CreateTaskHandler;
use App\Application\Tasks\UpdateTask\UpdateTaskCommand;
use App\Application\Tasks\UpdateTask\UpdateTaskHandler;
use App\Domain\Tasks\Catalog\Category;
use App\Domain\Tasks\Catalog\RecurrenceType;
use App\Domain\Tasks\Task;
use App\Infrastructure\Persistence\InMemoryCategoryRepository;
use App\Infrastructure\Persistence\InMemoryRecurrenceTypeRepository;
use App\Infrastructure\Persistence\InMemoryTaskRepository;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(CatalogGuard::class)]
#[CoversClass(CreateTaskHandler::class)]
#[CoversClass(UpdateTaskHandler::class)]
final class TaskCatalogReferencesTest extends TestCase
{
    // Fixture ids: 1 = active in house-1, 2 = deleted in house-1, 3 = active in house-2.
    private const ACTIVE = 1;
    private const DELETED = 2;
    private const FOREIGN = 3;

    private InMemoryTaskRepository $tasks;
    private InMemoryCategoryRepository $categories;
    private InMemoryRecurrenceTypeRepository $recurrenceTypes;

    protected function setUp(): void
    {
        $now = new DateTimeImmutable('2026-10-24 10:00:00');
        $this->tasks = new InMemoryTaskRepository();
        $this->categories = new InMemoryCategoryRepository();
        $this->recurrenceTypes = new InMemoryRecurrenceTypeRepository();

        $this->categories->save(Category::create(0, 'house-1', 'Cleaning', null, $now));
        $deletedCategory = Category::create(0, 'house-1', 'Old', null, $now);
        $deletedCategory->delete($now);
        $this->categories->save($deletedCategory);
        $this->categories->save(Category::create(0, 'house-2', 'Garden', null, $now));

        $this->recurrenceTypes->save(RecurrenceType::create(0, 'house-1', 'Weekly', 7, $now));
        $deletedRecurrence = RecurrenceType::create(0, 'house-1', 'Old', 3, $now);
        $deletedRecurrence->delete($now);
        $this->recurrenceTypes->save($deletedRecurrence);
        $this->recurrenceTypes->save(RecurrenceType::create(0, 'house-2', 'Daily', 1, $now));
    }

    public function testItCreatesATaskReferencingCatalogEntriesOfItsHousehold(): void
    {
        $task = $this->createHandler()->handle(new CreateTaskCommand(
            title: 'Mop floors',
            householdId: 'house-1',
            userId: 'user-1',
            categoryId: self::ACTIVE,
            recurrenceId: self::ACTIVE
        ));

        $this->assertSame(self::ACTIVE, $task->categoryId());
        $this->assertSame(self::ACTIVE, $task->recurrenceId());
        $this->assertSame(self::ACTIVE, $this->tasks->findById($task->id())?->categoryId());
    }

    public function testItCreatesATaskWithoutCatalogReferences(): void
    {
        $task = $this->createHandler()->handle(new CreateTaskCommand(title: 'Mop floors', householdId: 'house-1', userId: 'user-1'));

        $this->assertNull($task->categoryId());
        $this->assertNull($task->recurrenceId());
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function invalidReferences(): array
    {
        return [
            'unknown category' => ['categoryId', 99],
            'deleted category' => ['categoryId', self::DELETED],
            'foreign category' => ['categoryId', self::FOREIGN],
            'unknown recurrence' => ['recurrenceId', 99],
            'deleted recurrence' => ['recurrenceId', self::DELETED],
            'foreign recurrence' => ['recurrenceId', self::FOREIGN],
        ];
    }

    #[DataProvider('invalidReferences')]
    public function testCreateRejectsAnInvalidReferenceAndSavesNothing(string $field, int $id): void
    {
        try {
            $this->createHandler()->handle(new CreateTaskCommand(
                title: 'Mop floors',
                householdId: 'house-1',
                userId: 'user-1',
                categoryId: $field === 'categoryId' ? $id : null,
                recurrenceId: $field === 'recurrenceId' ? $id : null
            ));
            $this->fail('An invalid catalog reference must be rejected.');
        } catch (\InvalidArgumentException) {
        }

        $this->assertSame([], $this->tasks->findByHouseholdId('house-1'));
    }

    #[DataProvider('invalidReferences')]
    public function testUpdateRejectsAnInvalidReferenceAndChangesNothing(string $field, int $id): void
    {
        $task = $this->storedTask(categoryId: self::ACTIVE, recurrenceId: self::ACTIVE);

        try {
            $this->updateHandler()->handle(new UpdateTaskCommand(
                taskId: $task->id(),
                title: 'Renamed',
                categoryId: $field === 'categoryId' ? $id : null,
                recurrenceId: $field === 'recurrenceId' ? $id : null
            ));
            $this->fail('An invalid catalog reference must be rejected.');
        } catch (\InvalidArgumentException) {
        }

        $stored = $this->tasks->findById($task->id());
        $this->assertSame('Mop floors', $stored?->title()->value());
        $this->assertSame(self::ACTIVE, $stored?->categoryId());
        $this->assertSame(self::ACTIVE, $stored?->recurrenceId());
    }

    public function testUpdateKeepsReferencesThatAreAbsent(): void
    {
        $task = $this->storedTask(categoryId: self::ACTIVE, recurrenceId: self::ACTIVE);

        $updated = $this->updateHandler()->handle(new UpdateTaskCommand(taskId: $task->id(), title: 'Renamed'));

        $this->assertSame(self::ACTIVE, $updated->categoryId());
        $this->assertSame(self::ACTIVE, $updated->recurrenceId());
    }

    public function testUpdateClearsReferencesThatAreExplicitlyNull(): void
    {
        $task = $this->storedTask(categoryId: self::ACTIVE, recurrenceId: self::ACTIVE);

        $updated = $this->updateHandler()->handle(new UpdateTaskCommand(
            taskId: $task->id(),
            title: 'Renamed',
            clearCategory: true,
            clearRecurrence: true
        ));

        $this->assertNull($updated->categoryId());
        $this->assertNull($updated->recurrenceId());
        $this->assertNull($this->tasks->findById($task->id())?->recurrenceId());
    }

    public function testUpdateSetsReferences(): void
    {
        $task = $this->storedTask();

        $updated = $this->updateHandler()->handle(new UpdateTaskCommand(
            taskId: $task->id(),
            title: 'Mop floors',
            categoryId: self::ACTIVE,
            recurrenceId: self::ACTIVE
        ));

        $this->assertSame(self::ACTIVE, $updated->categoryId());
        $this->assertSame(self::ACTIVE, $updated->recurrenceId());
    }

    public function testTheGuardRejectsReferencesWhenNoCatalogIsAvailable(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new CatalogGuard(null, null))->ensureReferences(1, null, 'house-1');
    }

    public function testTheGuardAcceptsNoReferencesWithoutCatalogs(): void
    {
        (new CatalogGuard(null, null))->ensureReferences(null, null, 'house-1');

        $this->addToAssertionCount(1);
    }

    private function createHandler(): CreateTaskHandler
    {
        return new CreateTaskHandler(
            $this->tasks,
            categoryRepository: $this->categories,
            recurrenceTypeRepository: $this->recurrenceTypes
        );
    }

    private function updateHandler(): UpdateTaskHandler
    {
        return new UpdateTaskHandler(
            $this->tasks,
            categoryRepository: $this->categories,
            recurrenceTypeRepository: $this->recurrenceTypes
        );
    }

    private function storedTask(?int $categoryId = null, ?int $recurrenceId = null): Task
    {
        $task = Task::create(
            id: 0,
            title: 'Mop floors',
            householdId: 'house-1',
            createdAt: new DateTimeImmutable('2026-10-24 09:00:00'),
            categoryId: $categoryId,
            recurrenceId: $recurrenceId
        );
        $this->tasks->save($task);

        return $task;
    }
}
