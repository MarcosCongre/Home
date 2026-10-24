<?php

declare(strict_types=1);

namespace App\Domain\Tasks\Catalog;

use DateTimeImmutable;

final class Category
{
    private string $name;
    private ?string $color;

    public function __construct(
        private int $id,
        private readonly string $householdId,
        string $name,
        ?string $color,
        private readonly DateTimeImmutable $createdAt,
        private DateTimeImmutable $updatedAt,
        private ?DateTimeImmutable $deletedAt = null
    ) {
        if (trim($this->householdId) === '') {
            throw new \InvalidArgumentException('Household id cannot be empty.');
        }

        $this->name = CatalogName::normalize($name, 'Category');
        $this->color = self::normalizeColor($color);
    }

    public static function create(int $id, string $householdId, string $name, ?string $color, DateTimeImmutable $now): self
    {
        return new self($id, $householdId, $name, $color, $now, $now);
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

    public function color(): ?string
    {
        return $this->color;
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
            throw new \InvalidArgumentException('Category id must be a positive integer.');
        }

        $this->id = $id;
    }

    public function update(string $name, ?string $color, DateTimeImmutable $now): void
    {
        $this->name = CatalogName::normalize($name, 'Category');
        $this->color = self::normalizeColor($color);
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

    // The color is optional: null or blank means "no color".
    private static function normalizeColor(?string $color): ?string
    {
        $color = $color === null ? '' : trim($color);

        if ($color === '') {
            return null;
        }

        if (preg_match('/^#[A-Fa-f0-9]{6}$/', $color) !== 1) {
            throw new \InvalidArgumentException('Category color must be a #RRGGBB hex color.');
        }

        return $color;
    }
}
