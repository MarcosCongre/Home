<?php

declare(strict_types=1);

namespace App\Domain\Alerts;

use DateTimeImmutable;

class Alert
{
    public function __construct(
        private int $id,
        private readonly string $householdId,
        private readonly ?int $memberId,
        private string $title,
        private string $body,
        private string $icon,
        private bool $urgent,
        private AlertStatus $status,
        private readonly DateTimeImmutable $createdAt
    ) {
        $this->title = $this->normalizeRequiredString('Alert title', $title);
        $this->body = $this->normalizeRequiredString('Alert body', $body);
        $this->icon = $this->normalizeRequiredString('Alert icon', $icon);
    }

    public static function create(
        int $id,
        string $householdId,
        ?int $memberId,
        string $title,
        string $body,
        string $icon,
        bool $urgent,
        DateTimeImmutable $createdAt
    ): self {
        return new self(
            id: $id,
            householdId: $householdId,
            memberId: $memberId,
            title: $title,
            body: $body,
            icon: $icon,
            urgent: $urgent,
            status: AlertStatus::UNREAD,
            createdAt: $createdAt
        );
    }

    public function dismiss(): void
    {
        $this->status = AlertStatus::DISMISSED;
    }

    public function id(): int
    {
        return $this->id;
    }

    public function householdId(): string
    {
        return $this->householdId;
    }

    public function memberId(): ?int
    {
        return $this->memberId;
    }

    public function title(): string
    {
        return $this->title;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function icon(): string
    {
        return $this->icon;
    }

    public function urgent(): bool
    {
        return $this->urgent;
    }

    public function status(): AlertStatus
    {
        return $this->status;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function assignGeneratedId(int $id): void
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException('Alert id must be a positive integer.');
        }

        $this->id = $id;
    }

    private function normalizeRequiredString(string $fieldName, string $value): string
    {
        $normalizedValue = trim($value);

        if ($normalizedValue === '') {
            throw new \InvalidArgumentException(sprintf('%s cannot be empty.', $fieldName));
        }

        return $normalizedValue;
    }
}
