<?php

declare(strict_types=1);

namespace App\Infrastructure\Http;

use App\Application\Shared\TransactionRunner;
use App\Application\Tasks\AutoAssignTasks\AutoAssignTasksCommand;
use App\Application\Tasks\AutoAssignTasks\AutoAssignTasksHandler;
use App\Application\Tasks\CompleteTask\CompleteTaskCommand;
use App\Application\Tasks\CompleteTask\CompleteTaskHandler;
use App\Application\Tasks\CreateTask\CreateTaskCommand;
use App\Application\Tasks\CreateTask\CreateTaskHandler;
use App\Application\Tasks\ListTasks\ListTasksHandler;
use App\Application\Tasks\ListTasks\ListTasksQuery;
use App\Application\Tasks\ReopenTask\ReopenTaskCommand;
use App\Application\Tasks\ReopenTask\ReopenTaskHandler;
use App\Application\Tasks\UpdateTask\UpdateTaskCommand;
use App\Application\Tasks\UpdateTask\UpdateTaskHandler;
use App\Application\Tasks\DeleteTask\DeleteTaskHandler;
use App\Application\Tasks\HouseholdCatalogs\HouseholdCatalogs;
use App\Domain\Alerts\AlertRepositoryInterface;
use App\Domain\Members\MemberRepositoryInterface;
use App\Domain\Tasks\Catalog\CategoryRepositoryInterface;
use App\Domain\Tasks\Catalog\RecurrenceTypeRepositoryInterface;
use App\Domain\Tasks\Task;
use App\Domain\Tasks\TaskRepositoryInterface;

