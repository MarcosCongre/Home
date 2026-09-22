<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Tasks;

use App\Domain\Tasks\Task;
use App\Domain\Tasks\TaskStatus;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Task::class)]
final class TaskTest extends TestCase
{
    public function testItCreatesAPendingTask(): void
    {
        $task = Task::create(
            id: 'task-123',
            title: 'Vacuum living room',
            householdId: 'house-1',
            createdAt: new \DateTimeImmutable('2026-09-16 08:00:00')
        );

        $this->assertSame('task-123', $task->id());
        $this->assertSame('Vacuum living room', $task->title()->value());
        $this->assertSame(TaskStatus::PENDING, $task->status());
        $this->assertSame('house-1', $task->householdId());
    }

    public function testItCanBeCompleted(): void
    {
        $task = Task::create(
            id: 'task-456',
            title: 'Wash dishes',
            householdId: 'house-2',
            createdAt: new \DateTimeImmutable('2026-09-16 08:00:00')
        );

        $task->complete();

        $this->assertSame(TaskStatus::COMPLETED, $task->status());
        $this->assertNotNull($task->completedAt());
    }
}
