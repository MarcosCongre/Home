<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Application\Tasks\CompleteTask\CompleteTaskCommand;
use App\Application\Tasks\CompleteTask\CompleteTaskHandler;
use App\Application\Tasks\CreateTask\CreateTaskCommand;
use App\Application\Tasks\CreateTask\CreateTaskHandler;
use App\Application\Tasks\ListTasks\ListTasksHandler;
use App\Application\Tasks\ListTasks\ListTasksQuery;
use App\Application\Tasks\UpdateTask\UpdateTaskCommand;
use App\Application\Tasks\UpdateTask\UpdateTaskHandler;
use App\Application\Tasks\DeleteTask\DeleteTaskHandler;
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
            userId: (string) ($payload['userId'] ?? ''),
            assignedMemberId: $this->optionalPositiveInt($payload['assignedMemberId'] ?? null),
            day: $this->optionalValidatedDay($payload['day'] ?? null),
            time: $this->optionalString($payload['time'] ?? null),
            category: $this->optionalString($payload['category'] ?? null),
            recurrence: $this->optionalString($payload['recurrence'] ?? null),
            priority: $this->optionalValidatedPriority($payload['priority'] ?? null)
        ));

        return $this->serializeTask($task);
    }

    public function update(int $taskId, array $payload): array
    {
        $status = isset($payload['status']) ? \App\Domain\Tasks\TaskStatus::tryFrom((string) $payload['status']) : null;
        $handler = new UpdateTaskHandler($this->taskRepository);
        return $this->serializeTask($handler->handle(new UpdateTaskCommand(
            taskId: $taskId,
            title: (string) ($payload['title'] ?? ''),
            assignedMemberId: $this->optionalPositiveInt($payload['assignedMemberId'] ?? null),
            status: $status,
            day: $this->optionalValidatedDay($payload['day'] ?? null),
            time: $this->optionalString($payload['time'] ?? null),
            category: $this->optionalString($payload['category'] ?? null),
            recurrence: $this->optionalString($payload['recurrence'] ?? null),
            priority: $this->optionalValidatedPriority($payload['priority'] ?? null)
        )));
    }

    public function delete(int $taskId): void
    {
        (new DeleteTaskHandler($this->taskRepository))->handle($taskId);
    }

    public function complete(int $taskId, string $userId): array
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
            'assignedMemberId' => $task->assignedMemberId(),
            'day' => $task->day(),
            'time' => $task->time(),
            'category' => $task->category(),
            'recurrence' => $task->recurrence(),
            'priority' => $task->priority(),
        ];
    }

    private function optionalPositiveInt(mixed $value): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_int($value) && $value > 0) {
            return $value;
        }

        if (is_string($value) && preg_match('/^[1-9][0-9]*$/', $value) === 1) {
            return (int) $value;
        }

        throw new \InvalidArgumentException('Entity ids must be positive integers.');
    }

    private function optionalString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return is_string($value) ? $value : (string) $value;
    }

    private function optionalValidatedDay(mixed $value): ?string
    {
        $normalized = $this->optionalString($value);
        if ($normalized === null) {
            return null;
        }

        $validDays = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
        if (!in_array($normalized, $validDays, true)) {
            throw new \InvalidArgumentException(sprintf('Invalid day "%s". Allowed values: %s.', $normalized, implode(', ', $validDays)));
        }

        return $normalized;
    }

    private function optionalValidatedPriority(mixed $value): ?string
    {
        $normalized = $this->optionalString($value);
        if ($normalized === null) {
            return null;
        }

        $validPriorities = ['low', 'med', 'high'];
        if (!in_array($normalized, $validPriorities, true)) {
            throw new \InvalidArgumentException(sprintf('Invalid priority "%s". Allowed values: %s.', $normalized, implode(', ', $validPriorities)));
        }

        return $normalized;
    }
}
