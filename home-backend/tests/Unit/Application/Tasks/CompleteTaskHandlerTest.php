<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Tasks;

use App\Application\Tasks\CompleteTask\CompleteTaskCommand;
use App\Application\Tasks\CompleteTask\CompleteTaskHandler;
use App\Domain\Members\Member;
use App\Domain\Tasks\Task;
use App\Domain\Tasks\TaskStatus;
use App\Infrastructure\Persistence\InMemoryMemberRepository;
use App\Infrastructure\Persistence\InMemoryTaskRepository;
use App\Infrastructure\Persistence\PdoAlertRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CompleteTaskHandler::class)]
final class CompleteTaskHandlerTest extends TestCase
{
    public function testItMarksATaskAsCompleted(): void
    {
        $repository = new InMemoryTaskRepository();
        $task = Task::create(
            id: 100,
            title: 'Fold laundry',
            householdId: 'house-42',
            createdAt: new \DateTimeImmutable('2026-09-16 08:00:00')
        );
        $repository->save($task);

        $handler = new CompleteTaskHandler($repository);

        $completedTask = $handler->handle(new CompleteTaskCommand(
            taskId: 100,
            userId: 'user-77'
        ));

        $this->assertSame(TaskStatus::COMPLETED, $completedTask->status());
        $this->assertNotNull($completedTask->completedAt());
        $this->assertSame(TaskStatus::COMPLETED, $repository->findById(100)->status());
    }

    public function testItDoesNotAllowCompletingAnAlreadyCompletedTask(): void
    {
        $repository = new InMemoryTaskRepository();
        $task = Task::create(
            id: 101,
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
            taskId: 101,
            userId: 'user-77'
        ));
    }

    public function testItReturnsARepeatableTaskToPendingAssignedToTheNextMember(): void
    {
        $repository = new InMemoryTaskRepository();
        $repository->save($this->repeatableTask(id: 102, assignedMemberId: 1));
        $alerts = new PdoAlertRepository(new \PDO('sqlite::memory:'));

        $handler = new CompleteTaskHandler($repository, $alerts);

        $result = $handler->handle(new CompleteTaskCommand(
            taskId: 102,
            userId: '1',
            nextAssigneeId: 2
        ));

        $this->assertSame(TaskStatus::PENDING, $result->status());
        $this->assertSame(2, $result->assignedMemberId());
        $this->assertNotNull($result->completedAt(), 'The latest completion is kept as the completion record.');
        $this->assertSame(TaskStatus::PENDING, $repository->findById(102)->status());

        $completionAlerts = array_values(array_filter(
            $alerts->findByHousehold('house-42'),
            static fn ($alert): bool => $alert->title() === 'Tarea completada'
        ));
        $this->assertCount(1, $completionAlerts);
        $this->assertSame(1, $completionAlerts[0]->memberId(), 'The completion is credited to the member who did it.');
    }

    public function testItKeepsTheCurrentAssigneeWhenNoNextMemberIsGiven(): void
    {
        $repository = new InMemoryTaskRepository();
        $repository->save($this->repeatableTask(id: 103, assignedMemberId: 1));

        $result = (new CompleteTaskHandler($repository))->handle(new CompleteTaskCommand(
            taskId: 103,
            userId: '1'
        ));

        $this->assertSame(TaskStatus::PENDING, $result->status());
        $this->assertSame(1, $result->assignedMemberId());
    }

    public function testARepeatableTaskCanBeCompletedAgain(): void
    {
        $repository = new InMemoryTaskRepository();
        $repository->save($this->repeatableTask(id: 104, assignedMemberId: 1));
        $handler = new CompleteTaskHandler($repository);

        $handler->handle(new CompleteTaskCommand(taskId: 104, userId: '1', nextAssigneeId: 2));
        $result = $handler->handle(new CompleteTaskCommand(taskId: 104, userId: '2', nextAssigneeId: 1));

        $this->assertSame(TaskStatus::PENDING, $result->status());
        $this->assertSame(1, $result->assignedMemberId());
    }

    public function testANonRepeatableTaskIgnoresTheNextAssignee(): void
    {
        $repository = new InMemoryTaskRepository();
        $task = Task::create(
            id: 105,
            title: 'Clean windows',
            householdId: 'house-42',
            createdAt: new \DateTimeImmutable('2026-09-16 08:00:00')
        );
        $task->reassignTo(1, new \DateTimeImmutable('2026-09-16 08:00:00'));
        $repository->save($task);

        $result = (new CompleteTaskHandler($repository))->handle(new CompleteTaskCommand(
            taskId: 105,
            userId: '1',
            nextAssigneeId: 2
        ));

        $this->assertSame(TaskStatus::COMPLETED, $result->status());
        $this->assertSame(1, $result->assignedMemberId());
    }

    public function testItRejectsANextAssigneeFromAnotherHousehold(): void
    {
        $repository = new InMemoryTaskRepository();
        $repository->save($this->repeatableTask(id: 106, assignedMemberId: 1));
        $members = new InMemoryMemberRepository();
        $members->save(Member::create(0, 'Ana', 'A', '#C4963A', 'house-42'));
        $members->save(Member::create(0, 'Bruno', 'B', '#6B7C4E', 'house-other'));

        $handler = new CompleteTaskHandler($repository, memberRepository: $members);

        try {
            $handler->handle(new CompleteTaskCommand(taskId: 106, userId: '1', nextAssigneeId: 2));
            $this->fail('A member from another household must be rejected.');
        } catch (\InvalidArgumentException) {
            $this->assertSame(1, $repository->findById(106)->assignedMemberId());
            $this->assertSame(TaskStatus::PENDING, $repository->findById(106)->status());
        }
    }

    public function testItRejectsASoftDeletedNextAssignee(): void
    {
        $repository = new InMemoryTaskRepository();
        $repository->save($this->repeatableTask(id: 107, assignedMemberId: 1));
        $members = new InMemoryMemberRepository();
        $members->save(Member::create(0, 'Ana', 'A', '#C4963A', 'house-42'));
        $bruno = Member::create(0, 'Bruno', 'B', '#6B7C4E', 'house-42');
        $members->save($bruno);
        $bruno->delete(new \DateTimeImmutable('2026-09-16 09:00:00'));
        $members->save($bruno);

        $handler = new CompleteTaskHandler($repository, memberRepository: $members);

        try {
            $handler->handle(new CompleteTaskCommand(taskId: 107, userId: '1', nextAssigneeId: 2));
            $this->fail('A soft-deleted member must not receive the task.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertSame('Assigned member does not exist in this household.', $exception->getMessage());
            $this->assertSame(1, $repository->findById(107)->assignedMemberId());
            $this->assertSame(TaskStatus::PENDING, $repository->findById(107)->status());
            $this->assertNull($repository->findById(107)->completedAt());
        }
    }

    private function repeatableTask(int $id, int $assignedMemberId): Task
    {
        $task = Task::create(
            id: $id,
            title: 'Feed the cat',
            householdId: 'house-42',
            createdAt: new \DateTimeImmutable('2026-09-16 08:00:00'),
            repeatable: true
        );
        $task->reassignTo($assignedMemberId, new \DateTimeImmutable('2026-09-16 08:00:00'));

        return $task;
    }
}
