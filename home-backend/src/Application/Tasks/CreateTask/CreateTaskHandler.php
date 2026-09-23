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
            createdAt: new DateTimeImmutable()
        );

        if ($command->assignedMemberId !== null) {
            $task->update($command->title, $command->assignedMemberId);
        }

        $this->taskRepository->save($task);

        return $task;
    }
}
