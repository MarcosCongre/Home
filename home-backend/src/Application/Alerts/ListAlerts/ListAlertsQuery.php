<?php

declare(strict_types=1);

namespace App\Application\Alerts\ListAlerts;

class ListAlertsQuery
{
    public function __construct(
        public string $householdId
    ) {
    }
}
