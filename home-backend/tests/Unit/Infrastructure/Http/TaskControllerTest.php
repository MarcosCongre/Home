<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Http;

use App\Domain\Members\Member;
use App\Domain\Tasks\Catalog\Category;
use App\Domain\Tasks\Catalog\RecurrenceType;
use App\Domain\Tasks\Task;
use App\Infrastructure\Http\TaskController;
use App\Infrastructure\Persistence\InMemoryCategoryRepository;
use App\Infrastructure\Persistence\InMemoryMemberRepository;
use App\Infrastructure\Persistence\InMemoryRecurrenceTypeRepository;
use App\Infrastructure\Persistence\InMemoryTaskRepository;
use PHPUnit\Framework\TestCase;

final class TaskControllerTest extends TestCase
{
    public function testItCreatesATaskFromPayload(): void
    {
        $repository = new InMemoryTaskRepository();
        $controller = new TaskController($repository);

        $task = $controller->create([
            'title' => 'Clean kitchen',
            'householdId' => 'house-1',
            'userId' => 'user-1',
            'day' => 'Mon',
            'time' => '18:30',
            'priority' => 'high',
        ]);

        $this->assertSame('Clean kitchen', $task['title']);
        $this->assertSame('pending', $task['status']);
        $this->assertSame('house-1', $task['householdId']);
        $this->assertSame('Mon', $task['day']);
        $this->assertSame('18:30', $task['time']);
        $this->assertNull($task['categoryId']);
        $this->assertNull($task['category']);
        $this->assertNull($task['recurrenceId']);
        $this->assertNull($task['recurrence']);
        $this->assertSame('high', $task['priority']);
    }

    public function testItUpdatesTaskMetadataFromPayload(): void
    {
        $repository = new InMemoryTaskRepository();
        $task = Task::create(
            id: 10,
            title: 'Vacuum hallway',
            householdId: 'house-2',
            createdAt: new \DateTimeImmutable('2026-09-16 09:00:00')
        );
        $repository->save($task);

        $controller = new TaskController($repository);
        $updated = $controller->update(10, [
            'title' => 'Vacuum hallway',
            'assignedMemberId' => 4,
            'day' => 'Tue',
            'time' => '19:15',
            'priority' => 'low',
        ]);

        $this->assertSame('Tue', $updated['day']);
        $this->assertSame('19:15', $updated['time']);
        $this->assertSame('low', $updated['priority']);
        $this->assertSame(4, $updated['assignedMemberId']);
    }

    public function testItRejectsAnInvalidDay(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $controller = new TaskController(new InMemoryTaskRepository());
        $controller->create([
            'title' => 'Do laundry',
            'householdId' => 'house-3',
            'userId' => 'user-3',
            'day' => 'Funday',
        ]);
    }

    public function testItRejectsAnInvalidPriority(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $controller = new TaskController(new InMemoryTaskRepository());
        $controller->create([
            'title' => 'Do laundry',
            'householdId' => 'house-3',
            'userId' => 'user-3',
            'priority' => 'urgent',
        ]);
    }

    public function testItListsTasksForTheHousehold(): void
    {
        $repository = new InMemoryTaskRepository();
        $first = Task::create(
            id: 1,
            title: 'Water plants',
            householdId: 'house-2',
            createdAt: new \DateTimeImmutable('2026-09-16 08:00:00')
        );
        $second = Task::create(
            id: 2,
            title: 'Vacuum hallway',
            householdId: 'house-2',
            createdAt: new \DateTimeImmutable('2026-09-16 09:00:00')
        );

        $repository->save($first);
        $repository->save($second);

        $controller = new TaskController($repository);
        $tasks = $controller->list('house-2');

        $this->assertCount(2, $tasks);
        $this->assertSame([1, 2], array_map(static fn (array $task): int => $task['id'], $tasks));
    }

    public function testItAutoAssignsHouseholdTasksAndSerializesTheAssignmentDate(): void
    {
        $repository = new InMemoryTaskRepository();
        $repository->save(Task::create(
            id: 30,
            title: 'Mop floors',
            householdId: 'house-7',
            createdAt: new \DateTimeImmutable('2026-09-16 09:00:00'),
            recurrenceId: 1
        ));
        $members = new InMemoryMemberRepository();
        $members->save(Member::create(0, 'Ana', 'A', '#C4963A', 'house-7'));

        $controller = new TaskController($repository, null, $members);
        $tasks = $controller->autoAssign('house-7');

        $this->assertCount(1, $tasks);
        $this->assertSame(30, $tasks[0]['id']);
        $this->assertSame(1, $tasks[0]['assignedMemberId']);
        $this->assertIsString($tasks[0]['lastAssignedAt']);

        $listed = $controller->list('house-7');
        $this->assertSame($tasks[0]['lastAssignedAt'], $listed[0]['lastAssignedAt']);
    }

    public function testItCreatesNonRepeatableTasksByDefault(): void
    {
        $controller = new TaskController(new InMemoryTaskRepository());

        $task = $controller->create(['title' => 'Clean kitchen', 'householdId' => 'house-1']);

        $this->assertFalse($task['repeatable']);
    }

    public function testItCreatesAndUpdatesTheRepeatableFlag(): void
    {
        $repository = new InMemoryTaskRepository();
        $controller = new TaskController($repository);

        $created = $controller->create(['title' => 'Feed the cat', 'householdId' => 'house-1', 'repeatable' => true]);
        $this->assertTrue($created['repeatable']);

        $updated = $controller->update($created['id'], ['title' => 'Feed the cat', 'repeatable' => false]);
        $this->assertFalse($updated['repeatable']);

        $kept = $controller->update($created['id'], ['title' => 'Feed the cat']);
        $this->assertFalse($kept['repeatable'], 'Omitting the flag keeps the stored value.');
    }

