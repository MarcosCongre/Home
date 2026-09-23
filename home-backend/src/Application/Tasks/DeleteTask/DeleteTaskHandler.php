<?php

declare(strict_types=1);

namespace App\Application\Tasks\DeleteTask;

use App\Domain\Tasks\TaskRepositoryInterface;

final class DeleteTaskHandler
{
    public function __construct(private readonly TaskRepositoryInterface $taskRepository)
    {
    }

    public function handle(int $taskId): void
    {
        if ($this->taskRepository->findById($taskId) === null) {
            throw new \RuntimeException('Task not found.');
        }

        $this->taskRepository->delete($taskId);
    }
}