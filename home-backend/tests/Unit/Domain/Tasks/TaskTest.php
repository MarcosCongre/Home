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
            id: 123,
            title: 'Vacuum living room',
            householdId: 'house-1',
            createdAt: new \DateTimeImmutable('2026-09-16 08:00:00')
        );

        $this->assertSame(123, $task->id());
        $this->assertSame('Vacuum living room', $task->title()->value());
        $this->assertSame(TaskStatus::PENDING, $task->status());
        $this->assertSame('house-1', $task->householdId());
        $this->assertNull($task->day());
        $this->assertNull($task->time());
        $this->assertNull($task->category());
        $this->assertNull($task->recurrence());
        $this->assertSame('med', $task->priority());
    }

    public function testItCanBeCompleted(): void
    {
        $task = Task::create(
            id: 456,
            title: 'Wash dishes',
            householdId: 'house-2',
            createdAt: new \DateTimeImmutable('2026-09-16 08:00:00')
        );

        $task->complete();

        $this->assertSame(TaskStatus::COMPLETED, $task->status());
        $this->assertNotNull($task->completedAt());
    }

    public function testItUpdatesTaskMetadata(): void
    {
        $task = Task::create(
            id: 789,
            title: 'Buy groceries',
            householdId: 'house-3',
            createdAt: new \DateTimeImmutable('2026-09-16 08:00:00')
        );

        $task->update(
            title: 'Buy groceries',
            assignedMemberId: 42,
            status: TaskStatus::PENDING,
            day: 'monday',
            time: '18:30',
            category: 'shopping',
            recurrence: 'weekly',
            priority: 'high'
        );

        $this->assertSame('monday', $task->day());
        $this->assertSame('18:30', $task->time());
        $this->assertSame('shopping', $task->category());
        $this->assertSame('weekly', $task->recurrence());
        $this->assertSame('high', $task->priority());
        $this->assertSame(42, $task->assignedMemberId());
    }
}
