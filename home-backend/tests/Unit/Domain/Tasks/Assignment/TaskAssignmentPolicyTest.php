<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Tasks\Assignment;

use App\Domain\Tasks\Assignment\TaskAssignmentPolicy;
use App\Domain\Tasks\Assignment\TieBreaker;
use App\Domain\Tasks\Task;
use App\Domain\Tasks\TaskStatus;
use App\Domain\Tasks\TaskTitle;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

#[CoversClass(TaskAssignmentPolicy::class)]
final class TaskAssignmentPolicyTest extends TestCase
{
    private const NOW = '2026-10-19 12:00:00';

    /**
     * @return array<string, array{?string, string, float}>
     */
    public static function weights(): array
    {
        return [
            'daily high' => ['Daily', 'high', 21.0],
            'mon & thu med' => ['Mon & Thu', 'med', 4.0],
            'tue & fri low' => ['Tue & Fri', 'low', 2.0],
            'weekly medium' => ['Weekly', 'medium', 2.0],
            'biweekly high' => ['Biweekly', 'high', 1.5],
            'monthly low' => ['Monthly', 'low', 0.25],
            'once med' => ['Once', 'med', 2.0],
            'none high' => ['none', 'high', 3.0],
            'null low' => [null, 'low', 1.0],
            'unknown recurrence and priority' => ['Yearly', 'urgent', 2.0],
            'lowercase weekly' => ['weekly', 'high', 3.0],
        ];
    }

    #[DataProvider('weights')]
    public function testItWeighsTasksByFrequencyAndPriority(?string $recurrence, string $priority, float $expected): void
    {
        $this->assertSame($expected, TaskAssignmentPolicy::weightOf($recurrence, $priority));
    }

    /**
     * @return array<string, array{?string, string, ?string}>
     */
    public static function cycles(): array
    {
        return [
            'daily is owned for a week' => ['Daily', '2026-10-01 10:00:00', '2026-10-08 10:00:00'],
            'mon & thu' => ['Mon & Thu', '2026-10-01 10:00:00', '2026-10-08 10:00:00'],
            'tue & fri' => ['Tue & Fri', '2026-10-01 10:00:00', '2026-10-08 10:00:00'],
            'weekly' => ['Weekly', '2026-10-01 10:00:00', '2026-10-08 10:00:00'],
            'biweekly' => ['Biweekly', '2026-10-01 10:00:00', '2026-10-15 10:00:00'],
            'monthly' => ['Monthly', '2026-08-15 10:00:00', '2026-09-15 10:00:00'],
            'monthly clamps to the end of a shorter month' => ['Monthly', '2026-01-31 10:00:00', '2026-02-28 10:00:00'],
            'unknown recurring' => ['Yearly', '2026-10-01 10:00:00', '2026-10-08 10:00:00'],
            'once has no cycle' => ['Once', '2026-10-01 10:00:00', null],
            'none has no cycle' => ['none', '2026-10-01 10:00:00', null],
            'null has no cycle' => [null, '2026-10-01 10:00:00', null],
        ];
    }

    #[DataProvider('cycles')]
    public function testItComputesTheCycleEnd(?string $recurrence, string $assignedAt, ?string $expected): void
    {
        $end = TaskAssignmentPolicy::cycleEnd($recurrence, new DateTimeImmutable($assignedAt));

        $this->assertSame($expected, $end?->format('Y-m-d H:i:s'));
    }

    public function testWeeklyTaskAssignedThreeDaysAgoKeepsItsAssignee(): void
    {
        $task = $this->task(1, 'Weekly', 'med', assignee: 1, lastAssignedAt: '2026-10-16 12:00:00');

        $this->assertSame([], $this->policy()->assign([$task], [1, 2], $this->now()));
    }

    public function testWeeklyTaskAssignedEightDaysAgoRotatesToAnotherMember(): void
    {
        $task = $this->task(1, 'Weekly', 'med', assignee: 1, lastAssignedAt: '2026-10-11 12:00:00');

        $this->assertSame([1 => 2], $this->policy()->assign([$task], [1, 2], $this->now()));
    }

    public function testDailyTaskStaysWithinSevenDaysAndRotatesAfter(): void
    {
        $recent = $this->task(1, 'Daily', 'med', assignee: 2, lastAssignedAt: '2026-10-13 13:00:00');
        $expired = $this->task(2, 'Daily', 'med', assignee: 2, lastAssignedAt: '2026-10-11 12:00:00');

        $decisions = $this->policy()->assign([$recent, $expired], [1, 2], $this->now());

        $this->assertSame([2 => 1], $decisions);
    }

    public function testMonthlyTaskUsesACalendarMonth(): void
    {
        $now = new DateTimeImmutable('2026-09-14 12:00:00');
        // 30 days would have expired at 2026-09-14 10:00; a calendar month lasts until 2026-09-15 10:00.
        $task = $this->task(1, 'Monthly', 'med', assignee: 1, lastAssignedAt: '2026-08-15 10:00:00');

        $this->assertSame([], $this->policy()->assign([$task], [1, 2], $now));
        $this->assertSame([1 => 2], $this->policy()->assign([$task], [1, 2], new DateTimeImmutable('2026-09-15 10:00:01')));
    }

