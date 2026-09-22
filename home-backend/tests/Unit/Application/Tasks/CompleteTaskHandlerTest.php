<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Tasks;

use App\Application\Tasks\CompleteTask\CompleteTaskCommand;
use App\Application\Tasks\CompleteTask\CompleteTaskHandler;
use App\Domain\Tasks\Task;
use App\Domain\Tasks\TaskStatus;
use App\Infrastructure\Persistence\InMemoryTaskRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CompleteTaskHandler::class)]
final class CompleteTaskHandlerTest extends TestCase
{
    public function testItMarksATaskAsCompleted(): void
    {
        $repository = new InMemoryTaskRepository();
        $task = Task::create(
            id: 'task-100',
            title: 'Fold laundry',
            householdId: 'house-42',
            createdAt: new \DateTimeImmutable('2026-09-16 08:00:00')
        );
        $repository->save($task);

        $handler = new CompleteTaskHandler($repository);

        $completedTask = $handler->handle(new CompleteTaskCommand(
            taskId: 'task-100',
            userId: 'user-77'
        ));

        $this->assertSame(TaskStatus::COMPLETED, $completedTask->status());
        $this->assertNotNull($completedTask->completedAt());
        $this->assertSame(TaskStatus::COMPLETED, $repository->findById('task-100')->status());
    }

    public function testItDoesNotAllowCompletingAnAlreadyCompletedTask(): void
    {
        $repository = new InMemoryTaskRepository();
        $task = Task::create(
            id: 'task-101',
            title: 'Clean windows',
            householdId: 'house-42',
            createdAt: new \DateTimeImmutable('2026-09-16 08:00:00')
        );
        $task->complete();
        $repository->save($task);

        $handler = new CompleteTaskHandler($repository);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Task is already completed.');

        $handler->handle(new CompleteTaskCommand(
            taskId: 'task-101',
            userId: 'user-77'
        ));
    }
}
