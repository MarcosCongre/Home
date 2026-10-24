<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Members;

use App\Application\Members\DeleteMember\DeleteMemberCommand;
use App\Application\Members\DeleteMember\DeleteMemberHandler;
use App\Domain\Members\Member;
use App\Domain\Tasks\Task;
use App\Domain\Tasks\TaskTitle;
use App\Infrastructure\Persistence\InMemoryMemberRepository;
use App\Infrastructure\Persistence\InMemoryTaskRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(DeleteMemberHandler::class)]
final class DeleteMemberHandlerTest extends TestCase
{
    public function testItHidesTheDeletedMemberFromReads(): void
    {
        $members = $this->members();
        $handler = new DeleteMemberHandler($members, new InMemoryTaskRepository());

        $handler->handle(new DeleteMemberCommand(1));

        $this->assertNull($members->findById(1));
        $this->assertSame([2], array_map(static fn (Member $member): int => $member->id(), $members->findByHouseholdId('house-1')));
    }

    public function testItUnassignsTheMembersTasksAndLeavesOthersAlone(): void
    {
        $tasks = new InMemoryTaskRepository();
        $tasks->save($this->task(1, assignee: 1));
        $tasks->save($this->task(2, assignee: 2));
        $tasks->save($this->task(3, assignee: null));
        $handler = new DeleteMemberHandler($this->members(), $tasks);

        $handler->handle(new DeleteMemberCommand(1));

        $this->assertNull($tasks->findById(1)?->assignedMemberId());
        $this->assertNotNull($tasks->findById(1), 'Tasks of a deleted member are kept.');
        $this->assertSame(2, $tasks->findById(2)?->assignedMemberId());
        $this->assertNull($tasks->findById(3)?->assignedMemberId());
    }

    public function testDeletingAMissingMemberIsNotFound(): void
    {
        $handler = new DeleteMemberHandler(new InMemoryMemberRepository(), new InMemoryTaskRepository());

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Member not found.');

        $handler->handle(new DeleteMemberCommand(99));
    }

    public function testDeletingAnAlreadyDeletedMemberIsNotFound(): void
    {
        $handler = new DeleteMemberHandler($this->members(), new InMemoryTaskRepository());
        $handler->handle(new DeleteMemberCommand(1));

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Member not found.');

        $handler->handle(new DeleteMemberCommand(1));
    }

    private function members(): InMemoryMemberRepository
    {
        $members = new InMemoryMemberRepository();
        $members->save(Member::create(0, 'Ana', 'A', '#C4963A', 'house-1'));
        $members->save(Member::create(0, 'Leo', 'L', '#6B7C4E', 'house-1'));

        return $members;
    }

    private function task(int $id, ?int $assignee): Task
    {
        return new Task(
            id: $id,
            title: new TaskTitle('Task ' . $id),
            householdId: 'house-1',
            createdAt: new \DateTimeImmutable('2026-10-01 08:00:0' . $id),
            assignedMemberId: $assignee
        );
    }
}
