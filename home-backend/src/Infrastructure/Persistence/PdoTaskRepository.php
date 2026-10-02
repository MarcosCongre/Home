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
                    category TEXT NULL,
                    recurrence TEXT NULL,
                    priority TEXT NOT NULL DEFAULT "med"
                )'
            );
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
        $category = $task->category();
        $recurrence = $task->recurrence();

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
                     category = :category,
                     recurrence = :recurrence,
                     priority = :priority
                 WHERE id = :id'
            );
            $statement->bindValue(':id', $task->id(), PDO::PARAM_INT);
        } else {
            if ($task->id() > 0) {
                $statement = $this->pdo->prepare(
                    'INSERT INTO tasks (id, title, status, household_id, assigned_member_id, created_at, completed_at, day, time, category, recurrence, priority)
                     VALUES (:id, :title, :status, :householdId, :assignedMemberId, :createdAt, :completedAt, :day, :time, :category, :recurrence, :priority)'
                );
                $statement->bindValue(':id', $task->id(), PDO::PARAM_INT);
            } else {
                $statement = $this->pdo->prepare(
                    'INSERT INTO tasks (title, status, household_id, assigned_member_id, created_at, completed_at, day, time, category, recurrence, priority)
                     VALUES (:title, :status, :householdId, :assignedMemberId, :createdAt, :completedAt, :day, :time, :category, :recurrence, :priority)'
                );
            }
        }

        $statement->bindValue(':title', $task->title()->value());
        $statement->bindValue(':status', $task->status()->value);
        $statement->bindValue(':householdId', $task->householdId());
        $statement->bindValue(':assignedMemberId', $task->assignedMemberId(), $task->assignedMemberId() === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $statement->bindValue(':createdAt', $task->createdAt()->format(DATE_ATOM));
        $statement->bindValue(':completedAt', $task->completedAt()?->format(DATE_ATOM), $task->completedAt() === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $statement->bindValue(':day', $day, $day === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $statement->bindValue(':time', $time, $time === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $statement->bindValue(':category', $category, $category === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $statement->bindValue(':recurrence', $recurrence, $recurrence === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $statement->bindValue(':priority', $priority, PDO::PARAM_STR);
        $statement->execute();

        if ($task->id() === 0) {
            $task->assignGeneratedId((int) $this->pdo->lastInsertId());
        }
    }

    public function findById(int $id): ?Task
    {
        $statement = $this->pdo->prepare('SELECT * FROM tasks WHERE id = :id');
        $statement->bindValue(':id', $id, PDO::PARAM_INT);
        $statement->execute();

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return $this->hydrateTask($row);
    }

    public function delete(int $id): void
    {
        $statement = $this->pdo->prepare('DELETE FROM tasks WHERE id = :id');
        $statement->bindValue(':id', $id, PDO::PARAM_INT);
        $statement->execute();
    }

    /**
     * @return array<int, Task>
     */
    public function findByHouseholdId(string $householdId): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM tasks WHERE household_id = :householdId ORDER BY created_at ASC');
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
        $category = $this->normalizeOptionalString($row['category'] ?? null);
        $recurrence = $this->normalizeOptionalString($row['recurrence'] ?? null);
        $priority = $this->normalizePriority($row['priority'] ?? null);

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
            category: $category,
            recurrence: $recurrence,
            priority: $priority
        );
    }

    private function normalizeOptionalString(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        return (string) $value;
    }

    private function normalizePriority(mixed $value): string
    {
        $priority = $this->normalizeOptionalString($value);

        return $priority === null || $priority === '' ? 'med' : $priority;
    }
}
