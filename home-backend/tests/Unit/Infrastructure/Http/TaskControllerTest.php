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
        ]);

        $this->assertSame('Clean kitchen', $task['title']);
        $this->assertSame('pending', $task['status']);
        $this->assertSame('house-1', $task['householdId']);
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
