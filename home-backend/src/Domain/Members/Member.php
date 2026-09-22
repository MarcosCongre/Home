<?php

declare(strict_types=1);

namespace App\Domain\Members;

final class Member
{
    public function __construct(
        private readonly string $id,
        private string $name,
        private string $avatar,
        private string $color,
        private readonly string $householdId
    ) {
        $this->validate();
    }

    public static function create(
        string $id,
        string $name,
        string $avatar,
        string $color,
        string $householdId
    ): self {
        return new self(
            id: $id,
            name: $name,
            avatar: $avatar,
            color: $color,
            householdId: $householdId
        );
    }

    public function id(): string
    {
        return $this->id;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function avatar(): string
    {
        return $this->avatar;
    }

    public function color(): string
    {
        return $this->color;
    }

    public function householdId(): string
    {
        return $this->householdId;
    }

    public function update(string $name, string $avatar, string $color): void
    {
        $this->name = trim($name);
        $this->avatar = trim($avatar);
        $this->color = trim($color);

        $this->validate();
    }

    private function validate(): void
    {
        if (trim($this->name) === '') {
            throw new \InvalidArgumentException('Member name cannot be empty.');
        }

        if (trim($this->avatar) === '') {
            throw new \InvalidArgumentException('Member avatar cannot be empty.');
        }

        if (!preg_match('/^#([A-Fa-f0-9]{3}|[A-Fa-f0-9]{6})$/', $this->color)) {
            throw new \InvalidArgumentException('Member color must be a valid hex color.');
        }

        if (trim($this->householdId) === '') {
            throw new \InvalidArgumentException('Household id cannot be empty.');
        }
    }
}
