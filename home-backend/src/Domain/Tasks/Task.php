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
        private string $priority = 'med'
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
        ?string $priority = null
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
            priority: $priority ?? 'med'
        );
    }

    public function complete(): void
    {
        if ($this->status === TaskStatus::COMPLETED) {
            throw new \RuntimeException('Task is already completed.');
        }

        $this->status = TaskStatus::COMPLETED;
        $this->completedAt = new DateTimeImmutable();
    }

    public function update(
        string $title,
        ?int $assignedMemberId,
        ?TaskStatus $status = null,
        ?string $day = null,
        ?string $time = null,
        ?string $category = null,
        ?string $recurrence = null,
        ?string $priority = null
    ): void {
        $this->title = new TaskTitle($title);
        $this->assignedMemberId = $assignedMemberId;

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

        if ($status !== null && $status !== $this->status) {
            $this->status = $status;
            $this->completedAt = $status === TaskStatus::COMPLETED ? new DateTimeImmutable() : null;
        }
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

    public function assignGeneratedId(int $id): void
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException('Task id must be a positive integer.');
        }

        $this->id = $id;
    }
}