    public function testItRejectsANonBooleanRepeatableFlag(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        (new TaskController(new InMemoryTaskRepository()))->create([
            'title' => 'Feed the cat',
            'householdId' => 'house-1',
            'repeatable' => 'often',
        ]);
    }

    public function testCompletingARepeatableTaskReassignsItToTheNextMember(): void
    {
        $repository = new InMemoryTaskRepository();
        $members = new InMemoryMemberRepository();
        $members->save(Member::create(0, 'Ana', 'A', '#C4963A', 'house-1'));
        $members->save(Member::create(0, 'Bruno', 'B', '#6B7C4E', 'house-1'));
        $controller = new TaskController($repository, null, $members);

        $created = $controller->create([
            'title' => 'Feed the cat',
            'householdId' => 'house-1',
            'assignedMemberId' => 1,
            'repeatable' => true,
        ]);

        $completed = $controller->complete($created['id'], '1', 2);

        $this->assertSame('pending', $completed['status']);
        $this->assertSame(2, $completed['assignedMemberId']);
        $this->assertTrue($completed['repeatable']);
    }

    public function testItCreatesATaskWithCatalogReferencesAndReturnsTheirNames(): void
    {
        [$controller] = $this->catalogFixture();

        $task = $controller->create([
            'title' => 'Mop floors',
            'householdId' => 'house-1',
            'categoryId' => 1,
            'recurrenceId' => '1',
        ]);

        $this->assertSame(1, $task['categoryId']);
        $this->assertSame('Cleaning', $task['category']);
        $this->assertSame(1, $task['recurrenceId']);
        $this->assertSame('Weekly', $task['recurrence']);
    }

    public function testItRejectsACategoryOfAnotherHousehold(): void
    {
        [$controller, $tasks] = $this->catalogFixture();

        try {
            $controller->create(['title' => 'Mop floors', 'householdId' => 'house-1', 'categoryId' => 2]);
            $this->fail('A foreign category must be rejected.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertSame('Category does not exist in this household.', $exception->getMessage());
        }

        $this->assertSame([], $tasks->findByHouseholdId('house-1'));
    }

    public function testItRejectsANonIntegerCatalogReference(): void
    {
        [$controller] = $this->catalogFixture();

        $this->expectException(\InvalidArgumentException::class);

        $controller->create(['title' => 'Mop floors', 'householdId' => 'house-1', 'recurrenceId' => 'weekly']);
    }

    public function testUpdateKeepsAbsentReferencesAndClearsExplicitNulls(): void
    {
        [$controller] = $this->catalogFixture();
        $created = $controller->create(['title' => 'Mop floors', 'householdId' => 'house-1', 'categoryId' => 1, 'recurrenceId' => 1]);

        $kept = $controller->update($created['id'], ['title' => 'Mop all floors']);
        $this->assertSame(1, $kept['categoryId']);
        $this->assertSame(1, $kept['recurrenceId']);

        $cleared = $controller->update($created['id'], ['title' => 'Mop all floors', 'categoryId' => null, 'recurrenceId' => null]);
        $this->assertNull($cleared['categoryId']);
        $this->assertNull($cleared['category']);
        $this->assertNull($cleared['recurrenceId']);
        $this->assertNull($cleared['recurrence']);
    }

    public function testListResolvesRenamedEntriesAndDropsTheNameOfDeletedOnes(): void
    {
        [$controller, , $categories, $recurrenceTypes] = $this->catalogFixture();
        $controller->create(['title' => 'Mop floors', 'householdId' => 'house-1', 'categoryId' => 1, 'recurrenceId' => 1]);

        $category = $categories->findById(1);
        $category?->update('Floors', null, new \DateTimeImmutable());
        $categories->save($category);
        $recurrenceType = $recurrenceTypes->findById(1);
        $recurrenceType?->delete(new \DateTimeImmutable());
        $recurrenceTypes->save($recurrenceType);

        $listed = $controller->list('house-1');

        $this->assertSame('Floors', $listed[0]['category']);
        $this->assertSame(1, $listed[0]['recurrenceId'], 'The reference itself is kept.');
        $this->assertNull($listed[0]['recurrence']);
    }

    /**
     * Category 1 and recurrence type 1 belong to house-1; category 2 belongs to house-2.
     *
     * @return array{TaskController, InMemoryTaskRepository, InMemoryCategoryRepository, InMemoryRecurrenceTypeRepository}
     */
    private function catalogFixture(): array
    {
        $now = new \DateTimeImmutable('2026-10-24 10:00:00');
        $tasks = new InMemoryTaskRepository();
        $categories = new InMemoryCategoryRepository();
        $categories->save(Category::create(0, 'house-1', 'Cleaning', '#C4623A', $now));
        $categories->save(Category::create(0, 'house-2', 'Garden', null, $now));
        $recurrenceTypes = new InMemoryRecurrenceTypeRepository();
        $recurrenceTypes->save(RecurrenceType::create(0, 'house-1', 'Weekly', 7, $now));

        $controller = new TaskController(
            $tasks,
            categoryRepository: $categories,
            recurrenceTypeRepository: $recurrenceTypes
        );

        return [$controller, $tasks, $categories, $recurrenceTypes];
    }
}
