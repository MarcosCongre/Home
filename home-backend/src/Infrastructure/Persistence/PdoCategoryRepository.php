<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Tasks\Catalog\Category;
use App\Domain\Tasks\Catalog\CategoryRepositoryInterface;
use PDO;

final class PdoCategoryRepository implements CategoryRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo
    ) {
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $this->pdo->exec(
                'CREATE TABLE IF NOT EXISTS categories (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    household_id TEXT NOT NULL,
                    name TEXT NOT NULL,
                    color TEXT NULL,
                    created_at TEXT NOT NULL,
                    updated_at TEXT NOT NULL,
                    deleted_at TEXT NULL
                )'
            );
            $this->pdo->exec('CREATE INDEX IF NOT EXISTS idx_categories_household_id ON categories (household_id)');
        }
    }

    public function save(Category $category): void
    {
        $exists = false;
        if ($category->id() > 0) {
            $check = $this->pdo->prepare('SELECT 1 FROM categories WHERE id = :id');
            $check->bindValue(':id', $category->id(), PDO::PARAM_INT);
            $check->execute();
            $exists = $check->fetchColumn() !== false;
        }

        if ($exists) {
            $statement = $this->pdo->prepare(
                'UPDATE categories
                 SET name = :name, color = :color, updated_at = :updatedAt, deleted_at = :deletedAt
                 WHERE id = :id'
            );
            $statement->bindValue(':id', $category->id(), PDO::PARAM_INT);
        } else {
            $statement = $this->pdo->prepare(
                'INSERT INTO categories (household_id, name, color, created_at, updated_at, deleted_at)
                 VALUES (:householdId, :name, :color, :createdAt, :updatedAt, :deletedAt)'
            );
            $statement->bindValue(':householdId', $category->householdId());
            $statement->bindValue(':createdAt', DbDateTime::format($category->createdAt()));
        }

        $color = $category->color();
        $deletedAt = $category->deletedAt();
        $statement->bindValue(':name', $category->name());
        $statement->bindValue(':color', $color, $color === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $statement->bindValue(':updatedAt', DbDateTime::format($category->updatedAt()));
        $statement->bindValue(':deletedAt', $deletedAt === null ? null : DbDateTime::format($deletedAt), $deletedAt === null ? PDO::PARAM_NULL : PDO::PARAM_STR);
        $statement->execute();

        if (!$exists) {
            $category->assignGeneratedId((int) $this->pdo->lastInsertId());
        }
    }

    public function findById(int $id): ?Category
    {
        $statement = $this->pdo->prepare('SELECT * FROM categories WHERE id = :id AND deleted_at IS NULL');
        $statement->bindValue(':id', $id, PDO::PARAM_INT);
        $statement->execute();
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function findByHouseholdId(string $householdId): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM categories WHERE household_id = :householdId AND deleted_at IS NULL ORDER BY id ASC');
        $statement->execute([':householdId' => $householdId]);
        $categories = [];

        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            $categories[] = $this->hydrate($row);
        }

        return $categories;
    }

    public function countAllByHouseholdId(string $householdId): int
    {
        $statement = $this->pdo->prepare('SELECT COUNT(*) FROM categories WHERE household_id = :householdId');
        $statement->execute([':householdId' => $householdId]);

        return (int) $statement->fetchColumn();
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): Category
    {
        return new Category(
            id: (int) $row['id'],
            householdId: (string) $row['household_id'],
            name: (string) $row['name'],
            color: isset($row['color']) ? (string) $row['color'] : null,
            createdAt: new \DateTimeImmutable((string) $row['created_at']),
            updatedAt: new \DateTimeImmutable((string) $row['updated_at']),
            deletedAt: isset($row['deleted_at']) && $row['deleted_at'] !== '' ? new \DateTimeImmutable((string) $row['deleted_at']) : null
        );
    }
}
