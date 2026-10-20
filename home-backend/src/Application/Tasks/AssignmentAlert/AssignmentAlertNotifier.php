<?php

declare(strict_types=1);

namespace App\Application\Tasks\AssignmentAlert;

use App\Application\Alerts\CreateAlert\CreateAlertCommand;
use App\Application\Alerts\CreateAlert\CreateAlertHandler;
use App\Domain\Alerts\AlertRepositoryInterface;
use App\Domain\Tasks\Task;
use DateTimeImmutable;
use Throwable;

/**
 * Tells a member that a task was assigned to them. The task is already
 * persisted when this runs, so an alert failure is logged and never thrown.
 */
final class AssignmentAlertNotifier
{
    public function __construct(
        private readonly ?AlertRepositoryInterface $alertRepository
    ) {
    }

    public function notify(Task $task, int $memberId): void
    {
        if ($this->alertRepository === null) {
            return;
        }

        try {
            (new CreateAlertHandler($this->alertRepository))->handle(new CreateAlertCommand(
                householdId: $task->householdId(),
                memberId: $memberId,
                title: 'Nueva tarea asignada',
                body: sprintf('Se te asignó la tarea "%s". Miembro #%d.', $task->title()->value(), $memberId),
                icon: 'check-circle',
                urgent: false,
                createdAt: new DateTimeImmutable()
            ));
        } catch (Throwable $exception) {
            error_log(sprintf('Unable to create task assignment alert: %s', $exception->getMessage()));
        }
    }
}
