<?php

declare(strict_types=1);

namespace App\Domain\Tasks;

use DateTimeImmutable;

class Task
{
    public function __construct(
        private readonly string $id,
        private TaskTitle $title,
        private TaskStatus $status = TaskStatus::PENDING,
        private readonly string $householdId,
        private readonly DateTimeImmutable $createdAt,
        private ?DateTimeImmutable $completedAt = null
    ) {
    }

    public static function create(
        string $id,
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

    public function id(): string
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
}
