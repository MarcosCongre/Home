<?php

declare(strict_types=1);

namespace App\Application\Alerts\DismissAlert;

use App\Domain\Alerts\AlertRepositoryInterface;

final class DismissAlertHandler
{
    public function __construct(
        private readonly AlertRepositoryInterface $alertRepository
    ) {
    }

    public function handle(DismissAlertCommand $command): void
    {
        $this->alertRepository->dismiss($command->alertId);
    }
}
