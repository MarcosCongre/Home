<?php

declare(strict_types=1);

namespace Tests\Integration\Http;

use App\Domain\Alerts\Alert;
use App\Domain\Alerts\AlertNotFoundException;
use App\Domain\Alerts\AlertStatus;
use App\Domain\Members\Member;
use App\Infrastructure\Bootstrap\App;
use App\Infrastructure\Persistence\InMemoryMemberRepository;
use PHPUnit\Framework\TestCase;

final class AlertControllerTest extends TestCase
{
    public function testItListsAlertsAndSerializesUserAndTime(): void
    {
        $taskRepository = new \App\Infrastructure\Persistence\InMemoryTaskRepository();
        $memberRepository = new InMemoryMemberRepository();
        $memberRepository->save(new Member(1, 'Ana', 'a', '#ff0000', 'house-42'));
        $alertRepository = new AlertControllerTestRepository();
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

        $app = new App($taskRepository, $memberRepository, $alertRepository);

        $response = $app->handle([
            'REQUEST_METHOD' => 'GET',
            'PATH_INFO' => '/alerts',
            'QUERY_STRING' => 'householdId=house-42',
        ]);

        $this->assertCount(1, $response);
        $this->assertSame('Nueva tarea asignada', $response[0]['title']);
        $this->assertSame('08:30 AM', $response[0]['time']);
        $this->assertSame('Ana', $response[0]['user']);
    }

    public function testItRendersAlertTimeInTheAppTimezone(): void
    {
        $taskRepository = new \App\Infrastructure\Persistence\InMemoryTaskRepository();
        $memberRepository = new InMemoryMemberRepository();
        $alertRepository = new AlertControllerTestRepository();
        $alertRepository->save(Alert::create(
            id: 0,
            householdId: 'house-42',
            memberId: null,
            title: 'Tarea completada',
            body: 'Tarea completada: "Ordenar".',
            icon: 'check-circle',
            urgent: false,
            createdAt: new \DateTimeImmutable('2026-10-04T11:30:00+00:00')
        ));

        $app = new App($taskRepository, $memberRepository, $alertRepository);

        $response = $app->handle([
            'REQUEST_METHOD' => 'GET',
            'PATH_INFO' => '/alerts',
            'QUERY_STRING' => 'householdId=house-42',
        ]);

        $this->assertSame('America/Argentina/Buenos_Aires', date_default_timezone_get());
        $this->assertSame('08:30 AM', $response[0]['time']);
    }

    public function testItDismissesAnAlertAndDismissesAllForHousehold(): void
    {
        $taskRepository = new \App\Infrastructure\Persistence\InMemoryTaskRepository();
        $memberRepository = new InMemoryMemberRepository();
        $alertRepository = new AlertControllerTestRepository();
        $alertRepository->save(Alert::create(
            id: 0,
            householdId: 'house-7',
            memberId: null,
            title: 'Recordatorio',
            body: 'Revisa la cocina.',
            icon: 'bell',
            urgent: false,
            createdAt: new \DateTimeImmutable('2026-10-04 09:15:00')
        ));

        $app = new App($taskRepository, $memberRepository, $alertRepository);

        $dismissed = $app->handle([
            'REQUEST_METHOD' => 'PATCH',
            'PATH_INFO' => '/alerts/1/dismiss',
            'php://input' => '{"householdId":"house-7"}',
        ]);
        $this->assertSame(['dismissed' => true, 'id' => 1], $dismissed);

        $allDismissed = $app->handle([
            'REQUEST_METHOD' => 'POST',
            'PATH_INFO' => '/alerts/dismiss-all',
            'php://input' => '{"householdId":"house-7"}',
        ]);
        $this->assertSame(['dismissedAll' => true, 'householdId' => 'house-7'], $allDismissed);
    }
    public function testDismissingAnAlertOfAnotherHouseholdIsNotFoundAndKeepsItUnread(): void
    {
        $alertRepository = $this->repositoryWithAlertIn('house-7');
        $app = new App(new \App\Infrastructure\Persistence\InMemoryTaskRepository(), new InMemoryMemberRepository(), $alertRepository);

        try {
            $app->handle([
                'REQUEST_METHOD' => 'PATCH',
                'PATH_INFO' => '/alerts/1/dismiss',
                'php://input' => '{"householdId":"house-99"}',
            ]);
            $this->fail('Expected AlertNotFoundException.');
        } catch (AlertNotFoundException) {
        }

        $this->assertSame(AlertStatus::UNREAD, $alertRepository->findByHousehold('house-7')[0]->status());
    }

    public function testDismissingWithTheOwningHouseholdIsIdempotent(): void
    {
        $alertRepository = $this->repositoryWithAlertIn('house-7');
        $app = new App(new \App\Infrastructure\Persistence\InMemoryTaskRepository(), new InMemoryMemberRepository(), $alertRepository);
        $request = [
            'REQUEST_METHOD' => 'PATCH',
            'PATH_INFO' => '/alerts/1/dismiss',
            'php://input' => '{"householdId":"house-7"}',
        ];

        $this->assertSame(['dismissed' => true, 'id' => 1], $app->handle($request));
        $this->assertSame(AlertStatus::DISMISSED, $alertRepository->findByHousehold('house-7')[0]->status());
        $this->assertSame(['dismissed' => true, 'id' => 1], $app->handle($request));
    }

    public function testDismissingWithoutHouseholdIdIsRejected(): void
    {
        $alertRepository = $this->repositoryWithAlertIn('house-7');
        $app = new App(new \App\Infrastructure\Persistence\InMemoryTaskRepository(), new InMemoryMemberRepository(), $alertRepository);

        $this->expectException(\InvalidArgumentException::class);

        $app->handle([
            'REQUEST_METHOD' => 'PATCH',
            'PATH_INFO' => '/alerts/1/dismiss',
            'php://input' => '{}',
        ]);
    }

    public function testItDoesNotExposeMembersOfAnotherHousehold(): void
    {
        $memberRepository = new InMemoryMemberRepository();
        $memberRepository->save(new Member(1, 'Intruder', 'i', '#00ff00', 'house-99'));
        $alertRepository = new AlertControllerTestRepository();
        $alertRepository->save(Alert::create(
            id: 0,
            householdId: 'house-42',
            memberId: 1,
            title: 'Nueva tarea asignada',
            body: 'Body',
            icon: 'check-circle',
            urgent: false,
            createdAt: new \DateTimeImmutable('2026-10-04 08:30:00')
        ));
        $app = new App(new \App\Infrastructure\Persistence\InMemoryTaskRepository(), $memberRepository, $alertRepository);

        $response = $app->handle([
            'REQUEST_METHOD' => 'GET',
            'PATH_INFO' => '/alerts',
            'QUERY_STRING' => 'householdId=house-42',
        ]);

        $this->assertCount(1, $response);
        $this->assertNull($response[0]['user']);
    }

    private function repositoryWithAlertIn(string $householdId): AlertControllerTestRepository
    {
        $alertRepository = new AlertControllerTestRepository();
        $alertRepository->save(Alert::create(
            id: 0,
            householdId: $householdId,
            memberId: null,
            title: 'Recordatorio',
            body: 'Revisa la cocina.',
            icon: 'bell',
            urgent: false,
            createdAt: new \DateTimeImmutable('2026-10-04 09:15:00')
        ));

        return $alertRepository;
    }
}

final class AlertControllerTestRepository implements \App\Domain\Alerts\AlertRepositoryInterface
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
