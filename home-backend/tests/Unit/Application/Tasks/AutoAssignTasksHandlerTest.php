<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Tasks;

use App\Application\Tasks\AutoAssignTasks\AutoAssignTasksCommand;
use App\Application\Tasks\AutoAssignTasks\AutoAssignTasksHandler;
use App\Domain\Alerts\Alert;
use App\Domain\Alerts\AlertRepositoryInterface;
use App\Domain\Members\Member;
use App\Domain\Tasks\Task;
use App\Domain\Tasks\TaskTitle;
use App\Infrastructure\Persistence\InMemoryMemberRepository;
use App\Infrastructure\Persistence\InMemoryTaskRepository;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Domain\Tasks\Assignment\LowestIdTieBreaker;

#[CoversClass(AutoAssignTasksHandler::class)]
final class AutoAssignTasksHandlerTest extends TestCase
{
    public function testItAssignsTasksPersistsThemAndAlertsTheNewAssignees(): void
    {
        $now = new DateTimeImmutable('2026-10-19 12:00:00');
        $tasks = new InMemoryTaskRepository();
        $members = $this->membersOf('house-1', 2);
        $alerts = new RecordingAlertRepository();

        $tasks->save($this->task(1, 'house-1', 'Weekly', assignee: 1, lastAssignedAt: '2026-10-11 12:00:00'));
        $tasks->save($this->task(2, 'house-1', 'Weekly', assignee: 2, lastAssignedAt: '2026-10-18 12:00:00'));
        $tasks->save($this->task(3, 'house-2', 'Weekly'));

        $handler = new AutoAssignTasksHandler($tasks, $members, $alerts, new LowestIdTieBreaker());
        $updated = $handler->handle(new AutoAssignTasksCommand('house-1', $now));

        $this->assertSame([1], array_map(static fn (Task $task): int => $task->id(), $updated));
        $stored = $tasks->findById(1);
        $this->assertSame(2, $stored?->assignedMemberId());
        $this->assertSame($now, $stored?->lastAssignedAt());
        $this->assertSame(2, $tasks->findById(2)?->assignedMemberId());
        $this->assertNull($tasks->findById(3)?->assignedMemberId(), 'Other households are untouched.');

        $this->assertCount(1, $alerts->saved);
        $this->assertSame('house-1', $alerts->saved[0]->householdId());
        $this->assertSame(2, $alerts->saved[0]->memberId());
        $this->assertSame('Nueva tarea asignada', $alerts->saved[0]->title());
        $this->assertSame('Se te asignó la tarea "Task 1". Miembro #2.', $alerts->saved[0]->body());
    }

    public function testItReturnsNoChangesWhenTheHouseholdHasNoMembers(): void
    {
        $tasks = new InMemoryTaskRepository();
        $tasks->save($this->task(1, 'house-1', 'Weekly'));

        $handler = new AutoAssignTasksHandler($tasks, new InMemoryMemberRepository(), null, new LowestIdTieBreaker());

        $this->assertSame([], $handler->handle(new AutoAssignTasksCommand('house-1')));
        $this->assertNull($tasks->findById(1)?->assignedMemberId());
    }

    public function testAnAlertFailureDoesNotFailTheAssignment(): void
    {
        $tasks = new InMemoryTaskRepository();
        $tasks->save($this->task(1, 'house-1', 'Weekly'));
        $failingAlerts = new class implements AlertRepositoryInterface {
            public function findByHousehold(string $householdId): array
            {
                return [];
            }

            public function save(Alert $alert): Alert
            {
                throw new \RuntimeException('Alert storage unavailable');
            }

            public function dismiss(int $id, string $householdId): bool
            {
                return false;
            }

            public function dismissAll(string $householdId): void
            {
            }
        };

        $handler = new AutoAssignTasksHandler($tasks, $this->membersOf('house-1', 1), $failingAlerts, new LowestIdTieBreaker());
        $updated = $handler->handle(new AutoAssignTasksCommand('house-1'));

        $this->assertCount(1, $updated);
        $this->assertSame(1, $tasks->findById(1)?->assignedMemberId());
    }

    public function testItRequiresAHouseholdId(): void
    {
        $handler = new AutoAssignTasksHandler(new InMemoryTaskRepository(), new InMemoryMemberRepository());

        $this->expectException(\InvalidArgumentException::class);

        $handler->handle(new AutoAssignTasksCommand(''));
    }

    private function membersOf(string $householdId, int $count): InMemoryMemberRepository
    {
        $members = new InMemoryMemberRepository();
        for ($i = 1; $i <= $count; $i++) {
            $members->save(Member::create(0, 'Member ' . $i, 'M', '#C4963A', $householdId));
        }

        return $members;
    }

    private function task(int $id, string $householdId, ?string $recurrence, ?int $assignee = null, ?string $lastAssignedAt = null): Task
    {
        return new Task(
            id: $id,
            title: new TaskTitle('Task ' . $id),
            householdId: $householdId,
            createdAt: new DateTimeImmutable('2026-01-01 00:00:0' . $id),
            assignedMemberId: $assignee,
            recurrence: $recurrence,
            lastAssignedAt: $lastAssignedAt === null ? null : new DateTimeImmutable($lastAssignedAt)
        );
    }
}

final class RecordingAlertRepository implements AlertRepositoryInterface
{
    /** @var array<int, Alert> */
    public array $saved = [];

    public function findByHousehold(string $householdId): array
    {
        return $this->saved;
    }

    public function save(Alert $alert): Alert
    {
        $this->saved[] = $alert;

        return $alert;
    }

    public function dismiss(int $id, string $householdId): bool
    {
        return false;
    }

    public function dismissAll(string $householdId): void
    {
    }
}
