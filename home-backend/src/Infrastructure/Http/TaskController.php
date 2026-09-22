<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Application\Tasks\CompleteTask\CompleteTaskCommand;
use App\Application\Tasks\CompleteTask\CompleteTaskHandler;
use App\Application\Tasks\CreateTask\CreateTaskCommand;
use App\Application\Tasks\CreateTask\CreateTaskHandler;
use App\Application\Tasks\ListTasks\ListTasksHandler;
use App\Application\Tasks\ListTasks\ListTasksQuery;
use App\Domain\Tasks\Task;
use App\Domain\Tasks\TaskRepositoryInterface;

final class TaskController
{
    public function __construct(
        private TaskRepositoryInterface $taskRepository
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function create(array $payload): array
    {
        $handler = new CreateTaskHandler($this->taskRepository);
        $task = $handler->handle(new CreateTaskCommand(
            title: (string) ($payload['title'] ?? ''),
            householdId: (string) ($payload['householdId'] ?? ''),
            userId: (string) ($payload['userId'] ?? '')
        ));

        return $this->serializeTask($task);
    }

    public function complete(string $taskId, string $userId): array
    {
        $handler = new CompleteTaskHandler($this->taskRepository);
        $task = $handler->handle(new CompleteTaskCommand(
            taskId: $taskId,
            userId: $userId
        ));

        return $this->serializeTask($task);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(string $householdId): array
    {
        $handler = new ListTasksHandler($this->taskRepository);
        $tasks = $handler->handle(new ListTasksQuery($householdId));

        return array_map(fn (Task $task): array => $this->serializeTask($task), $tasks);
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeTask(Task $task): array
    {
        return [
            'id' => $task->id(),
            'title' => $task->title()->value(),
            'status' => $task->status()->value,
            'householdId' => $task->householdId(),
            'createdAt' => $task->createdAt()->format(DATE_ATOM),
            'completedAt' => $task->completedAt()?->format(DATE_ATOM),
        ];
    }
}
