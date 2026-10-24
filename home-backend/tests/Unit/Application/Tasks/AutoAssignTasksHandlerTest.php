<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Tasks;

use App\Application\Tasks\AutoAssignTasks\AutoAssignTasksCommand;
use App\Application\Tasks\AutoAssignTasks\AutoAssignTasksHandler;
use App\Application\Tasks\CreateTask\CreateTaskCommand;
use App\Application\Tasks\CreateTask\CreateTaskHandler;
use App\Domain\Alerts\Alert;
use App\Domain\Alerts\AlertRepositoryInterface;
use App\Domain\Members\Member;
use App\Domain\Tasks\Catalog\RecurrenceType;
use App\Domain\Tasks\Task;
use App\Domain\Tasks\TaskRepositoryInterface;
use App\Domain\Tasks\TaskTitle;
use App\Infrastructure\Persistence\InMemoryMemberRepository;
use App\Infrastructure\Persistence\InMemoryRecurrenceTypeRepository;
use App\Infrastructure\Persistence\InMemoryTaskRepository;
use App\Infrastructure\Persistence\PdoTaskRepository;
use App\Infrastructure\Persistence\PdoTransactionRunner;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Domain\Tasks\Assignment\LowestIdTieBreaker;

#[CoversClass(AutoAssignTasksHandler::class)]
final class AutoAssignTasksHandlerTest extends TestCase
{
    // Without a recurrence type repository an id cannot be resolved, so it falls back to every 7 days.
    private const UNRESOLVED_WEEKLY = 1;

    public function testItAssignsTasksPersistsThemAndAlertsTheNewAssignees(): void
    {
        $now = new DateTimeImmutable('2026-10-19 12:00:00');
        $tasks = new InMemoryTaskRepository();
        $members = $this->membersOf('house-1', 2);
        $alerts = new RecordingAlertRepository();

        $tasks->save($this->task(1, 'house-1', self::UNRESOLVED_WEEKLY, assignee: 1, lastAssignedAt: '2026-10-11 12:00:00'));
        $tasks->save($this->task(2, 'house-1', self::UNRESOLVED_WEEKLY, assignee: 2, lastAssignedAt: '2026-10-18 12:00:00'));
        $tasks->save($this->task(3, 'house-2', self::UNRESOLVED_WEEKLY));

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
        $tasks->save($this->task(1, 'house-1', self::UNRESOLVED_WEEKLY));

        $handler = new AutoAssignTasksHandler($tasks, new InMemoryMemberRepository(), null, new LowestIdTieBreaker());

        $this->assertSame([], $handler->handle(new AutoAssignTasksCommand('house-1')));
        $this->assertNull($tasks->findById(1)?->assignedMemberId());
    }

