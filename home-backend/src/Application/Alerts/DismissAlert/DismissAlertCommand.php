<?php

declare(strict_types=1);

namespace App\Application\Alerts\DismissAlert;

readonly class DismissAlertCommand
{
    public function __construct(
        public int $alertId,
        public string $householdId
    ) {
    }
}
