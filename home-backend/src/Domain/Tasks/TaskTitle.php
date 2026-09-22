<?php

declare(strict_types=1);

namespace App\Domain\Tasks;

class TaskTitle
{
    public function __construct(
        private string $value
    ) {
        $normalizedValue = trim($value);

        if ($normalizedValue === '') {
            throw new \InvalidArgumentException('Task title cannot be empty.');
        }

        if (mb_strlen($normalizedValue) > 120) {
            throw new \InvalidArgumentException('Task title exceeds the maximum length.');
        }

        $this->value = $normalizedValue;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
