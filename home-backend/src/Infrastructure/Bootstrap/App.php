<?php

declare(strict_types=1);

namespace App\Infrastructure\Bootstrap;

use App\Application\Tasks\CompleteTask\CompleteTaskCommand;
use App\Application\Tasks\CompleteTask\CompleteTaskHandler;
use App\Application\Tasks\CreateTask\CreateTaskCommand;
use App\Application\Tasks\CreateTask\CreateTaskHandler;
use App\Application\Tasks\ListTasks\ListTasksHandler;
use App\Application\Tasks\ListTasks\ListTasksQuery;
use App\Domain\Tasks\Task;
use App\Domain\Tasks\TaskRepositoryInterface;
use App\Domain\Tasks\TaskStatus;

final class App
{
    public function __construct(
        private TaskRepositoryInterface $taskRepository
    ) {
    }

    /**
     * @param array<string, mixed> $request
     * @return array<string, mixed>|array<int, array<string, mixed>>
     */
    public function handle(array $request): array
    {
        $method = strtoupper((string) ($request['REQUEST_METHOD'] ?? 'GET'));
        $path = (string) ($request['PATH_INFO'] ?? '/');

        if ($method === 'POST' && $path === '/tasks') {
            $payload = $this->decodeJson((string) ($request['php://input'] ?? '{}'));
            $handler = new CreateTaskHandler($this->taskRepository);
            $task = $handler->handle(new CreateTaskCommand(
                title: (string) ($payload['title'] ?? ''),
                householdId: (string) ($payload['householdId'] ?? ''),
                userId: (string) ($payload['userId'] ?? '')
            ));

            return $this->serializeTask($task);
        }

        if ($method === 'PATCH' && preg_match('#^/tasks/([^/]+)/complete$#', $path, $matches) === 1) {
            $payload = $this->decodeJson((string) ($request['php://input'] ?? '{}'));
            $handler = new CompleteTaskHandler($this->taskRepository);
            $task = $handler->handle(new CompleteTaskCommand(
                taskId: $matches[1],
                userId: (string) ($payload['userId'] ?? '')
            ));

            return $this->serializeTask($task);
        }

        if ($method === 'GET' && $path === '/tasks') {
            $householdId = (string) ($_GET['householdId'] ?? $request['QUERY_STRING'] ?? '');
            $householdId = $this->extractHouseholdId($householdId);
            $handler = new ListTasksHandler($this->taskRepository);
            $tasks = $handler->handle(new ListTasksQuery($householdId));

            return array_map(fn (Task $task): array => $this->serializeTask($task), $tasks);
        }

        return ['error' => 'Not found'];
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

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function decodeJson(string $body): array
    {
        $decoded = json_decode($body, true);

        if (!is_array($decoded)) {
            return [];
        }

        return $decoded;
    }

    private function extractHouseholdId(string $queryString): string
    {
        if ($queryString === '') {
            return '';
        }

        parse_str($queryString, $params);

        return (string) ($params['householdId'] ?? '');
    }
}
