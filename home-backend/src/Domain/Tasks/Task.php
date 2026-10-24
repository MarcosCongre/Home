<?php

declare(strict_types=1);

namespace App\Domain\Tasks;

use DateTimeImmutable;

class Task
{
    public function __construct(
        private int $id,
        private TaskTitle $title,
        private readonly string $householdId,
        private readonly DateTimeImmutable $createdAt,
        private TaskStatus $status = TaskStatus::PENDING,
        private ?DateTimeImmutable $completedAt = null,
        private ?int $assignedMemberId = null,
        private ?string $day = null,
        private ?string $time = null,
        private ?string $category = null,
        private ?string $recurrence = null,
        private string $priority = 'med',
        private ?DateTimeImmutable $lastAssignedAt = null,
        private ?DateTimeImmutable $deletedAt = null,
        private bool $repeatable = false
    ) {
    }

    public static function create(
        int $id,
        string $title,
        string $householdId,
        DateTimeImmutable $createdAt,
        ?string $day = null,
        ?string $time = null,
        ?string $category = null,
        ?string $recurrence = null,
        ?string $priority = null,
        bool $repeatable = false
    ): self {
        return new self(
            id: $id,
            title: new TaskTitle($title),
            status: TaskStatus::PENDING,
            householdId: $householdId,
            createdAt: $createdAt,
            day: $day,
            time: $time,
            category: $category,
            recurrence: $recurrence,
            priority: $priority ?? 'med',
            repeatable: $repeatable
        );
    }

    /**
     * A repeatable task is done several times a day: completing it records the
     * completion time and hands it back as pending, optionally to the next member.
     * The next assignee is ignored for non-repeatable tasks.
     */
    public function complete(?int $nextAssigneeId = null): void
    {
        if ($this->status === TaskStatus::COMPLETED) {
            throw TaskStateConflict::alreadyCompleted();
        }

        $completedAt = new DateTimeImmutable();
        $this->completedAt = $completedAt;

        if (!$this->repeatable) {
            $this->status = TaskStatus::COMPLETED;
            return;
        }

        if ($nextAssigneeId !== null && $nextAssigneeId !== $this->assignedMemberId) {
            $this->reassignTo($nextAssigneeId, $completedAt);
        }
    }

    public function reopen(): void
    {
        if ($this->status !== TaskStatus::COMPLETED) {
            throw TaskStateConflict::notCompleted();
        }

        $this->status = TaskStatus::PENDING;
        $this->completedAt = null;
    }

    public function update(
        string $title,
        ?int $assignedMemberId,
        ?TaskStatus $status = null,
        ?string $day = null,
        ?string $time = null,
        ?string $category = null,
        ?string $recurrence = null,
        ?string $priority = null,
        ?bool $repeatable = null
    ): void {
        $this->title = new TaskTitle($title);
        if ($assignedMemberId !== $this->assignedMemberId) {
            $this->assignedMemberId = $assignedMemberId;
            $this->lastAssignedAt = $assignedMemberId === null ? null : new DateTimeImmutable();
        }

        if ($day !== null) {
            $this->day = $day;
        }
        if ($time !== null) {
            $this->time = $time;
        }
        if ($category !== null) {
            $this->category = $category;
        }
        if ($recurrence !== null) {
            $this->recurrence = $recurrence;
        }
        if ($priority !== null) {
            $this->priority = $priority;
        }
        if ($repeatable !== null) {
            $this->repeatable = $repeatable;
        }

        if ($status !== null && $status !== $this->status) {
            $this->status = $status;
            $this->completedAt = $status === TaskStatus::COMPLETED ? new DateTimeImmutable() : null;
        }
    }

    public function reassignTo(int $memberId, DateTimeImmutable $assignedAt): void
    {
        $this->assignedMemberId = $memberId;
        $this->lastAssignedAt = $assignedAt;
    }

    // Drops the assignee but keeps the last assignment date for rotation history.
    public function unassign(): void
    {
        $this->assignedMemberId = null;
    }

    // Soft delete: the first deletion date wins.
    public function delete(DateTimeImmutable $now): void
    {
        $this->deletedAt ??= $now;
    }

    public function isDeleted(): bool
    {
        return $this->deletedAt !== null;
    }

    public function deletedAt(): ?DateTimeImmutable
    {
        return $this->deletedAt;
    }

    public function id(): int
    {
        return $this->id;
    }

    public function title(): TaskTitle
    {
        return $this->title;
    }

    public function status(): TaskStatus
    {
        return $this->status;
    }

    public function householdId(): string
    {
        return $this->householdId;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function completedAt(): ?DateTimeImmutable
    {
        return $this->completedAt;
    }

    public function assignedMemberId(): ?int
    {
        return $this->assignedMemberId;
    }

    public function lastAssignedAt(): ?DateTimeImmutable
    {
        return $this->lastAssignedAt;
    }

    public function day(): ?string
    {
        return $this->day;
    }

    public function time(): ?string
    {
        return $this->time;
    }

    public function category(): ?string
    {
        return $this->category;
    }

    public function recurrence(): ?string
    {
        return $this->recurrence;
    }

    public function priority(): string
    {
        return $this->priority;
    }

    public function isRepeatable(): bool
    {
        return $this->repeatable;
    }

    public function assignGeneratedId(int $id): void
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException('Task id must be a positive integer.');
        }

        $this->id = $id;
    }
}
