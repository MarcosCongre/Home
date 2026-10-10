<?php

declare(strict_types=1);

namespace App\Domain\Alerts;

use App\Domain\Alerts\Alert;

interface AlertRepositoryInterface
{
    /**
     * @return array<int, Alert>
     */
    public function findByHousehold(string $householdId): array;

    public function save(Alert $alert): Alert;

    /**
     * Dismisses the alert only when it belongs to the household.
     * Returns false when no such alert exists in that household.
     */
    public function dismiss(int $id, string $householdId): bool;

    public function dismissAll(string $householdId): void;
}
