<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Http;

use App\Application\Alerts\CreateAlert\CreateAlertCommand;
use App\Application\Alerts\CreateAlert\CreateAlertHandler;
use App\Domain\Alerts\Alert;
use App\Domain\Alerts\AlertRepositoryInterface;
use App\Domain\Members\Member;
use App\Infrastructure\Http\Router;
use App\Infrastructure\Persistence\InMemoryMemberRepository;
use App\Infrastructure\Persistence\InMemoryTaskRepository;
use PHPUnit\Framework\TestCase;

final class AlertRouterTest extends TestCase
{
    public function testItDispatchesAlertRoutes(): void
    {
        $alertRepository = new InMemoryAlertRepository();
        $memberRepository = new InMemoryMemberRepository();
        $memberRepository->save(new Member(1, 'Ana', 'a', '#ff0000', 'house-42'));
        $alertRepository->save(Alert::create(
            id: 0,
            householdId: 'house-42',
            memberId: 1,
            title: 'Nueva tarea asignada',
            body: 'Se te asignó la tarea "Pasear al perro".',
            icon: 'check-circle',
            urgent: false,
            createdAt: new \DateTimeImmutable('2026-10-04 08:30:00')
        ));

        $router = new Router(new InMemoryTaskRepository(), $memberRepository, $alertRepository);

        $list = $router->dispatch([
            'REQUEST_METHOD' => 'GET',
            'PATH_INFO' => '/alerts',
            'QUERY_STRING' => 'householdId=house-42'
        ]);
        $this->assertCount(1, $list);
        $this->assertSame('08:30 AM', $list[0]['time']);
        $this->assertSame('Ana', $list[0]['user']);

        $dismissed = $router->dispatch([
            'REQUEST_METHOD' => 'PATCH',
            'PATH_INFO' => '/alerts/1/dismiss',
            'php://input' => '{"householdId":"house-42"}'
        ]);
        $this->assertSame(['dismissed' => true, 'id' => 1], $dismissed);

        $dismissAll = $router->dispatch([
            'REQUEST_METHOD' => 'POST',
            'PATH_INFO' => '/alerts/dismiss-all',
            'php://input' => '{"householdId":"house-42"}'
        ]);
        $this->assertSame(['dismissedAll' => true, 'householdId' => 'house-42'], $dismissAll);
    }
}

final class InMemoryAlertRepository implements AlertRepositoryInterface
{
    /** @var array<int, Alert> */
    private array $alerts = [];

    public function findByHousehold(string $householdId): array
    {
        $items = array_values(array_filter($this->alerts, static fn (Alert $alert): bool => $alert->householdId() === $householdId));
        usort($items, static fn (Alert $a, Alert $b): int => $b->createdAt() <=> $a->createdAt());

        return $items;
    }

    public function save(Alert $alert): Alert
    {
        if ($alert->id() === 0) {
            $alert->assignGeneratedId(1);
        }

        $this->alerts[$alert->id()] = $alert;

        return $alert;
    }

    public function dismiss(int $id, string $householdId): bool
    {
        if (!isset($this->alerts[$id]) || $this->alerts[$id]->householdId() !== $householdId) {
            return false;
        }

        $this->alerts[$id]->dismiss();

        return true;
    }

    public function dismissAll(string $householdId): void
    {
        foreach ($this->alerts as $alert) {
            if ($alert->householdId() === $householdId) {
                $alert->dismiss();
            }
        }
    }
}
