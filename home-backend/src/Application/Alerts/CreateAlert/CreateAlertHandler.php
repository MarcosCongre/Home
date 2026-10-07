<?php

declare(strict_types=1);

namespace App\Application\Alerts\CreateAlert;

use App\Domain\Alerts\Alert;
use App\Domain\Alerts\AlertRepositoryInterface;
use DateTimeImmutable;

final class CreateAlertHandler
{
    public function __construct(
        private readonly AlertRepositoryInterface $alertRepository
    ) {
    }

    public function handle(CreateAlertCommand $command): Alert
    {
        $alert = Alert::create(
            id: 0,
            householdId: $command->householdId,
            memberId: $command->memberId,
            title: $command->title,
            body: $command->body,
            icon: $command->icon,
            urgent: $command->urgent,
            createdAt: $command->createdAt ?? new DateTimeImmutable()
        );

        return $this->alertRepository->save($alert);
    }
}
