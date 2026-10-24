<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Tasks\Catalog\RecurrenceType;
use App\Domain\Tasks\Catalog\RecurrenceTypeRepositoryInterface;
use PDO;

final class PdoRecurrenceTypeRepository implements RecurrenceTypeRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo
    ) {
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $this->pdo->exec(
                'CREATE TABLE IF NOT EXISTS recurrence_types (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    household_id TEXT NOT NULL,
                    name TEXT NOT NULL,
                    interval_days INTEGER NOT NULL,
                    created_at TEXT NOT NULL,
                    updated_at TEXT NOT NULL,
                    deleted_at TEXT NULL
                )'
            );
            $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_recurrence_types_household_id ON recurrence_types (household_id)');
        }
    }

    public function save(RecurrenceType $recurrenceType): void
    {
        $exists = false;
        if ($recurrenceType->id() > 0) {
            $check = $this->pdo->prepare('SELECT 1 FROM recurrence_types WHERE id = :id');
            $check->bindValue(':id', $recurrenceType->id(), PDO::PARAM_INT);
            $check->execute();
            $exists = $check->fetchColumn() !== false;
        }

        if ($exists) {
            $statement = $this->pdo->prepare(
                'UPDATE recurrence_types
                 SET name = :name, interval_days = :intervalDays, updated_at = :updatedAt, deleted_at = :deletedAt
                 WHERE id = :id'
            );
            $statement->bindValue(':id', $recurrenceType->id(), PDO::PARAM_INT);
        } else {
            $statement = $this->pdo->prepare(
                'INSERT INTO recurrence_types (household_id, name, interval_days, created_at, updated_at, deleted_at)
                 VALUES (:householdId, :name, :intervalDays, :createdAt, :updatedAt, :deletedAt)'
            );
            $statement->bindValue(':householdId', $recurrenceType->householdId());
            $statement->bindValue(':createdAt', DbDateTime::format($recurrenceType->createdAt()));
        }

        $deletedAt = $recurrenceType->deletedAt();
        $statement->bindValue(':name', $recurrenceType->name());
        $statement->bindValue(':intervalDays', $recurrenceType->intervalDays(), PDO::PARAM_INT);
        $statement->bindValue(':updatedAt', DbDateTime::format($recurrenceType->updatedAt()));
        $statement->bindValue(':deletedAt', $deletedAt === null ? null : DbDateTime::format($deletedAt), $deletedAt === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $statement->execute();

        if (!$exists) {
            $recurrenceType->assignGeneratedId((int) $this->pdo->lastInsertId());
        }
    }

    public function findById(int $id): ?RecurrenceType
    {
        $statement = $this->pdo->prepare('SELECT * FROM recurrence_types WHERE id = :id AND deleted_at IS NULL');
        $statement->bindValue(':id', $id, PDO::PARAM_INT);
        $statement->execute();
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function findByHouseholdId(string $householdId): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM recurrence_types WHERE household_id = :householdId AND deleted_at IS NULL ORDER BY id ASC');
        $statement->execute([':householdId' => $householdId]);
        $recurrenceTypes = [];

        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            $recurrenceTypes[] = $this->hydrate($row);
        }

        return $recurrenceTypes;
    }

    public function countAllByHouseholdId(string $householdId): int
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM recurrence_types WHERE household_id = :householdId');
        $statement->execute([':householdId' => $householdId]);

        return (int) $statement->fetchColumn();
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): RecurrenceType
    {
        return new RecurrenceType(
            id: (int) $row['id'],
            householdId: (string) $row['household_id'],
            name: (string) $row['name'],
            intervalDays: (int) $row['interval_days'],
            createdAt: new \DateTimeImmutable((string) $row['created_at']),
            updatedAt: new \DateTimeImmutable((string) $row['updated_at']),
            deletedAt: isset($row['deleted_at']) && $row['deleted_at'] !== '' ? new \DateTimeImmutable((string) $row['deleted_at']) : null
        );
    }
}
