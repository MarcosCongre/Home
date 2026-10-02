<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Http;

use App\Domain\Tasks\Task;
use App\Infrastructure\Http\TaskController;
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
            'category' => 'housework',
            'recurrence' => 'weekly',
            'priority' => 'high',
        ]);

        $this->assertSame('Clean kitchen', $task['title']);
        $this->assertSame('pending', $task['status']);
        $this->assertSame('house-1', $task['householdId']);
        $this->assertSame('Mon', $task['day']);
        $this->assertSame('18:30', $task['time']);
        $this->assertSame('housework', $task['category']);
        $this->assertSame('weekly', $task['recurrence']);
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
            'category' => 'cleaning',
            'recurrence' => 'monthly',
            'priority' => 'low',
        ]);

        $this->assertSame('Tue', $updated['day']);
        $this->assertSame('19:15', $updated['time']);
        $this->assertSame('cleaning', $updated['category']);
        $this->assertSame('monthly', $updated['recurrence']);
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
}
