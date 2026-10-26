<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Tasks;

use App\Application\Tasks\CreateTask\CreateTaskCommand;
use App\Application\Tasks\CreateTask\CreateTaskHandler;
use App\Domain\Members\Member;
use App\Domain\Tasks\TaskStatus;
use App\Infrastructure\Persistence\InMemoryMemberRepository;
use App\Infrastructure\Persistence\InMemoryTaskRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CreateTaskHandler::class)]
final class CreateTaskHandlerTest extends TestCase
{
    public function testItCreatesATaskAndPersistsIt(): void
    {
        $repository = new InMemoryTaskRepository();
        $handler = new CreateTaskHandler($repository);

        $task = $handler->handle(new CreateTaskCommand(
            title: 'Take out trash',
            householdId: 'house-42',
            userId: 'user-77',
            day: 'monday',
            time: '20:00',
            category: 'home',
            recurrence: 'weekly',
            priority: 'high'
        ));

        $this->assertSame('Take out trash', $task->title()->value());
        $this->assertSame(TaskStatus::PENDING, $task->status());
        $this->assertSame('house-42', $task->householdId());
        $this->assertSame('monday', $task->day());
        $this->assertSame('20:00', $task->time());
        $this->assertSame('home', $task->category());
        $this->assertSame('weekly', $task->recurrence());
        $this->assertSame('high', $task->priority());
        $this->assertNotNull($repository->findById($task->id()));
    }

    public function testItAssignsAnActiveMemberOfTheHousehold(): void
    {
        $tasks = new InMemoryTaskRepository();
        $members = $this->membersWith(Member::create(0, 'Ana', 'A', '#000000', 'house-1'));

        $task = (new CreateTaskHandler($tasks, memberRepository: $members))->handle(
            new CreateTaskCommand(title: 'Dishes', householdId: 'house-1', userId: 'u', assignedMemberId: 1)
        );

        $this->assertSame(1, $task->assignedMemberId());
    }

    public function testItRejectsAnUnknownAssignee(): void
    {
        $tasks = new InMemoryTaskRepository();

        $this->assertRejected($tasks, new InMemoryMemberRepository(), 99);
    }

    public function testItRejectsADeletedAssignee(): void
    {
        $tasks = new InMemoryTaskRepository();
        $member = Member::create(0, 'Ana', 'A', '#000000', 'house-1');
        $members = $this->membersWith($member);
        $member->delete(new \DateTimeImmutable('2026-10-24 10:00:00'));
        $members->save($member);

        $this->assertRejected($tasks, $members, 1);
    }

    public function testItRejectsAnAssigneeFromAnotherHousehold(): void
    {
        $tasks = new InMemoryTaskRepository();
        $members = $this->membersWith(Member::create(0, 'Ana', 'A', '#000000', 'house-2'));

        $this->assertRejected($tasks, $members, 1);
    }

    private function assertRejected(InMemoryTaskRepository $tasks, InMemoryMemberRepository $members, int $assignee): void
    {
        try {
            (new CreateTaskHandler($tasks, memberRepository: $members))->handle(
                new CreateTaskCommand(title: 'Dishes', householdId: 'house-1', userId: 'u', assignedMemberId: $assignee)
            );
            $this->fail('Expected the assignee to be rejected.');
        } catch (\InvalidArgumentException) {
            $this->assertSame([], $tasks->findByHouseholdId('house-1'));
        }
    }

    private function membersWith(Member ...$list): InMemoryMemberRepository
    {
        $members = new InMemoryMemberRepository();
        foreach ($list as $member) {
            $members->save($member);
        }

        return $members;
    }
}
