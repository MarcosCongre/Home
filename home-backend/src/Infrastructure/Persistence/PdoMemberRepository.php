<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Members\Member;
use App\Domain\Members\MemberRepositoryInterface;
use PDO;

final class PdoMemberRepository implements MemberRepositoryInterface
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function save(Member $member): void
    {
        if ($member->id() > 0) {
            $statement = $this->pdo->prepare(
                'INSERT INTO members (id, household_id, name, avatar, color, created_at, updated_at)
                 VALUES (:id, :householdId, :name, :avatar, :color, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)
                 ON DUPLICATE KEY UPDATE name = VALUES(name), avatar = VALUES(avatar), color = VALUES(color), updated_at = CURRENT_TIMESTAMP'
            );
            $statement->bindValue(':id', $member->id(), PDO::PARAM_INT);
        } else {
            $statement = $this->pdo->prepare(
                'INSERT INTO members (household_id, name, avatar, color, created_at, updated_at)
                 VALUES (:householdId, :name, :avatar, :color, CURRENT_TIMESTAMP, CURRENT_TIMESTAMP)'
            );
        }

        $statement->bindValue(':householdId', $member->householdId());
        $statement->bindValue(':name', $member->name());
        $statement->bindValue(':avatar', $member->avatar());
        $statement->bindValue(':color', $member->color());
        $statement->execute();

        if ($member->id() === 0) {
            $member->assignGeneratedId((int) $this->pdo->lastInsertId());
        }
    }

    public function findById(int $id): ?Member
    {
        $statement = $this->pdo->prepare('SELECT * FROM members WHERE id = :id');
        $statement->bindValue(':id', $id, PDO::PARAM_INT);
        $statement->execute();
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $this->hydrate($row);
    }

    public function findByHouseholdId(string $householdId): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM members WHERE household_id = :householdId ORDER BY name ASC');
        $statement->execute([':householdId' => $householdId]);
        $members = [];

        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            $members[] = $this->hydrate($row);
        }

        return $members;
    }

    public function delete(int $id): void
    {
        $statement = $this->pdo->prepare('DELETE FROM members WHERE id = :id');
        $statement->bindValue(':id', $id, PDO::PARAM_INT);
        $statement->execute();
    }

    /** @param array<string, mixed> $row */
    private function hydrate(array $row): Member
    {
        return new Member(
            id: (int) $row['id'],
            name: (string) $row['name'],
            avatar: (string) $row['avatar'],
            color: (string) $row['color'],
            householdId: (string) $row['household_id']
        );
    }
}