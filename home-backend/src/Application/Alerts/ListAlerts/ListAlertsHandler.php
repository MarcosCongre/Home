<?php

declare(strict_types=1);

namespace App\Application\Alerts\ListAlerts;

use App\Domain\Alerts\Alert;
use App\Domain\Alerts\AlertRepositoryInterface;

final class ListAlertsHandler
{
    public function __construct(
        private readonly AlertRepositoryInterface $alertRepository
    ) {
    }

    /**
     * @return array<int, Alert>
     */
    public function handle(ListAlertsQuery $query): array
    {
        $alerts = $this->alertRepository->findByHousehold($query->householdId);

        usort(
            $alerts,
            static fn (Alert $a, Alert $b): int => $b->createdAt() <=> $a->createdAt()
        );

        return $alerts;
    }
}
