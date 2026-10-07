<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Application\Alerts\DismissAllAlerts\DismissAllAlertsCommand;
use App\Application\Alerts\DismissAllAlerts\DismissAllAlertsHandler;
use App\Application\Alerts\DismissAlert\DismissAlertCommand;
use App\Application\Alerts\DismissAlert\DismissAlertHandler;
use App\Application\Alerts\ListAlerts\ListAlertsHandler;
use App\Application\Alerts\ListAlerts\ListAlertsQuery;
use App\Domain\Alerts\Alert;
use App\Domain\Alerts\AlertRepositoryInterface;
use App\Domain\Members\MemberRepositoryInterface;

final class AlertController
{
    public function __construct(
        private AlertRepositoryInterface $alertRepository,
        private ?MemberRepositoryInterface $memberRepository = null
    ) {
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(string $householdId): array
    {
        $handler = new ListAlertsHandler($this->alertRepository);
        $alerts = $handler->handle(new ListAlertsQuery($householdId));

        return array_map(fn (Alert $alert): array => $this->serializeAlert($alert), $alerts);
    }

    public function dismiss(int $id): array
    {
        (new DismissAlertHandler($this->alertRepository))->handle(new DismissAlertCommand($id));

        return ['dismissed' => true, 'id' => $id];
    }

    public function dismissAll(string $householdId): array
    {
        (new DismissAllAlertsHandler($this->alertRepository))->handle(new DismissAllAlertsCommand($householdId));

        return ['dismissedAll' => true, 'householdId' => $householdId];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeAlert(Alert $alert): array
    {
        $member = $alert->memberId() === null || $this->memberRepository === null
            ? null
            : $this->memberRepository->findById($alert->memberId());

        return [
            'id' => $alert->id(),
            'title' => $alert->title(),
            'body' => $alert->body(),
            'icon' => $alert->icon(),
            'urgent' => $alert->urgent(),
            'status' => $alert->status()->value,
            'time' => $alert->createdAt()->format('h:i A'),
            'user' => $member?->name(),
        ];
    }
}
