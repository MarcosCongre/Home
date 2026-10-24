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

    // Recurrence ids double as their interval in these tests: id 7 is "every 7 days".
    private const INTERVALS = [1 => 1, 3 => 3, 7 => 7, 14 => 14, 30 => 30];

    private const DAILY = 1;
    private const TWICE_A_WEEK = 3;
    private const WEEKLY = 7;
    private const BIWEEKLY = 14;
    private const MONTHLY = 30;

    /**
     * @return array<string, array{?int, string, float}>
     */
    public static function weights(): array
    {
        return [
            'every day, high' => [1, 'high', 21.0],
            'every 7 days, medium' => [7, 'medium', 2.0],
            'every 7 days, high' => [7, 'high', 3.0],
            'every 14 days, high' => [14, 'high', 1.5],
            'every 2 days, low' => [2, 'low', 3.5],
            'one-off, med' => [null, 'med', 2.0],
            'one-off, high' => [null, 'high', 3.0],
            'one-off, low' => [null, 'low', 1.0],
            'unknown priority' => [7, 'urgent', 2.0],
        ];
    }

    #[DataProvider('weights')]
    public function testItWeighsTasksBySevenOverTheIntervalTimesPriority(?int $intervalDays, string $priority, float $expected): void
    {
        $this->assertSame($expected, TaskAssignmentPolicy::weightOf($intervalDays, $priority));
    }

    public function testItWeighsNonDivisorIntervalsProportionally(): void
    {
        $this->assertEqualsWithDelta(7 / 30, TaskAssignmentPolicy::weightOf(30, 'low'), 1e-9);
        $this->assertEqualsWithDelta(14 / 3, TaskAssignmentPolicy::weightOf(3, 'med'), 1e-9);
    }

    public function testRecurringTaskStaysWithItsAssigneeWhileNotCompletedEvenDaysLater(): void
    {
        $task = $this->task(1, self::DAILY, 'med', assignee: 1, lastAssignedAt: '2026-10-01 12:00:00');

        $this->assertSame([], $this->assign([$task], [1, 2]));
    }

    public function testRecurringTaskStaysWithItsAssigneeOnTheCompletionDay(): void
    {
        $task = $this->task(
            1,
            self::TWICE_A_WEEK,
            'med',
            assignee: 1,
            lastAssignedAt: '2026-10-16 09:00:00',
            completedAt: '2026-10-19 08:00:00'
        );

        $this->assertSame([], $this->assign([$task], [1, 2], new DateTimeImmutable('2026-10-19 23:59:59')));
    }

    public function testRecurringTaskRotatesFromTheDayAfterCompletion(): void
    {
        // Completed on Monday: free to rotate from Tuesday.
        $task = $this->task(
            1,
            self::TWICE_A_WEEK,
            'med',
            assignee: 1,
            lastAssignedAt: '2026-10-16 09:00:00',
            completedAt: '2026-10-19 21:00:00'
        );

        $this->assertSame([1 => 2], $this->assign([$task], [1, 2], new DateTimeImmutable('2026-10-20 00:00:00')));
    }

    public function testCompletionDayFollowsTheClockTimezone(): void
    {
        // 02:30 UTC on the 19th is 23:30 on the 18th in Buenos Aires, so the 19th is already the next day there.
        $task = $this->task(
            1,
            self::WEEKLY,
            'med',
            assignee: 1,
            lastAssignedAt: '2026-10-10 09:00:00 America/Argentina/Buenos_Aires',
            completedAt: '2026-10-19 02:30:00 UTC'
        );
        $now = new DateTimeImmutable('2026-10-19 08:00:00 America/Argentina/Buenos_Aires');

        $this->assertSame([1 => 2], $this->assign([$task], [1, 2], $now));
        $this->assertSame([], $this->assign([$task], [1, 2], $now->setTimezone(new \DateTimeZone('UTC'))));
    }

    public function testACompletionBeforeTheLastAssignmentDoesNotCount(): void
    {
        $task = $this->task(
            1,
            self::WEEKLY,
            'med',
            assignee: 2,
            lastAssignedAt: '2026-10-15 12:00:00',
            completedAt: '2026-10-14 12:00:00'
        );

        $this->assertSame([], $this->assign([$task], [1, 2]));
    }

    public function testACompletionAtTheLastAssignmentCounts(): void
    {
        $task = $this->task(
            1,
            self::WEEKLY,
            'med',
            assignee: 2,
            lastAssignedAt: '2026-10-15 12:00:00',
            completedAt: '2026-10-15 12:00:00'
        );

        $this->assertSame([1 => 1], $this->assign([$task], [1, 2]));
    }

    public function testACompletionWithoutLastAssignmentDateCounts(): void
    {
        $task = $this->task(1, self::WEEKLY, 'med', assignee: 2, completedAt: '2026-10-15 12:00:00');

        $this->assertSame([1 => 1], $this->assign([$task], [1, 2]));
    }

    public function testCompletedNonRepeatableRecurringTaskRotatesFromTheDayAfterCompletion(): void
    {
        $task = $this->task(
            1,
            self::WEEKLY,
            'med',
            status: TaskStatus::COMPLETED,
            assignee: 1,
            lastAssignedAt: '2026-10-10 12:00:00',
            completedAt: '2026-10-18 12:00:00'
        );

        $this->assertSame([], $this->assign([$task], [1, 2], new DateTimeImmutable('2026-10-18 20:00:00')));
        $this->assertSame([1 => 2], $this->assign([$task], [1, 2]));
    }

    public function testARecurrenceMissingFromTheIntervalsIsStillRecurring(): void
    {
        $locked = $this->task(1, 99, 'med', assignee: 1, lastAssignedAt: '2026-09-01 13:00:00');
        $free = $this->task(2, 99, 'med', assignee: 1, lastAssignedAt: '2026-09-01 11:00:00', completedAt: '2026-10-18 11:00:00');

        $this->assertSame([2 => 2], $this->assign([$locked, $free], [1, 2]));
    }

    public function testUnassignedTaskGetsAnAssignee(): void
    {
        $task = $this->task(1, null, 'med');

        $this->assertSame([1 => 1], $this->assign([$task], [1, 2]));
    }

    public function testRecurringTaskWithoutLastAssignmentDateKeepsItsAssigneeUntilCompleted(): void
    {
        $task = $this->task(1, self::WEEKLY, 'med', assignee: 1);

        $this->assertSame([], $this->assign([$task], [1, 2]));
    }

    public function testCompletedOneOffTasksAreUntouched(): void
    {
        $unassigned = $this->task(1, null, 'high', status: TaskStatus::COMPLETED);
        $assigned = $this->task(2, null, 'high', status: TaskStatus::COMPLETED, assignee: 1);

        $this->assertSame([], $this->assign([$unassigned, $assigned], [1, 2]));
    }

    public function testCompletedRecurringTasksAreStillEligible(): void
    {
        $task = $this->task(1, self::WEEKLY, 'med', status: TaskStatus::COMPLETED);

        $this->assertSame([1 => 1], $this->assign([$task], [1, 2]));
    }

    public function testAssignedPendingOneOffKeepsItsAssignee(): void
    {
        $task = $this->task(1, null, 'med', assignee: 2, lastAssignedAt: '2026-01-01 00:00:00');

        $this->assertSame([], $this->assign([$task], [1, 2]));
    }

    public function testAssignedOneOffKeepsItsAssigneeEvenAfterAnOldCompletion(): void
    {
        $task = $this->task(1, null, 'med', assignee: 2, lastAssignedAt: '2026-10-01 00:00:00', completedAt: '2026-10-02 00:00:00');

        $this->assertSame([], $this->assign([$task], [1, 2]));
    }

    public function testHeavierTasksAreSpreadAcrossMembers(): void
    {
        $tasks = [
            $this->task(1, self::WEEKLY, 'high'),
            $this->task(2, self::TWICE_A_WEEK, 'high'),
            $this->task(3, self::WEEKLY, 'high'),
            $this->task(4, self::TWICE_A_WEEK, 'high'),
        ];

        $decisions = $this->assign($tasks, [1, 2]);

        $this->assertCount(4, $decisions);
        $this->assertNotSame($decisions[2], $decisions[4], 'The two heaviest tasks must not pile on one member.');
        $this->assertNotSame($decisions[1], $decisions[3]);
    }

    public function testLockedTasksCountTowardMemberLoad(): void
    {
        $locked = $this->task(1, self::DAILY, 'high', assignee: 1, lastAssignedAt: '2026-10-19 06:00:00');
        $unassigned = $this->task(2, self::WEEKLY, 'low');

        $this->assertSame([2 => 2], $this->assign([$locked, $unassigned], [1, 2]));
    }

    public function testSingleMemberGetsEverything(): void
    {
        $tasks = [
            $this->task(1, self::DAILY, 'high'),
            $this->task(2, self::WEEKLY, 'low'),
            $this->task(3, self::WEEKLY, 'med', assignee: 7, lastAssignedAt: '2026-10-01 00:00:00'),
            $this->task(4, self::WEEKLY, 'med', assignee: 5, lastAssignedAt: '2026-10-01 00:00:00'),
        ];

        $this->assertSame([1 => 5, 2 => 5, 3 => 5], $this->assign($tasks, [5]));
    }

    public function testNoMembersMeansNoChanges(): void
    {
        $this->assertSame([], $this->assign([$this->task(1, self::WEEKLY, 'high')], []));
    }

    public function testTaskAssignedToAnUnknownMemberIsReassigned(): void
    {
        $task = $this->task(1, null, 'med', assignee: 99, lastAssignedAt: '2026-10-18 12:00:00');

        $this->assertSame([1 => 1], $this->assign([$task], [1, 2]));
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

        $decisions = (new TaskAssignmentPolicy($tieBreaker))
            ->assign([$this->task(1, self::WEEKLY, 'med')], [1, 2, 3], $this->now(), self::INTERVALS);

        $this->assertSame([1 => 3], $decisions);
        $this->assertSame([[1, 2, 3]], $tieBreaker->calls);
    }

    /**
     * @param array<int, Task> $tasks
     * @param array<int, int> $memberIds
     * @return array<int, int>
     */
    private function assign(array $tasks, array $memberIds, ?DateTimeImmutable $now = null): array
    {
        return (new TaskAssignmentPolicy(new LowestIdTieBreaker()))->assign($tasks, $memberIds, $now ?? $this->now(), self::INTERVALS);
    }

    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable(self::NOW);
    }

    private function task(
        int $id,
        ?int $recurrenceId,
        string $priority,
        TaskStatus $status = TaskStatus::PENDING,
        ?int $assignee = null,
        ?string $lastAssignedAt = null,
        ?string $completedAt = null
    ): Task {
        return new Task(
            id: $id,
            title: new TaskTitle('Task ' . $id),
            householdId: 'house-1',
            createdAt: new DateTimeImmutable('2026-01-01 00:00:00'),
            status: $status,
            completedAt: $completedAt === null ? null : new DateTimeImmutable($completedAt),
            assignedMemberId: $assignee,
            recurrenceId: $recurrenceId,
            priority: $priority,
            lastAssignedAt: $lastAssignedAt === null ? null : new DateTimeImmutable($lastAssignedAt)
        );
    }
}
