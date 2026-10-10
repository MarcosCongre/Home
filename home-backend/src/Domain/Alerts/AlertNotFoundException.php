<?php

declare(strict_types=1);

namespace App\Domain\Alerts;

use RuntimeException;

final class AlertNotFoundException extends RuntimeException
{
    public static function inHousehold(int $id, string $householdId): self
    {
        return new self(sprintf('Alert %d was not found in household %s.', $id, $householdId));
    }
}
