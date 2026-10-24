<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Tasks;

use App\Application\Tasks\DeleteTask\DeleteTaskHandler;
use App\Domain\Tasks\Task;
use App\Infrastructure\Persistence\InMemoryTaskRepository;
use App\Infrastructure\Persistence\PdoTaskRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeleteTaskHandler::class)]
final class DeleteTaskHandlerTest extends TestCase
{
    public function testItHidesTheDeletedTaskFromReads(): void
    {
        $tasks = new InMemoryTaskRepository();
        $tasks->save($this->task(1));
        $tasks->save($this->task(2));

        (new DeleteTaskHandler($tasks))->handle(1);

        $this->assertNull($tasks->findById(1));
        $this->assertSame([2], array_map(static fn (Task $task): int => $task->id(), $tasks->findByHouseholdId('house-1')));
    }

    public function testItKeepsTheRowAndStampsDeletedAt(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $tasks = new PdoTaskRepository($pdo);
        $tasks->save($this->task(1));

        (new DeleteTaskHandler($tasks))->handle(1);

        $row = $pdo->query('SELECT deleted_at FROM tasks WHERE id = 1')->fetch(\PDO::FETCH_ASSOC);
        $this->assertIsArray($row, 'The row is kept.');
        $this->assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2}$/', (string) $row['deleted_at']);
        $this->assertNull($tasks->findById(1));
    }

    public function testDeletingAMissingTaskIsNotFound(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Task not found.');

        (new DeleteTaskHandler(new InMemoryTaskRepository()))->handle(99);
    }

    public function testDeletingAnAlreadyDeletedTaskIsNotFound(): void
    {
        $tasks = new InMemoryTaskRepository();
        $tasks->save($this->task(1));
        $handler = new DeleteTaskHandler($tasks);
        $handler->handle(1);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Task not found.');

        $handler->handle(1);
    }

    private function task(int $id): Task
    {
        return Task::create(
            id: $id,
            title: 'Task ' . $id,
            householdId: 'house-1',
            createdAt: new \DateTimeImmutable('2026-10-01 08:00:0' . $id)
        );
    }
}
