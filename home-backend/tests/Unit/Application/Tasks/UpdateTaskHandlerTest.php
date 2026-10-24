<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Tasks;

use App\Application\Tasks\UpdateTask\UpdateTaskCommand;
use App\Application\Tasks\UpdateTask\UpdateTaskHandler;
use App\Domain\Members\Member;
use App\Domain\Tasks\Task;
use App\Domain\Tasks\TaskStatus;
use App\Infrastructure\Persistence\InMemoryMemberRepository;
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

    public function testItAssignsAnActiveMemberOfTheHousehold(): void
    {
        [$tasks, $members] = $this->fixture('house-1');

        $task = (new UpdateTaskHandler($tasks, $members))->handle(
            new UpdateTaskCommand(taskId: 1, title: 'Clean kitchen', assignedMemberId: 1)
        );

        $this->assertSame(1, $task->assignedMemberId());
    }

    public function testItRejectsAnUnknownAssignee(): void
    {
        [$tasks, $members] = $this->fixture('house-1');

        $this->assertRejected($tasks, $members, 99);
    }

    public function testItRejectsADeletedAssignee(): void
    {
        [$tasks, $members] = $this->fixture('house-1');
        $member = $members->findById(1);
        $this->assertNotNull($member);
        $member->delete(new \DateTimeImmutable('2026-10-24 10:00:00'));
        $members->save($member);

        $this->assertRejected($tasks, $members, 1);
    }

    public function testItRejectsAnAssigneeFromAnotherHousehold(): void
    {
        [$tasks, $members] = $this->fixture('house-2');

        $this->assertRejected($tasks, $members, 1);
    }

    public function testItAllowsClearingTheAssignee(): void
    {
        [$tasks, $members] = $this->fixture('house-1');

        $task = (new UpdateTaskHandler($tasks, $members))->handle(
            new UpdateTaskCommand(taskId: 1, title: 'Clean kitchen', assignedMemberId: null)
        );

        $this->assertNull($task->assignedMemberId());
    }

    private function assertRejected(InMemoryTaskRepository $tasks, InMemoryMemberRepository $members, int $assignee): void
    {
        try {
            (new UpdateTaskHandler($tasks, $members))->handle(
                new UpdateTaskCommand(taskId: 1, title: 'Renamed', assignedMemberId: $assignee)
            );
            $this->fail('Expected the assignee to be rejected.');
        } catch (\InvalidArgumentException) {
            $stored = $tasks->findById(1);
            $this->assertSame('Clean kitchen', $stored?->title()->value());
            $this->assertNull($stored?->assignedMemberId());
        }
    }

    /**
     * @return array{0: InMemoryTaskRepository, 1: InMemoryMemberRepository}
     */
    private function fixture(string $memberHousehold): array
    {
        $tasks = new InMemoryTaskRepository();
        $tasks->save(Task::create(
            id: 1,
            title: 'Clean kitchen',
            householdId: 'house-1',
            createdAt: new \DateTimeImmutable('2026-09-16 08:00:00')
        ));
        $members = new InMemoryMemberRepository();
        $members->save(Member::create(0, 'Ana', 'A', '#000000', $memberHousehold));

        return [$tasks, $members];
    }
}
