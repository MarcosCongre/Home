<?php

declare(strict_types=1);

namespace App\Application\Tasks\CompleteTask;

use App\Application\Alerts\CreateAlert\CreateAlertCommand;
use App\Application\Alerts\CreateAlert\CreateAlertHandler;
use App\Domain\Alerts\AlertRepositoryInterface;
use App\Domain\Tasks\Task;
use App\Domain\Tasks\TaskRepositoryInterface;
use DateTimeImmutable;

final class CompleteTaskHandler
{
    public function __construct(
        private TaskRepositoryInterface $taskRepository,
        private ?AlertRepositoryInterface $alertRepository = null
    ) {
    }

    public function handle(CompleteTaskCommand $command): Task
    {
        $task = $this->taskRepository->findById($command->taskId);

        if ($task === null) {
            throw new \RuntimeException(sprintf('Task %s was not found.', $command->taskId));
        }

        $task->complete();
        $this->taskRepository->save($task);

        if ($this->alertRepository !== null) {
            $memberId = $task->assignedMemberId();
            $description = $memberId !== null
                ? sprintf(
                    'Tarea completada: "%s". Asignada a miembro #%d.',
                    $task->title()->value(),
                    $memberId
                )
                : sprintf('Tarea completada: "%s".', $task->title()->value());

            $alertHandler = new CreateAlertHandler($this->alertRepository);
            $alertHandler->handle(new CreateAlertCommand(
                householdId: $task->householdId(),
                memberId: $memberId,
                title: 'Tarea completada',
                body: $description,
                icon: 'check-circle',
                urgent: false,
                createdAt: new DateTimeImmutable()
            ));
        }

        return $task;
    }
}
