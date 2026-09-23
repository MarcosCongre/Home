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
                    id TEXT PRIMARY KEY,
                    title TEXT NOT NULL,
                    status TEXT NOT NULL,
                    household_id TEXT NOT NULL,
                    assigned_member_id TEXT NULL,
                    created_at TEXT NOT NULL,
                    completed_at TEXT NULL
                )'
            );
        }
    }

    public function save(Task $task): void
    {
        $exists = $this->pdo->prepare('SELECT 1 FROM tasks WHERE id = :id');
        $exists->execute([':id' => $task->id()]);

        if ($exists->fetchColumn() !== false) {
            $statement = $this->pdo->prepare(
                'UPDATE tasks
                 SET title = :title,
                     status = :status,
                     household_id = :householdId,
                     assigned_member_id = :assignedMemberId,
                     created_at = :createdAt,
                     completed_at = :completedAt
                 WHERE id = :id'
            );
        } else {
            $statement = $this->pdo->prepare(
                'INSERT INTO tasks (id, title, status, household_id, assigned_member_id, created_at, completed_at)
                 VALUES (:id, :title, :status, :householdId, :assignedMemberId, :createdAt, :completedAt)'
            );
        }

        $statement->execute([
            ':id' => $task->id(),
            ':title' => $task->title()->value(),
            ':status' => $task->status()->value,
            ':householdId' => $task->householdId(),
            ':assignedMemberId' => $task->assignedMemberId(),
            ':createdAt' => $task->createdAt()->format(DATE_ATOM),
            ':completedAt' => $task->completedAt()?->format(DATE_ATOM),
        ]);
    }

    public function findById(string $id): ?Task
    {
        $statement = $this->pdo->prepare('SELECT * FROM tasks WHERE id = :id');
        $statement->execute([':id' => $id]);

        $row = $statement->fetch(PDO::FETCH_ASSOC);

        if ($row === false) {
            return null;
        }

        return $this->hydrateTask($row);
    }

    public function delete(string $id): void
    {
        $statement = $this->pdo->prepare('DELETE FROM tasks WHERE id = :id');
        $statement->execute([':id' => $id]);
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

        return new Task(
            id: (string) $row['id'],
            title: new \App\Domain\Tasks\TaskTitle((string) $row['title']),
            status: TaskStatus::from((string) $row['status']),
            householdId: (string) $row['household_id'],
            createdAt: new \DateTimeImmutable((string) $row['created_at']),
            completedAt: $completedAt !== null && $completedAt !== '' ? new \DateTimeImmutable((string) $completedAt) : null,
            assignedMemberId: isset($row['assigned_member_id']) ? ((string) $row['assigned_member_id'] ?: null) : null
        );
    }
}
