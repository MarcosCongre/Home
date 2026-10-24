<?php

declare(strict_types=1);

namespace App\Domain\Tasks\Catalog;

use RuntimeException;

// Unknown, soft-deleted and other-household entries are all reported the same way.
final class CatalogEntryNotFoundException extends RuntimeException
{
    public static function category(int $id, string $householdId): self
    {
        return new self(sprintf('Category %d was not found in household %s.', $id, $householdId));
    }

    public static function recurrenceType(int $id, string $householdId): self
    {
        return new self(sprintf('Recurrence type %d was not found in household %s.', $id, $householdId));
    }
}
