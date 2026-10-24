<?php

declare(strict_types=1);

namespace App\Domain\Tasks\Catalog;

use DateTimeImmutable;

// A named recurrence meaning "every N days".
final class RecurrenceType
{
    public const MIN_INTERVAL_DAYS = 1;
    public const MAX_INTERVAL_DAYS = 365;

    private string $name;
    private int $intervalDays;

    public function __construct(
        private int $id,
        private readonly string $householdId,
        string $name,
        int $intervalDays,
        private readonly DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
        private ?DateTimeImmutable $deletedAt = null
    ) {
        if (trim($this->householdId) === '') {
            throw new \InvalidArgumentException('Household id cannot be empty.');
        }

        $this->name = CatalogName::normalize($name, 'Recurrence type');
        $this->intervalDays = self::validateInterval($intervalDays);
    }

    public static function create(int $id, string $householdId, string $name, int $intervalDays, DateTimeImmutable $now): self
    {
        return new self($id, $householdId, $name, $intervalDays, $now, $now);
    }

    public function id(): int
    {
        return $this->id;
    }

    public function householdId(): string
    {
        return $this->householdId;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function intervalDays(): int
    {
        return $this->intervalDays;
    }

    public function createdAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function assignGeneratedId(int $id): void
    {
        if ($id <= 0) {
            throw new \InvalidArgumentException('Recurrence type id must be a positive integer.');
        }

        $this->id = $id;
    }

    public function update(string $name, int $intervalDays, DateTimeImmutable $now): void
    {
        $this->name = CatalogName::normalize($name, 'Recurrence type');
        $this->intervalDays = self::validateInterval($intervalDays);
        $this->updatedAt = $now;
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

    private static function validateInterval(int $intervalDays): int
    {
        if ($intervalDays < self::MIN_INTERVAL_DAYS || $intervalDays > self::MAX_INTERVAL_DAYS) {
            throw new \InvalidArgumentException(sprintf(
                'Recurrence interval must be between %d and %d days.',
                self::MIN_INTERVAL_DAYS,
                self::MAX_INTERVAL_DAYS
            ));
        }

        return $intervalDays;
    }
}
