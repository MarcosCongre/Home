<?php

declare(strict_types=1);

namespace App\Application\Alerts\DismissAllAlerts;

readonly class DismissAllAlertsCommand
{
    public function __construct(
        public string $householdId
    ) {
    }
}
