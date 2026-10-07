<?php

declare(strict_types=1);

namespace App\Application\Alerts\DismissAllAlerts;

use App\Domain\Alerts\AlertRepositoryInterface;

final class DismissAllAlertsHandler
{
    public function __construct(
        private readonly AlertRepositoryInterface $alertRepository
    ) {
    }

    public function handle(DismissAllAlertsCommand $command): void
    {
        $this->alertRepository->dismissAll($command->householdId);
    }
}
