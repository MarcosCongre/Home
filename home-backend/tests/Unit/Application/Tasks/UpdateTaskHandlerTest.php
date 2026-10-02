<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Tasks;

use App\Application\Tasks\UpdateTask\UpdateTaskCommand;
use App\Application\Tasks\UpdateTask\UpdateTaskHandler;
use App\Domain\Tasks\Task;
use App\Domain\Tasks\TaskStatus;
use App\Infrastructure\Persistence\InMemoryTaskRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(UpdateTaskHandler::class)]
final class UpdateTaskHandlerTest extends TestCase
{
    public function testItUpdatesTaskMetadata(): void
    {
        $repository = new InMemoryTaskRepository();
        $task = Task::create(
            id: 1,
            title: 'Clean kitchen',
            householdId: 'house-1',
            createdAt: new \DateTimeImmutable('2026-09-16 08:00:00')
        );
        $repository->save($task);

        $updatedTask = (new UpdateTaskHandler($repository))->handle(new UpdateTaskCommand(
            taskId: 1,
            title: 'Clean kitchen',
            assignedMemberId: 7,
            status: TaskStatus::PENDING,
            day: 'tuesday',
            time: '19:00',
            category: 'cleaning',
            recurrence: 'monthly',
            priority: 'low'
        ));

        $this->assertSame('tuesday', $updatedTask->day());
        $this->assertSame('19:00', $updatedTask->time());
        $this->assertSame('cleaning', $updatedTask->category());
        $this->assertSame('monthly', $updatedTask->recurrence());
        $this->assertSame('low', $updatedTask->priority());
        $this->assertSame(7, $updatedTask->assignedMemberId());
    }
}
