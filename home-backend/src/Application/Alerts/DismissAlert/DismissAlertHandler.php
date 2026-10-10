<?php

declare(strict_types=1);

namespace App\Application\Alerts\DismissAlert;

use App\Domain\Alerts\AlertNotFoundException;
use App\Domain\Alerts\AlertRepositoryInterface;

final class DismissAlertHandler
{
    public function __construct(
        private readonly AlertRepositoryInterface $alertRepository
    ) {
    }

    public function handle(DismissAlertCommand $command): void
    {
        if ($command->householdId === '') {
            throw new \InvalidArgumentException('householdId is required.');
        }

        if (!$this->alertRepository->dismiss($command->alertId, $command->householdId)) {
            throw AlertNotFoundException::inHousehold($command->alertId, $command->householdId);
        }
    }
}