final class TaskController
{
    public function __construct(
        private TaskRepositoryInterface $taskRepository,
        private ?AlertRepositoryInterface $alertRepository = null,
        private ?MemberRepositoryInterface $memberRepository = null,
        private ?TransactionRunner $transactionRunner = null,
        private ?CategoryRepositoryInterface $categoryRepository = null,
        private ?RecurrenceTypeRepositoryInterface $recurrenceTypeRepository = null
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function create(array $payload): array
    {
        $handler = new CreateTaskHandler(
            $this->taskRepository,
            $this->alertRepository,
            $this->memberRepository,
            $this->categoryRepository,
            $this->recurrenceTypeRepository
        );
        $task = $handler->handle(new CreateTaskCommand(
            title: (string) ($payload['title'] ?? ''),
            householdId: (string) ($payload['householdId'] ?? ''),
            userId: (string) ($payload['userId'] ?? ''),
            assignedMemberId: $this->optionalPositiveInt($payload['assignedMemberId'] ?? null),
            day: $this->optionalValidatedDay($payload['day'] ?? null),
            time: $this->optionalString($payload['time'] ?? null),
            categoryId: $this->optionalPositiveInt($payload['categoryId'] ?? null),
            recurrenceId: $this->optionalPositiveInt($payload['recurrenceId'] ?? null),
            priority: $this->optionalValidatedPriority($payload['priority'] ?? null),
            repeatable: $this->optionalBool($payload['repeatable'] ?? null) ?? false
        ));

        return $this->serializeTask($task);
    }

    public function update(int $taskId, array $payload): array
    {
        $status = isset($payload['status']) ? \App\Domain\Tasks\TaskStatus::tryFrom((string) $payload['status']) : null;
        $handler = new UpdateTaskHandler(
            $this->taskRepository,
            $this->memberRepository,
            $this->categoryRepository,
            $this->recurrenceTypeRepository
        );

        // An absent catalog reference keeps the stored one; an explicit null (or "") clears it.
        return $this->serializeTask($handler->handle(new UpdateTaskCommand(
            taskId: $taskId,
            title: (string) ($payload['title'] ?? ''),
            assignedMemberId: $this->optionalPositiveInt($payload['assignedMemberId'] ?? null),
            status: $status,
            day: $this->optionalValidatedDay($payload['day'] ?? null),
            time: $this->optionalString($payload['time'] ?? null),
            categoryId: $this->optionalPositiveInt($payload['categoryId'] ?? null),
            recurrenceId: $this->optionalPositiveInt($payload['recurrenceId'] ?? null),
            priority: $this->optionalValidatedPriority($payload['priority'] ?? null),
            repeatable: $this->optionalBool($payload['repeatable'] ?? null),
            clearCategory: $this->isExplicitlyEmpty($payload, 'categoryId'),
            clearRecurrence: $this->isExplicitlyEmpty($payload, 'recurrenceId')
        )));
    }

    public function delete(int $taskId): void
    {
        (new DeleteTaskHandler($this->taskRepository))->handle($taskId);
    }

    public function complete(int $taskId, string $userId, mixed $nextAssigneeId = null): array
    {
        $handler = new CompleteTaskHandler($this->taskRepository, $this->alertRepository, $this->memberRepository);
        $task = $handler->handle(new CompleteTaskCommand(
            taskId: $taskId,
            userId: $userId,
            nextAssigneeId: $this->optionalPositiveInt($nextAssigneeId)
        ));

        return $this->serializeTask($task);
    }

    public function reopen(int $taskId): array
    {
        $handler = new ReopenTaskHandler($this->taskRepository);
        $task = $handler->handle(new ReopenTaskCommand(taskId: $taskId));

        return $this->serializeTask($task);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function autoAssign(string $householdId): array
    {
        if ($this->memberRepository === null) {
            throw new \LogicException('Auto-assign requires a member repository.');
        }

        $handler = new AutoAssignTasksHandler(
            $this->taskRepository,
            $this->memberRepository,
            $this->alertRepository,
            transactionRunner: $this->transactionRunner,
            recurrenceTypeRepository: $this->recurrenceTypeRepository
        );
        $tasks = $handler->handle(new AutoAssignTasksCommand($householdId));

        return $this->serializeTasks($tasks, $householdId);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function list(string $householdId): array
    {
        $handler = new ListTasksHandler($this->taskRepository);
        $tasks = $handler->handle(new ListTasksQuery($householdId));

        return $this->serializeTasks($tasks, $householdId);
    }

    /**
     * Resolves catalog names with one read per catalog for the whole list.
     *
     * @param array<int, Task> $tasks
     * @return array<int, array<string, mixed>>
     */
    private function serializeTasks(array $tasks, string $householdId): array
    {
        $catalogs = $this->catalogsOf($householdId);

        return array_map(fn (Task $task): array => $this->serializeTask($task, $catalogs), $tasks);
    }

    private function catalogsOf(string $householdId): HouseholdCatalogs
    {
        return HouseholdCatalogs::load($this->categoryRepository, $this->recurrenceTypeRepository, $householdId);
    }

    /**
     * `category` and `recurrence` are read-only names resolved from the catalogs.
     *
     * @return array<string, mixed>
     */
    private function serializeTask(Task $task, ?HouseholdCatalogs $catalogs = null): array
    {
        $catalogs ??= $this->catalogsOf($task->householdId());

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
            'categoryId' => $task->categoryId(),
            'category' => $catalogs->categoryName($task->categoryId()),
            'recurrenceId' => $task->recurrenceId(),
            'recurrence' => $catalogs->recurrenceName($task->recurrenceId()),
            'priority' => $task->priority(),
            'lastAssignedAt' => $task->lastAssignedAt()?->format(DATE_ATOM),
            'repeatable' => $task->isRepeatable(),
        ];
    }

    /**
     * Parses an optional JSON boolean. Accepts 0/1 too, matching the stored column.
     */
    private function optionalBool(mixed $value): ?bool
    {
        if ($value === null) {
            return null;
        }

        if (is_bool($value)) {
            return $value;
        }

        if ($value === 0 || $value === 1) {
            return $value === 1;
        }

        throw new \InvalidArgumentException('repeatable must be a boolean.');
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function isExplicitlyEmpty(array $payload, string $key): bool
    {
        return array_key_exists($key, $payload) && ($payload[$key] === null || $payload[$key] === '');
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
