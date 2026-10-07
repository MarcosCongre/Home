<?php

declare(strict_types=1);

namespace Tests\Integration\Persistence;

use App\Domain\Alerts\Alert;
use App\Domain\Alerts\AlertStatus;
use App\Infrastructure\Persistence\PdoAlertRepository;
use PHPUnit\Framework\TestCase;

final class PdoAlertRepositoryTest extends TestCase
{
    public function testItPersistsAlertsAndSupportsDismissActions(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $pdo->exec('PRAGMA foreign_keys = ON');

        $repository = new PdoAlertRepository($pdo);

        $alert = Alert::create(
            id: 0,
            householdId: 'house-42',
            memberId: 7,
            title: 'Nueva tarea asignada',
            body: 'Se te asignó la tarea "Pasear al perro".',
            icon: 'check-circle',
            urgent: false,
            createdAt: new \DateTimeImmutable('2026-10-04 08:00:00')
        );

        $saved = $repository->save($alert);
        $this->assertNotSame(0, $saved->id());
        $this->assertCount(1, $repository->findByHousehold('house-42'));

        $repository->dismiss($saved->id());
        $this->assertSame(AlertStatus::DISMISSED, $repository->findByHousehold('house-42')[0]->status());

        $alert2 = Alert::create(
            id: 0,
            householdId: 'house-42',
            memberId: 9,
            title: 'Tarea completada',
            body: 'Tarea completada: "Ordenar".',
            icon: 'check-circle',
            urgent: false,
            createdAt: new \DateTimeImmutable('2026-10-04 09:00:00')
        );
        $repository->save($alert2);
        $repository->dismissAll('house-42');

        $this->assertCount(2, $repository->findByHousehold('house-42'));
        $this->assertSame(AlertStatus::DISMISSED, $repository->findByHousehold('house-42')[0]->status());
        $this->assertSame(AlertStatus::DISMISSED, $repository->findByHousehold('house-42')[1]->status());
    }
}
