<?php

declare(strict_types=1);

namespace App\Application\Tasks\UpdateTask;

use App\Domain\Tasks\Task;
use App\Domain\Tasks\TaskRepositoryInterface;

final class UpdateTaskHandler
{
    public function __construct(private readonly TaskRepositoryInterface $taskRepository)
    {
    }

    public function handle(UpdateTaskCommand $command): Task
    {
        $task = $this->taskRepository->findById($command->taskId);

        if ($task === null) {
            throw new \RuntimeException('Task not found.');
        }

        $task->update($command->title, $command->assignedMemberId, $command->status);
        $this->taskRepository->save($task);

        return $task;
    }
}