<?php

declare(strict_types=1);

namespace App\Domain\Tasks\Assignment;

use App\Domain\Tasks\Task;
use App\Domain\Tasks\TaskStatus;
use DateTimeImmutable;

/**
 * Distributes household tasks among members, balancing load by frequency and
 * priority and rotating a recurring task only when its cycle has ended.
 */
final class TaskAssignmentPolicy
{
    /** Times per week, keyed by normalized recurrence. */
    private const FREQUENCIES = [
        'daily' => 7.0,
        'mon & thu' => 2.0,
        'tue & fri' => 2.0,
        'weekly' => 1.0,
        'biweekly' => 0.5,
        'monthly' => 0.25,
    ];

    private const PRIORITIES = [
        'low' => 1.0,
        'med' => 2.0,
        'medium' => 2.0,
        'high' => 3.0,
    ];

    private const ONE_OFF = ['', 'once', 'none'];

    private const DEFAULT_FREQUENCY = 1.0;

    private const DEFAULT_PRIORITY = 2.0;

    public function __construct(
        private readonly TieBreaker $tieBreaker
    ) {
    }

    public static function weightOf(?string $recurrence, string $priority): float
    {
        $frequency = self::FREQUENCIES[self::normalize($recurrence)] ?? self::DEFAULT_FREQUENCY;

        return $frequency * (self::PRIORITIES[self::normalize($priority)] ?? self::DEFAULT_PRIORITY);
    }

    /**
     * Returns when the ownership started at $assignedAt ends, or null for one-off tasks.
     */
    public static function cycleEnd(?string $recurrence, DateTimeImmutable $assignedAt): ?DateTimeImmutable
    {
        $normalized = self::normalize($recurrence);
        if (in_array($normalized, self::ONE_OFF, true)) {
            return null;
        }

        return match ($normalized) {
            'biweekly' => $assignedAt->modify('+14 days'),
            'monthly' => self::addCalendarMonth($assignedAt),
            default => $assignedAt->modify('+7 days'),
        };
    }

    /**
     * @param array<int, Task> $tasks
     * @param array<int, int> $memberIds
     * @return array<int, int> member id keyed by task id, only for tasks whose assignee changes
     */
    public function assign(array $tasks, array $memberIds, DateTimeImmutable $now): array
    {
        $memberIds = array_values(array_unique($memberIds));
        if ($memberIds === []) {
            return [];
        }

        $loads = array_fill_keys($memberIds, 0.0);
        $pending = [];

        foreach ($tasks as $task) {
            if (!self::isEligible($task)) {
                continue;
            }

            $assignee = $task->assignedMemberId();
            if ($assignee !== null && isset($loads[$assignee]) && self::isLocked($task, $now)) {
                $loads[$assignee] += self::weightOf($task->recurrence(), $task->priority());
                continue;
            }

            $pending[] = $task;
        }

        // Heaviest first; task id keeps the order deterministic for equal weights.
        usort(
            $pending,
            static fn (Task $a, Task $b): int => [self::weightOf($b->recurrence(), $b->priority()), $a->id()]
                <=> [self::weightOf($a->recurrence(), $a->priority()), $b->id()]
        );

        $decisions = [];
        foreach ($pending as $task) {
            $memberId = $this->leastLoaded($loads, count($memberIds) > 1 ? $task->assignedMemberId() : null);
            $loads[$memberId] += self::weightOf($task->recurrence(), $task->priority());

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

    private static function isEligible(Task $task): bool
    {
        return self::isRecurring($task) || $task->status() === TaskStatus::PENDING;
    }

    private static function isLocked(Task $task, DateTimeImmutable $now): bool
    {
        if (!self::isRecurring($task)) {
            return true;
        }

        $assignedAt = $task->lastAssignedAt();

        return $assignedAt !== null && self::cycleEnd($task->recurrence(), $assignedAt) > $now;
    }

    private static function isRecurring(Task $task): bool
    {
        return !in_array(self::normalize($task->recurrence()), self::ONE_OFF, true);
    }

    private static function addCalendarMonth(DateTimeImmutable $from): DateTimeImmutable
    {
        $firstOfNextMonth = $from->modify('first day of next month');
        $day = min((int) $from->format('j'), (int) $firstOfNextMonth->format('t'));

        return $firstOfNextMonth->setDate(
            (int) $firstOfNextMonth->format('Y'),
            (int) $firstOfNextMonth->format('n'),
            $day
        );
    }

    private static function normalize(?string $value): string
    {
        return strtolower(trim((string) $value));
    }
}
