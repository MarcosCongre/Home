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

    public function dismiss(int $id): void;

    public function dismissAll(string $householdId): void;
}