    public function testAnAlertFailureDoesNotFailTheAssignment(): void
    {
        $tasks = new InMemoryTaskRepository();
        $tasks->save($this->task(1, 'house-1', self::UNRESOLVED_WEEKLY));
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

    public function testATaskCreatedWithAnAssigneeKeepsItOnTheNextAutoAssign(): void
    {
        $tasks = new InMemoryTaskRepository();
        $members = $this->membersOf('house-1', 2);
        $recurrenceTypes = new InMemoryRecurrenceTypeRepository();
        $recurrenceTypes->save(RecurrenceType::create(0, 'house-1', 'Weekly', 7, new DateTimeImmutable()));
        $created = (new CreateTaskHandler($tasks, recurrenceTypeRepository: $recurrenceTypes))->handle(new CreateTaskCommand(
            title: 'Laundry',
            householdId: 'house-1',
            userId: 'user-1',
            assignedMemberId: 1,
            recurrenceId: 1
        ));

        $this->assertNotNull($created->lastAssignedAt(), 'Creating with an assignee records the assignment date.');

        $handler = new AutoAssignTasksHandler($tasks, $members, null, new LowestIdTieBreaker(), recurrenceTypeRepository: $recurrenceTypes);
        $updated = $handler->handle(new AutoAssignTasksCommand('house-1', new DateTimeImmutable('+1 hour')));

        $this->assertSame([], $updated);
        $this->assertSame(1, $tasks->findById($created->id())?->assignedMemberId());
    }

    public function testASaveFailureMidBatchLeavesNoPartialReassignment(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $repository = new PdoTaskRepository($pdo);
        $repository->save($this->task(1, 'house-1', self::UNRESOLVED_WEEKLY, assignee: 1, lastAssignedAt: '2026-10-01 12:00:00'));
        $repository->save($this->task(2, 'house-1', self::UNRESOLVED_WEEKLY, assignee: 2, lastAssignedAt: '2026-10-01 12:00:00'));
        $alerts = new RecordingAlertRepository();

        $failingOnSecondSave = new class ($repository) implements TaskRepositoryInterface {
            private int $saves = 0;

            public function __construct(private readonly TaskRepositoryInterface $inner)
            {
            }

            public function save(Task $task): void
            {
                if (++$this->saves === 2) {
                    throw new \RuntimeException('Disk full');
                }
                $this->inner->save($task);
            }

            public function findById(int $id): ?Task
            {
                return $this->inner->findById($id);
            }

            public function findByHouseholdId(string $householdId): array
            {
                return $this->inner->findByHouseholdId($householdId);
            }
        };

        $handler = new AutoAssignTasksHandler(
            $failingOnSecondSave,
            $this->membersOf('house-1', 2),
            $alerts,
            new LowestIdTieBreaker(),
            new PdoTransactionRunner($pdo)
        );

        try {
            $handler->handle(new AutoAssignTasksCommand('house-1', new DateTimeImmutable('2026-10-19 12:00:00')));
            $this->fail('The save failure must propagate.');
        } catch (\RuntimeException $exception) {
            $this->assertSame('Disk full', $exception->getMessage());
        }

        $this->assertSame(1, $repository->findById(1)?->assignedMemberId(), 'The first save is rolled back.');
        $this->assertSame(2, $repository->findById(2)?->assignedMemberId());
        $this->assertFalse($pdo->inTransaction());
        $this->assertSame([], $alerts->saved, 'No alerts are sent for a rolled-back batch.');
    }

    public function testItSkipsSoftDeletedMembersAndTasks(): void
    {
        $now = new DateTimeImmutable('2026-10-19 12:00:00');
        $tasks = new InMemoryTaskRepository();
        $members = $this->membersOf('house-1', 2);
        $deletedMember = $members->findById(1);
        $deletedMember?->delete($now);
        $members->save($deletedMember);

        $tasks->save($this->task(1, 'house-1', self::UNRESOLVED_WEEKLY));
        $deletedTask = $this->task(2, 'house-1', self::UNRESOLVED_WEEKLY);
        $deletedTask->delete($now);
        $tasks->save($deletedTask);

        $handler = new AutoAssignTasksHandler($tasks, $members, null, new LowestIdTieBreaker());
        $updated = $handler->handle(new AutoAssignTasksCommand('house-1', $now));

        $this->assertSame([1], array_map(static fn (Task $task): int => $task->id(), $updated));
        $this->assertSame(2, $tasks->findById(1)?->assignedMemberId(), 'Only the remaining member is a candidate.');
        $this->assertNull($deletedTask->assignedMemberId());
    }

    public function testItUsesTheIntervalOfTheHouseholdRecurrenceTypes(): void
    {
        $now = new DateTimeImmutable('2026-10-19 12:00:00');
        $tasks = new InMemoryTaskRepository();
        $recurrenceTypes = new InMemoryRecurrenceTypeRepository();
        $daily = RecurrenceType::create(0, 'house-1', 'Daily', 1, $now);
        $recurrenceTypes->save($daily);
        $weekly = RecurrenceType::create(0, 'house-1', 'Weekly', 7, $now);
        $recurrenceTypes->save($weekly);

        // Both were assigned two days ago: the daily cycle has ended, the weekly one has not.
        $tasks->save($this->task(1, 'house-1', $daily->id(), assignee: 1, lastAssignedAt: '2026-10-17 12:00:00'));
        $tasks->save($this->task(2, 'house-1', $weekly->id(), assignee: 1, lastAssignedAt: '2026-10-17 12:00:00'));

        $handler = new AutoAssignTasksHandler(
            $tasks,
            $this->membersOf('house-1', 2),
            null,
            new LowestIdTieBreaker(),
            recurrenceTypeRepository: $recurrenceTypes
        );
        $updated = $handler->handle(new AutoAssignTasksCommand('house-1', $now));

        $this->assertSame([1], array_map(static fn (Task $task): int => $task->id(), $updated));
        $this->assertSame(2, $tasks->findById(1)?->assignedMemberId());
        $this->assertSame(1, $tasks->findById(2)?->assignedMemberId());
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

    private function task(int $id, string $householdId, ?int $recurrenceId, ?int $assignee = null, ?string $lastAssignedAt = null): Task
    {
        return new Task(
            id: $id,
            title: new TaskTitle('Task ' . $id),
            householdId: $householdId,
            createdAt: new DateTimeImmutable('2026-01-01 00:00:0' . $id),
            assignedMemberId: $assignee,
            recurrenceId: $recurrenceId,
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
