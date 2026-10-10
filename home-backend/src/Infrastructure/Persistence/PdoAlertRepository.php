<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Alerts\Alert;
use App\Domain\Alerts\AlertRepositoryInterface;
use App\Domain\Alerts\AlertStatus;
use PDO;

final class PdoAlertRepository implements AlertRepositoryInterface
{
    public function __construct(
        private readonly PDO $pdo
    ) {
        if ($this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            $this->pdo->exec(
                'CREATE TABLE IF NOT EXISTS alerts (
                    id INTEGER PRIMARY KEY AUTOINCREMENT,
                    household_id TEXT NOT NULL,
                    member_id INTEGER NULL,
                    title TEXT NOT NULL,
                    body TEXT NOT NULL,
                    icon TEXT NOT NULL,
                    urgent INTEGER NOT NULL DEFAULT 0,
                    status TEXT NOT NULL,
                    created_at TEXT NOT NULL
                )'
            );
        }
    }

    public function findByHousehold(string $householdId): array
    {
        $statement = $this->pdo->prepare('SELECT * FROM alerts WHERE household_id = :householdId ORDER BY created_at DESC');
        $statement->execute([':householdId' => $householdId]);

        $alerts = [];
        while (($row = $statement->fetch(PDO::FETCH_ASSOC)) !== false) {
            $alerts[] = $this->hydrate($row);
        }

        return $alerts;
    }

    public function save(Alert $alert): Alert
    {
        $exists = $this->pdo->prepare('SELECT 1 FROM alerts WHERE id = :id');
        $exists->bindValue(':id', $alert->id(), PDO::PARAM_INT);
        $exists->execute();

        if ($alert->id() > 0 && $exists->fetchColumn() !== false) {
            $statement = $this->pdo->prepare(
                'UPDATE alerts
                 SET household_id = :householdId,
                     member_id = :memberId,
                     title = :title,
                     body = :body,
                     icon = :icon,
                     urgent = :urgent,
                     status = :status,
                     created_at = :createdAt
                 WHERE id = :id'
            );
            $statement->bindValue(':id', $alert->id(), PDO::PARAM_INT);
        } else {
            $statement = $this->pdo->prepare(
                'INSERT INTO alerts (household_id, member_id, title, body, icon, urgent, status, created_at)
                 VALUES (:householdId, :memberId, :title, :body, :icon, :urgent, :status, :createdAt)'
            );
        }

        $statement->bindValue(':householdId', $alert->householdId());
        $statement->bindValue(':memberId', $alert->memberId(), $alert->memberId() === null ? PDO::PARAM_NULL : PDO::PARAM_INT);
        $statement->bindValue(':title', $alert->title());
        $statement->bindValue(':body', $alert->body());
        $statement->bindValue(':icon', $alert->icon());
        $statement->bindValue(':urgent', $alert->urgent() ? 1 : 0, PDO::PARAM_INT);
        $statement->bindValue(':status', $alert->status()->value);
        $statement->bindValue(':createdAt', DbDateTime::format($alert->createdAt()));
        $statement->execute();

        if ($alert->id() === 0) {
            $alert->assignGeneratedId((int) $this->pdo->lastInsertId());
        }

        return $alert;
    }

    public function dismiss(int $id, string $householdId): bool
    {
        // Check existence explicitly: MySQL rowCount() reports changed rows, not matched rows.
        $exists = $this->pdo->prepare('SELECT 1 FROM alerts WHERE id = :id AND household_id = :householdId');
        $exists->bindValue(':id', $id, PDO::PARAM_INT);
        $exists->bindValue(':householdId', $householdId);
        $exists->execute();
        if ($exists->fetchColumn() === false) {
            return false;
        }

        $statement = $this->pdo->prepare('UPDATE alerts SET status = :status WHERE id = :id AND household_id = :householdId');
        $statement->bindValue(':status', AlertStatus::DISMISSED->value);
        $statement->bindValue(':id', $id, PDO::PARAM_INT);
        $statement->bindValue(':householdId', $householdId);
        $statement->execute();

        return true;
    }

    public function dismissAll(string $householdId): void
    {
        $statement = $this->pdo->prepare('UPDATE alerts SET status = :status WHERE household_id = :householdId');
        $statement->bindValue(':status', AlertStatus::DISMISSED->value);
        $statement->bindValue(':householdId', $householdId);
        $statement->execute();
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Alert
    {
        $memberId = $row['member_id'] ?? null;

        return new Alert(
            id: (int) $row['id'],
            householdId: (string) $row['household_id'],
            memberId: $memberId === null || $memberId === '' ? null : (int) $memberId,
            title: (string) $row['title'],
            body: (string) $row['body'],
            icon: (string) $row['icon'],
            urgent: (bool) (int) ($row['urgent'] ?? 0),
            status: AlertStatus::tryFrom((string) ($row['status'] ?? AlertStatus::UNREAD->value)) ?? AlertStatus::UNREAD,
            createdAt: new \DateTimeImmutable((string) $row['created_at'])
        );
    }
}
