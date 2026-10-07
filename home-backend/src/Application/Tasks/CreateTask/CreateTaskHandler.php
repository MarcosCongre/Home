<?php

declare(strict_types=1);

namespace App\Application\Tasks\CreateTask;

use App\Application\Alerts\CreateAlert\CreateAlertCommand;
use App\Application\Alerts\CreateAlert\CreateAlertHandler;
use App\Domain\Alerts\AlertRepositoryInterface;
use App\Domain\Tasks\Task;
use App\Domain\Tasks\TaskRepositoryInterface;
use DateTimeImmutable;

final class CreateTaskHandler
{
    public function __construct(
        private readonly TaskRepositoryInterface $taskRepository,
        private readonly ?AlertRepositoryInterface $alertRepository = null
    ) {
    }

    public function handle(CreateTaskCommand $command): Task
    {
        $task = Task::create(
            id: 0,
            title: $command->title,
            householdId: $command->householdId,
            createdAt: new DateTimeImmutable(),
            day: $command->day,
            time: $command->time,
            category: $command->category,
            recurrence: $command->recurrence,
            priority: $command->priority
        );

        if ($command->assignedMemberId !== null) {
            $task->update(
                title: $command->title,
                assignedMemberId: $command->assignedMemberId,
                day: $command->day,
                time: $command->time,
                category: $command->category,
                recurrence: $command->recurrence,
                priority: $command->priority
            );
        }

        $this->taskRepository->save($task);

        if ($command->assignedMemberId !== null && $this->alertRepository !== null) {
            $alertHandler = new CreateAlertHandler($this->alertRepository);
            $alertHandler->handle(new CreateAlertCommand(
                householdId: $task->householdId(),
                memberId: $command->assignedMemberId,
                title: 'Nueva tarea asignada',
                body: sprintf(
                    'Se te asignó la tarea "%s". Miembro #%d.',
                    $task->title()->value(),
                    $command->assignedMemberId
                ),
                icon: 'check-circle',
                urgent: false,
                createdAt: new DateTimeImmutable()
            ));
        }

        return $task;
    }
}
