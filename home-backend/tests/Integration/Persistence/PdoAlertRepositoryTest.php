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

        $this->assertTrue($repository->dismiss($saved->id(), 'house-42'));
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

    public function testItOnlyDismissesAlertsOfTheGivenHousehold(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $repository = new PdoAlertRepository($pdo);
        $saved = $repository->save(Alert::create(
            id: 0,
            householdId: 'house-42',
            memberId: null,
            title: 'Recordatorio',
            body: 'Revisa la cocina.',
            icon: 'bell',
            urgent: false,
            createdAt: new \DateTimeImmutable('2026-10-04 08:00:00')
        ));

        $this->assertFalse($repository->dismiss($saved->id(), 'house-99'));
        $this->assertSame(AlertStatus::UNREAD, $repository->findByHousehold('house-42')[0]->status());
        $this->assertFalse($repository->dismiss($saved->id() + 1, 'house-42'));

        $this->assertTrue($repository->dismiss($saved->id(), 'house-42'));
        $this->assertSame(AlertStatus::DISMISSED, $repository->findByHousehold('house-42')[0]->status());
        $this->assertTrue($repository->dismiss($saved->id(), 'house-42'));
    }

    public function testItPersistsCreatedAtAsAppLocalTimestamp(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $repository = new PdoAlertRepository($pdo);
        $createdAt = new \DateTimeImmutable('2026-10-04T11:30:00+00:00');

        $saved = $repository->save(Alert::create(
            id: 0,
            householdId: 'house-43',
            memberId: null,
            title: 'Nueva tarea asignada',
            body: 'Se te asignó una tarea.',
            icon: 'check-circle',
            urgent: false,
            createdAt: $createdAt
        ));

        $raw = $pdo->query('SELECT created_at FROM alerts WHERE id = ' . $saved->id())->fetchColumn();
        $this->assertSame('2026-10-04 08:30:00', $raw);
        $this->assertSame(
            $createdAt->getTimestamp(),
            $repository->findByHousehold('house-43')[0]->createdAt()->getTimestamp()
        );
    }
}
