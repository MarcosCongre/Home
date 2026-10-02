<?php

declare(strict_types=1);

namespace App\Application\Tasks\CreateTask;

use App\Domain\Tasks\Task;
use App\Domain\Tasks\TaskRepositoryInterface;
use DateTimeImmutable;

final class CreateTaskHandler
{
    public function __construct(
        private readonly TaskRepositoryInterface $taskRepository
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

        return $task;
    }
}
