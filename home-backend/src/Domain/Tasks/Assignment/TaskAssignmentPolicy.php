<?php

declare(strict_types=1);

namespace App\Domain\Tasks\Assignment;

use App\Domain\Tasks\Task;
use App\Domain\Tasks\TaskStatus;
use DateTimeImmutable;

/**
 * Distributes household tasks among members, balancing load by frequency and
 * priority and rotating a recurring task only when its cycle has ended.
 *
 * A task's recurrence is "every N days" (its recurrence type's interval): it happens
 * 7/N times a week and its assignee keeps it for N days. A task without a recurrence
 * is one-off: it has no cycle and counts once a week.
 */
final class TaskAssignmentPolicy
{
    private const PRIORITIES = [
        'low' => 1.0,
        'med' => 2.0,
        'medium' => 2.0,
        'high' => 3.0,
    ];

    private const ONE_OFF_FREQUENCY = 1.0;

    private const DEFAULT_PRIORITY = 2.0;

    // Used when a task references a recurrence type that cannot be resolved (e.g. deleted).
    private const DEFAULT_INTERVAL_DAYS = 7;

    public function __construct(
        private readonly TieBreaker $tieBreaker
    ) {
    }

    public static function weightOf(?int $intervalDays, string $priority): float
    {
        $frequency = $intervalDays === null ? self::ONE_OFF_FREQUENCY : 7 / $intervalDays;

        return $frequency * (self::PRIORITIES[strtolower(trim($priority))] ?? self::DEFAULT_PRIORITY);
    }

    /**
     * Returns when the ownership started at $assignedAt ends, or null for one-off tasks.
     */
    public static function cycleEnd(?int $intervalDays, DateTimeImmutable $assignedAt): ?DateTimeImmutable
    {
        return $intervalDays === null ? null : $assignedAt->modify(sprintf('+%d days', $intervalDays));
    }

    /**
     * @param array<int, Task> $tasks
     * @param array<int, int> $memberIds
     * @param array<int, int> $intervalDaysByRecurrenceId the household's recurrence types
     * @return array<int, int> member id keyed by task id, only for tasks whose assignee changes
     */
    public function assign(array $tasks, array $memberIds, DateTimeImmutable $now, array $intervalDaysByRecurrenceId): array
    {
        $memberIds = array_values(array_unique($memberIds));
        if ($memberIds === []) {
            return [];
        }

        $intervalOf = static function (Task $task) use ($intervalDaysByRecurrenceId): ?int {
            $recurrenceId = $task->recurrenceId();

            return $recurrenceId === null ? null : ($intervalDaysByRecurrenceId[$recurrenceId] ?? self::DEFAULT_INTERVAL_DAYS);
        };
        $weightOf = static fn (Task $task): float => self::weightOf($intervalOf($task), $task->priority());

        $loads = array_fill_keys($memberIds, 0.0);
        $pending = [];

        foreach ($tasks as $task) {
            $intervalDays = $intervalOf($task);
            if (!self::isEligible($task, $intervalDays)) {
                continue;
            }

            $assignee = $task->assignedMemberId();
            if ($assignee !== null && isset($loads[$assignee]) && self::isLocked($task, $intervalDays, $now)) {
                $loads[$assignee] += $weightOf($task);
                continue;
            }

            $pending[] = $task;
        }

        // Heaviest first; task id keeps the order deterministic for equal weights.
        usort(
            $pending,
            static fn (Task $a, Task $b): int => [$weightOf($b), $a->id()] <=> [$weightOf($a), $b->id()]
        );

        $decisions = [];
        foreach ($pending as $task) {
            $memberId = $this->leastLoaded($loads, count($memberIds) > 1 ? $task->assignedMemberId() : null);
            $loads[$memberId] += $weightOf($task);

            if ($memberId !== $task->assignedMemberId()) {
                $decisions[$task->id()] = $memberId;
            }
        }

        ksort($decisions);

        return $decisions;
    }

    /**
     * @param array<int, float> $loads
     */
    private function leastLoaded(array $loads, ?int $excludedMemberId): int
    {
        unset($loads[$excludedMemberId ?? -1]);
        $lowest = min($loads);
        $candidates = array_keys(array_filter($loads, static fn (float $load): bool => $load === $lowest));

        return count($candidates) === 1 ? $candidates[0] : $this->tieBreaker->pick($candidates);
    }

    private static function isEligible(Task $task, ?int $intervalDays): bool
    {
        return $intervalDays !== null || $task->status() === TaskStatus::PENDING;
    }

    // One-off tasks keep their assignee; recurring ones only until the cycle ends.
    private static function isLocked(Task $task, ?int $intervalDays, DateTimeImmutable $now): bool
    {
        if ($intervalDays === null) {
            return true;
        }

        $assignedAt = $task->lastAssignedAt();

        return $assignedAt !== null && self::cycleEnd($intervalDays, $assignedAt) > $now;
    }
}
