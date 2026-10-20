<?php

declare(strict_types=1);

namespace App\Application\Tasks\ReopenTask;

use App\Domain\Tasks\Task;
use App\Domain\Tasks\TaskRepositoryInterface;

final class ReopenTaskHandler
{
    public function __construct(
        private TaskRepositoryInterface $taskRepository
    ) {
    }

    public function handle(ReopenTaskCommand $command): Task
    {
        $task = $this->taskRepository->findById($command->taskId);

        if ($task === null) {
            throw new \RuntimeException(sprintf('Task %s was not found.', $command->taskId));
        }

        $task->reopen();
        $this->taskRepository->save($task);

        return $task;
    }
}
