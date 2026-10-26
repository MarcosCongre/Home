<?php

declare(strict_types=1);

namespace App\Application\Tasks\DeleteTask;

use App\Domain\Tasks\TaskRepositoryInterface;
use DateTimeImmutable;

final class DeleteTaskHandler
{
    public function __construct(private readonly TaskRepositoryInterface $taskRepository)
    {
    }

    public function handle(int $taskId): void
    {
        // Already-deleted tasks are not returned, so they are reported as not found too.
        $task = $this->taskRepository->findById($taskId);

        if ($task === null) {
            throw new \RuntimeException('Task not found.');
        }

        $task->delete(new DateTimeImmutable());
        $this->taskRepository->save($task);
    }
}
