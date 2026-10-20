<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Tasks;

use App\Application\Tasks\ReopenTask\ReopenTaskCommand;
use App\Application\Tasks\ReopenTask\ReopenTaskHandler;
use App\Domain\Tasks\Task;
use App\Domain\Tasks\TaskStateConflict;
use App\Domain\Tasks\TaskStatus;
use App\Infrastructure\Persistence\InMemoryTaskRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ReopenTaskHandler::class)]
final class ReopenTaskHandlerTest extends TestCase
{
    public function testItReopensACompletedTask(): void
    {
        $repository = new InMemoryTaskRepository();
        $task = Task::create(
            id: 200,
            title: 'Fold laundry',
            householdId: 'house-42',
            createdAt: new \DateTimeImmutable('2026-09-16 08:00:00')
        );
        $task->complete();
        $repository->save($task);

        $handler = new ReopenTaskHandler($repository);

        $reopenedTask = $handler->handle(new ReopenTaskCommand(taskId: 200));

        $this->assertSame(TaskStatus::PENDING, $reopenedTask->status());
        $this->assertNull($reopenedTask->completedAt());
        $this->assertSame(TaskStatus::PENDING, $repository->findById(200)->status());
        $this->assertNull($repository->findById(200)->completedAt());
    }

    public function testItRejectsReopeningAPendingTask(): void
    {
        $repository = new InMemoryTaskRepository();
        $repository->save(Task::create(
            id: 201,
            title: 'Clean windows',
            householdId: 'house-42',
            createdAt: new \DateTimeImmutable('2026-09-16 08:00:00')
        ));

        $handler = new ReopenTaskHandler($repository);

        $this->expectException(TaskStateConflict::class);
        $this->expectExceptionMessage('Task is not completed.');

        $handler->handle(new ReopenTaskCommand(taskId: 201));
    }

    public function testItFailsWhenTheTaskDoesNotExist(): void
    {
        $handler = new ReopenTaskHandler(new InMemoryTaskRepository());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Task 999 was not found.');

        $handler->handle(new ReopenTaskCommand(taskId: 999));
    }
}
