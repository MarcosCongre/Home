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
        private ?int $assignedMemberId = null
    ) {
    }

    public static function create(
        int $id,
        string $title,
        string $householdId,
        DateTimeImmutable $createdAt
    ): self {
        return new self(
            id: $id,
            title: new TaskTitle($title),
            status: TaskStatus::PENDING,
            householdId: $householdId,
            createdAt: $createdAt
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

    public function update(string $title, ?int $assignedMemberId, ?TaskStatus $status = null): void
    {
        $this->title = new TaskTitle($title);
        $this->assignedMemberId = $assignedMemberId;

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

    public function assignGeneratedId(int $id): void
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException('Task id must be a positive integer.');
        }

        $this->id = $id;
    }
}
