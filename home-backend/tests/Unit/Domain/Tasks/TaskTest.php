<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Tasks;

use App\Domain\Tasks\Task;
use App\Domain\Tasks\TaskStateConflict;
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
        $this->assertNull($task->categoryId());
        $this->assertNull($task->recurrenceId());
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
            priority: 'high'
        );

        $this->assertSame('monday', $task->day());
        $this->assertSame('18:30', $task->time());
        $this->assertNull($task->categoryId(), 'Catalog references change only through their own methods.');
        $this->assertSame('high', $task->priority());
        $this->assertSame(42, $task->assignedMemberId());
    }

    public function testItCanBeReopenedAfterBeingCompleted(): void
    {
        $task = Task::create(
            id: 457,
            title: 'Wash dishes',
            householdId: 'house-2',
            createdAt: new \DateTimeImmutable('2026-09-16 08:00:00')
        );
        $task->complete();

        $task->reopen();

        $this->assertSame(TaskStatus::PENDING, $task->status());
        $this->assertNull($task->completedAt());
    }

    public function testItCannotReopenATaskThatIsNotCompleted(): void
    {
        $task = Task::create(
            id: 458,
            title: 'Wash dishes',
            householdId: 'house-2',
            createdAt: new \DateTimeImmutable('2026-09-16 08:00:00')
        );

        $this->expectException(TaskStateConflict::class);
        $this->expectExceptionMessage('Task is not completed.');

        $task->reopen();
    }

    public function testCompletingAnAlreadyCompletedTaskIsAStateConflict(): void
    {
        $task = Task::create(
            id: 459,
            title: 'Wash dishes',
            householdId: 'house-2',
            createdAt: new \DateTimeImmutable('2026-09-16 08:00:00')
        );
        $task->complete();

        $this->expectException(TaskStateConflict::class);
        $this->expectExceptionMessage('Task is already completed.');

        $task->complete();
    }

    public function testANewTaskHasNoLastAssignmentDate(): void
    {
        $task = Task::create(
            id: 460,
            title: 'Wash dishes',
            householdId: 'house-2',
            createdAt: new \DateTimeImmutable('2026-09-16 08:00:00')
        );

        $this->assertNull($task->lastAssignedAt());
    }

    public function testUpdatingToADifferentAssigneeRecordsTheAssignmentDate(): void
    {
        $task = Task::create(
            id: 461,
            title: 'Wash dishes',
            householdId: 'house-2',
            createdAt: new \DateTimeImmutable('2026-09-16 08:00:00')
        );

        $task->update(title: 'Wash dishes', assignedMemberId: 4);

        $this->assertSame(4, $task->assignedMemberId());
        $this->assertNotNull($task->lastAssignedAt());
    }

    public function testUpdatingWithTheSameAssigneeKeepsTheAssignmentDate(): void
    {
        $assignedAt = new \DateTimeImmutable('2026-09-01 08:00:00');
        $task = new Task(
            id: 462,
            title: new \App\Domain\Tasks\TaskTitle('Wash dishes'),
            householdId: 'house-2',
            createdAt: new \DateTimeImmutable('2026-08-16 08:00:00'),
            assignedMemberId: 4,
            lastAssignedAt: $assignedAt
        );

        $task->update(title: 'Wash all dishes', assignedMemberId: 4);

        $this->assertSame($assignedAt, $task->lastAssignedAt());
    }

    public function testUnassigningClearsTheAssignmentDate(): void
    {
        $task = new Task(
            id: 463,
            title: new \App\Domain\Tasks\TaskTitle('Wash dishes'),
            householdId: 'house-2',
            createdAt: new \DateTimeImmutable('2026-08-16 08:00:00'),
            assignedMemberId: 4,
            lastAssignedAt: new \DateTimeImmutable('2026-09-01 08:00:00')
        );

        $task->update(title: 'Wash dishes', assignedMemberId: null);

        $this->assertNull($task->assignedMemberId());
        $this->assertNull($task->lastAssignedAt());
    }

    public function testReassigningRecordsTheMemberAndTheGivenDate(): void
    {
        $task = Task::create(
            id: 464,
            title: 'Wash dishes',
            householdId: 'house-2',
            createdAt: new \DateTimeImmutable('2026-09-16 08:00:00')
        );
        $at = new \DateTimeImmutable('2026-10-19 12:00:00');

        $task->reassignTo(6, $at);

        $this->assertSame(6, $task->assignedMemberId());
        $this->assertSame($at, $task->lastAssignedAt());
    }

    public function testANewTaskIsNotDeleted(): void
    {
        $task = Task::create(
            id: 465,
            title: 'Wash dishes',
            householdId: 'house-2',
            createdAt: new \DateTimeImmutable('2026-09-16 08:00:00')
        );

        $this->assertNull($task->deletedAt());
        $this->assertFalse($task->isDeleted());
    }

    public function testDeletingRecordsTheGivenDateAndKeepsTheFirstOne(): void
    {
        $task = Task::create(
            id: 466,
            title: 'Wash dishes',
            householdId: 'house-2',
            createdAt: new \DateTimeImmutable('2026-09-16 08:00:00')
        );
        $at = new \DateTimeImmutable('2026-10-24 09:00:00');

        $task->delete($at);
        $task->delete(new \DateTimeImmutable('2026-10-25 09:00:00'));

        $this->assertTrue($task->isDeleted());
        $this->assertSame($at, $task->deletedAt());
    }

    public function testUnassigningClearsOnlyTheAssignee(): void
    {
        $assignedAt = new \DateTimeImmutable('2026-09-01 08:00:00');
        $task = new Task(
            id: 467,
            title: new \App\Domain\Tasks\TaskTitle('Wash dishes'),
            householdId: 'house-2',
            createdAt: new \DateTimeImmutable('2026-08-16 08:00:00'),
            assignedMemberId: 4,
            lastAssignedAt: $assignedAt
        );

        $task->unassign();

        $this->assertNull($task->assignedMemberId());
        $this->assertSame($assignedAt, $task->lastAssignedAt(), 'Rotation history is kept, like the old ON DELETE SET NULL.');
    }
}
