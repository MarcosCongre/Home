<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Tasks\Task;
use App\Domain\Tasks\TaskRepositoryInterface;
use App\Domain\Tasks\TaskStatus;
use PDO;

final class PdoTaskRepository implements TaskRepositoryInterface
{
    public function __construct(
        private PDO $pdo
    ) {
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $this->pdo->exec(
                'CREATE TABLE IF NOT EXISTS tasks (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    title TEXT NOT NULL,
                    status TEXT NOT NULL,
                    household_id TEXT NOT NULL,
                    assigned_member_id INTEGER NULL,
                    created_at TEXT NOT NULL,
                    completed_at TEXT NULL,
                    day TEXT NULL,
                    time TEXT NULL,
                    category_id INTEGER NULL,
                    recurrence_id INTEGER NULL,
                    priority TEXT NOT NULL DEFAULT "med",
                    last_assigned_at TEXT NULL,
                    deleted_at TEXT NULL,
                    repeatable INTEGER NOT NULL DEFAULT 0
                )'
            );

            // A table created before the catalogs keeps its legacy category/recurrence strings
            // (no longer read or written) and only gains the reference columns.
            $columns = array_column($this->pdo->query('PRAGMA table_info(tasks)')->fetchAll(PDO::FETCH_ASSOC), 'name');
            foreach (['category_id', 'recurrence_id'] as $column) {
                if (!in_array($column, $columns, true)) {
                    $this->pdo->exec(sprintf('ALTER TABLE tasks ADD COLUMN %s INTEGER NULL', $column));
                }
            }
        }
    }

    public function save(Task $task): void
    {
        $exists = $this->pdo->prepare('SELECT 1 FROM tasks WHERE id = :id');
        $exists->bindValue(':id', $task->id(), PDO::PARAM_INT);
        $exists->execute();

        $priority = $task->priority() === '' ? 'med' : $task->priority();
        $day = $task->day();
        $time = $task->time();
        $categoryId = $task->categoryId();
        $recurrenceId = $task->recurrenceId();

        if ($task->id() > 0 && $exists->fetchColumn() !== false) {
            $statement = $this->pdo->prepare(
                'UPDATE tasks
                 SET title = :title,
                     status = :status,
                     household_id = :householdId,
                     assigned_member_id = :assignedMemberId,
                     created_at = :createdAt,
                     completed_at = :completedAt,
                     day = :day,
                     time = :time,
                     category_id = :categoryId,
                     recurrence_id = :recurrenceId,
                     priority = :priority,
                     last_assigned_at = :lastAssignedAt,
                     deleted_at = :deletedAt,
                     repeatable = :repeatable
                 WHERE id = :id'
            );
            $statement->bindValue(':id', $task->id(), PDO::PARAM_INT);
        } else {
            if ($task->id() > 0) {
                $statement = $this->pdo->prepare(
                    'INSERT INTO tasks (id, title, status, household_id, assigned_member_id, created_at, completed_at, day, time, category_id, recurrence_id, priority, last_assigned_at, deleted_at, repeatable)
                     VALUES (:id, :title, :status, :householdId, :assignedMemberId, :createdAt, :completedAt, :day, :time, :categoryId, :recurrenceId, :priority, :lastAssignedAt, :deletedAt, :repeatable)'
                );
                $statement->bindValue(':id', $task->id(), PDO::PARAM_INT);
            } else {
                $statement = $this->pdo->prepare(
                    'INSERT INTO tasks (title, status, household_id, assigned_member_id, created_at, completed_at, day, time, category_id, recurrence_id, priority, last_assigned_at, deleted_at, repeatable)
                     VALUES (:title, :status, :householdId, :assignedMemberId, :createdAt, :completedAt, :day, :time, :categoryId, :recurrenceId, :priority, :lastAssignedAt, :deletedAt, :repeatable)'
                );
            }
        }

        $statement->bindValue(':title', $task->title()->value());
        $statement->bindValue(':status', $task->status()->value);
        $statement->bindValue(':householdId', $task->householdId());
        $statement->bindValue(':assignedMemberId', $task->assignedMemberId(), $task->assignedMemberId() === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $completedAt = $task->completedAt();
        $statement->bindValue(':createdAt', DbDateTime::format($task->createdAt()));
        $statement->bindValue(':completedAt', $completedAt === null ? null : DbDateTime::format($completedAt), $completedAt === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $statement->bindValue(':day', $day, $day === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $statement->bindValue(':time', $time, $time === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $statement->bindValue(':categoryId', $categoryId, $categoryId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $statement->bindValue(':recurrenceId', $recurrenceId, $recurrenceId === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $statement->bindValue(':priority', $priority, PDO::PARAM_STR);
        $lastAssignedAt = $task->lastAssignedAt();
        $statement->bindValue(':lastAssignedAt', $lastAssignedAt === null ? null : DbDateTime::format($lastAssignedAt), $lastAssignedAt === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $deletedAt = $task->deletedAt();
        $statement->bindValue(':deletedAt', $deletedAt === null ? null : DbDateTime::format($deletedAt), $deletedAt === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $statement->bindValue(':repeatable', $task->isRepeatable() ? 1 : 0, PDO::PARAM_INT);
        $statement->execute();

        if ($task->id() === 0) {
            $task->assignGeneratedId((int) $this->pdo->lastInsertId());
        }
    }

    public function findById(int $id): ?Task
    {
        $statement = $this->pdo->prepare('SELECT * FROM tasks WHERE id = :id AND deleted_at IS NULL');
        $statement->bindValue(':id', $id, PDO::PARAM_INT);
        $statement->execute();

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return $this->hydrateTask($row);
    }

    /**
     * @return array<int, Task>
     */
    public function findByHouseholdId(string $householdId): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM tasks WHERE household_id = :householdId AND deleted_at IS NULL ORDER BY created_at ASC');
        $statement->execute([':householdId' => $householdId]);

        $tasks = [];
        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            $tasks[] = $this->hydrateTask($row);
        }

        return $tasks;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrateTask(array $row): Task
    {
        $completedAt = $row['completed_at'];
        $day = $this->normalizeOptionalString($row['day'] ?? null);
        $time = $this->normalizeOptionalString($row['time'] ?? null);
        $priority = $this->normalizePriority($row['priority'] ?? null);
        $lastAssignedAt = $this->normalizeOptionalString($row['last_assigned_at'] ?? null);
        $deletedAt = $this->normalizeOptionalString($row['deleted_at'] ?? null);

        return new Task(
            id: (int) $row['id'],
            title: new \App\Domain\Tasks\TaskTitle((string) $row['title']),
            status: TaskStatus::from((string) $row['status']),
            householdId: (string) $row['household_id'],
            createdAt: new \DateTimeImmutable((string) $row['created_at']),
            completedAt: $completedAt !== null && $completedAt !== '' ? new \DateTimeImmutable((string) $completedAt) : null,
            assignedMemberId: isset($row['assigned_member_id']) && $row['assigned_member_id'] !== null ? (int) $row['assigned_member_id'] : null,
            day: $day,
            time: $time,
            categoryId: $this->optionalInt($row['category_id'] ?? null),
            recurrenceId: $this->optionalInt($row['recurrence_id'] ?? null),
            priority: $priority,
            lastAssignedAt: $lastAssignedAt === null ? null : new \DateTimeImmutable($lastAssignedAt),
            deletedAt: $deletedAt === null ? null : new \DateTimeImmutable($deletedAt),
            repeatable: (bool) ($row['repeatable'] ?? false)
        );
    }

    private function normalizeOptionalString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    private function optionalInt(mixed $value): ?int
    {
        return $value === null || $value === '' ? null : (int) $value;
    }

    private function normalizePriority(mixed $value): string
    {
        $priority = $this->normalizeOptionalString($value);

        return $priority === null || $priority === '' ? 'med' : $priority;
    }
}
