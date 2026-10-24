<?php

declare(strict_types=1);

namespace App\Domain\Tasks\Catalog;

// Shared name rules for catalog entries (categories and recurrence types).
final class CatalogName
{
    public const MAX_LENGTH = 64;

    public static function normalize(string $name, string $label): string
    {
        $name = trim($name);

        if ($name === '') {
            throw new \InvalidArgumentException(sprintf('%s name cannot be empty.', $label));
        }

        if (mb_strlen($name) > self::MAX_LENGTH) {
            throw new \InvalidArgumentException(sprintf('%s name cannot be longer than %d characters.', $label, self::MAX_LENGTH));
        }

        return $name;
    }

    // Names are unique per household regardless of case.
    public static function equals(string $a, string $b): bool
    {
        return mb_strtolower(trim($a)) === mb_strtolower(trim($b));
    }
}