    public function testUnassignedTaskGetsAnAssignee(): void
    {
        $task = $this->task(1, 'Once', 'med');

        $this->assertSame([1 => 1], $this->policy()->assign([$task], [1, 2], $this->now()));
    }

    public function testRecurringTaskWithoutLastAssignmentDateIsReassigned(): void
    {
        $task = $this->task(1, 'Weekly', 'med', assignee: 1);

        $this->assertSame([1 => 2], $this->policy()->assign([$task], [1, 2], $this->now()));
    }

    public function testCompletedOneOffTasksAreUntouched(): void
    {
        $unassigned = $this->task(1, 'Once', 'high', status: TaskStatus::COMPLETED);
        $assigned = $this->task(2, null, 'high', status: TaskStatus::COMPLETED, assignee: 1);

        $this->assertSame([], $this->policy()->assign([$unassigned, $assigned], [1, 2], $this->now()));
    }

    public function testCompletedRecurringTasksAreStillEligible(): void
    {
        $task = $this->task(1, 'Weekly', 'med', status: TaskStatus::COMPLETED);

        $this->assertSame([1 => 1], $this->policy()->assign([$task], [1, 2], $this->now()));
    }

    public function testAssignedPendingOneOffKeepsItsAssignee(): void
    {
        $task = $this->task(1, 'Once', 'med', assignee: 2, lastAssignedAt: '2026-01-01 00:00:00');

        $this->assertSame([], $this->policy()->assign([$task], [1, 2], $this->now()));
    }

    public function testHeavierTasksAreSpreadAcrossMembers(): void
    {
        $tasks = [
            $this->task(1, 'Weekly', 'high'),
            $this->task(2, 'Mon & Thu', 'high'),
            $this->task(3, 'Weekly', 'high'),
            $this->task(4, 'Mon & Thu', 'high'),
        ];

        $decisions = $this->policy()->assign($tasks, [1, 2], $this->now());

        $this->assertCount(4, $decisions);
        $this->assertNotSame($decisions[2], $decisions[4], 'The two heaviest tasks must not pile on one member.');
        $this->assertNotSame($decisions[1], $decisions[3]);
    }

    public function testLockedTasksCountTowardMemberLoad(): void
    {
        $locked = $this->task(1, 'Daily', 'high', assignee: 1, lastAssignedAt: '2026-10-18 12:00:00');
        $unassigned = $this->task(2, 'Weekly', 'low');

        $this->assertSame([2 => 2], $this->policy()->assign([$locked, $unassigned], [1, 2], $this->now()));
    }

    public function testSingleMemberGetsEverything(): void
    {
        $tasks = [
            $this->task(1, 'Daily', 'high'),
            $this->task(2, 'Weekly', 'low'),
            $this->task(3, 'Weekly', 'med', assignee: 7, lastAssignedAt: '2026-10-01 00:00:00'),
            $this->task(4, 'Weekly', 'med', assignee: 5, lastAssignedAt: '2026-10-01 00:00:00'),
        ];

        $this->assertSame([1 => 5, 2 => 5, 3 => 5], $this->policy()->assign($tasks, [5], $this->now()));
    }

    public function testNoMembersMeansNoChanges(): void
    {
        $this->assertSame([], $this->policy()->assign([$this->task(1, 'Weekly', 'high')], [], $this->now()));
    }

    public function testTaskAssignedToAnUnknownMemberIsReassigned(): void
    {
        $task = $this->task(1, 'Once', 'med', assignee: 99, lastAssignedAt: '2026-10-18 12:00:00');

        $this->assertSame([1 => 1], $this->policy()->assign([$task], [1, 2], $this->now()));
    }

    public function testTiesAreBrokenByTheInjectedTieBreaker(): void
    {
        $tieBreaker = new class implements TieBreaker {
            /** @var array<int, array<int, int>> */
            public array $calls = [];

            public function pick(array $candidateIds): int
            {
                $this->calls[] = $candidateIds;

                return $candidateIds[array_key_last($candidateIds)];
            }
        };

        $decisions = (new TaskAssignmentPolicy($tieBreaker))->assign([$this->task(1, 'Weekly', 'med')], [1, 2, 3], $this->now());

        $this->assertSame([1 => 3], $decisions);
        $this->assertSame([[1, 2, 3]], $tieBreaker->calls);
    }

    private function policy(): TaskAssignmentPolicy
    {
        return new TaskAssignmentPolicy(new LowestIdTieBreaker());
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable(self::NOW);
    }

    private function task(
        int $id,
        ?string $recurrence,
        string $priority,
        TaskStatus $status = TaskStatus::PENDING,
        ?int $assignee = null,
        ?string $lastAssignedAt = null
    ): Task {
        return new Task(
            id: $id,
            title: new TaskTitle('Task ' . $id),
            householdId: 'house-1',
            createdAt: new DateTimeImmutable('2026-01-01 00:00:00'),
            status: $status,
            assignedMemberId: $assignee,
            recurrence: $recurrence,
            priority: $priority,
            lastAssignedAt: $lastAssignedAt === null ? null : new DateTimeImmutable($lastAssignedAt)
        );
    }
}
