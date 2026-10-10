<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Alerts;

use App\Application\Alerts\CreateAlert\CreateAlertCommand;
use App\Application\Alerts\CreateAlert\CreateAlertHandler;
use App\Application\Alerts\ListAlerts\ListAlertsHandler;
use App\Application\Alerts\ListAlerts\ListAlertsQuery;
use App\Application\Tasks\CompleteTask\CompleteTaskCommand;
use App\Application\Tasks\CompleteTask\CompleteTaskHandler;
use App\Application\Tasks\CreateTask\CreateTaskCommand;
use App\Application\Tasks\CreateTask\CreateTaskHandler;
use App\Domain\Alerts\Alert;
use App\Domain\Alerts\AlertRepositoryInterface;
use App\Domain\Alerts\AlertStatus;
use App\Domain\Tasks\Task;
use App\Infrastructure\Persistence\InMemoryTaskRepository;
use PHPUnit\Framework\TestCase;

final class AlertApplicationTest extends TestCase
{
    public function testItCreatesAnAlert(): void
    {
        $repository = new InMemoryAlertRepository();
        $handler = new CreateAlertHandler($repository);

        $alert = $handler->handle(new CreateAlertCommand(
            householdId: 'house-42',
            memberId: 7,
            title: 'Nueva tarea asignada',
            body: 'Se te asignó la tarea "Pasear al perro".',
            icon: 'check-circle',
            urgent: false,
            createdAt: new \DateTimeImmutable('2026-09-16 09:00:00')
        ));

        $this->assertSame('Nueva tarea asignada', $alert->title());
        $this->assertSame('house-42', $alert->householdId());
        $this->assertSame(AlertStatus::UNREAD, $alert->status());
        $this->assertSame(7, $alert->memberId());
        $this->assertCount(1, $repository->findByHousehold('house-42'));
    }

    public function testItCreatesAnAssignmentAlertWhenATaskIsAssigned(): void
    {
        $taskRepository = new InMemoryTaskRepository();
        $alertRepository = new InMemoryAlertRepository();
        $handler = new CreateTaskHandler($taskRepository, $alertRepository);

        $task = $handler->handle(new CreateTaskCommand(
            title: 'Pasear al perro',
            householdId: 'house-42',
            userId: 'user-77',
            assignedMemberId: 7,
            day: 'monday',
            time: '18:00'
        ));

        $this->assertNotNull($task->assignedMemberId());
        $alerts = $alertRepository->findByHousehold('house-42');
        $this->assertCount(1, $alerts);
        $this->assertSame('Nueva tarea asignada', $alerts[0]->title());
        $this->assertStringContainsString('7', $alerts[0]->body());
        $this->assertStringContainsString('Pasear al perro', $alerts[0]->body());
    }

    public function testItCreatesACompletionAlertWhenATaskIsCompleted(): void
    {
        $taskRepository = new InMemoryTaskRepository();
        $task = Task::create(
            id: 100,
            title: 'Ordenar la cocina',
            householdId: 'house-42',
            createdAt: new \DateTimeImmutable('2026-09-16 08:00:00')
        );
        $task->update(
            title: 'Ordenar la cocina',
            assignedMemberId: 11,
            day: null,
            time: null,
            category: null,
            recurrence: null,
            priority: null
        );
        $taskRepository->save($task);

        $alertRepository = new InMemoryAlertRepository();
        $handler = new CompleteTaskHandler($taskRepository, $alertRepository);

        $handler->handle(new CompleteTaskCommand(
            taskId: 100,
            userId: 'user-77'
        ));

        $alerts = $alertRepository->findByHousehold('house-42');
        $this->assertCount(1, $alerts);
        $this->assertSame('Tarea completada', $alerts[0]->title());
        $this->assertStringContainsString('Ordenar la cocina', $alerts[0]->body());
        $this->assertStringContainsString('11', $alerts[0]->body());
    }

    public function testListingHidesDismissedAlerts(): void
    {
        $repository = new InMemoryAlertRepository();
        $createHandler = new CreateAlertHandler($repository);
        $createAlert = static fn (string $title) => $createHandler->handle(new CreateAlertCommand(
            householdId: 'house-42',
            memberId: null,
            title: $title,
            body: 'Body',
            icon: 'check-circle',
            urgent: false,
            createdAt: new \DateTimeImmutable('2026-09-16 09:00:00')
        ));

        $createAlert('Dismissed alert');
        $repository->dismissAll('house-42');
        $createAlert('Unread alert');

        $alerts = (new ListAlertsHandler($repository))->handle(new ListAlertsQuery('house-42'));

        $this->assertCount(1, $alerts);
        $this->assertSame('Unread alert', $alerts[0]->title());
    }

    public function testTaskIsCreatedEvenWhenTheAlertCannotBeSaved(): void
    {
        $taskRepository = new InMemoryTaskRepository();
        $handler = new CreateTaskHandler($taskRepository, new FailingAlertRepository());

        $task = $handler->handle(new CreateTaskCommand(
            title: 'Pasear al perro',
            householdId: 'house-42',
            userId: 'user-77',
            assignedMemberId: 7,
            day: 'monday',
            time: '18:00'
        ));

        $this->assertCount(1, $taskRepository->findByHouseholdId('house-42'));
        $this->assertSame('Pasear al perro', $task->title()->value());
    }

    public function testTaskIsCompletedEvenWhenTheAlertCannotBeSaved(): void
    {
        $taskRepository = new InMemoryTaskRepository();
        $taskRepository->save(Task::create(
            id: 100,
            title: 'Ordenar la cocina',
            householdId: 'house-42',
            createdAt: new \DateTimeImmutable('2026-09-16 08:00:00')
        ));
        $handler = new CompleteTaskHandler($taskRepository, new FailingAlertRepository());

        $task = $handler->handle(new CompleteTaskCommand(taskId: 100, userId: 'user-77'));

        $this->assertNotNull($task->completedAt());
        $this->assertNotNull($taskRepository->findById(100)?->completedAt());
    }
}

final class FailingAlertRepository implements AlertRepositoryInterface
{
    public function findByHousehold(string $householdId): array
    {
        return [];
    }

    public function save(Alert $alert): Alert
    {
        throw new \RuntimeException('Alert storage unavailable');
    }

    public function dismiss(int $id, string $householdId): bool
    {
        return false;
    }

    public function dismissAll(string $householdId): void
    {
    }
}

final class InMemoryAlertRepository implements AlertRepositoryInterface
{
    /** @var array<int, Alert> */
    private array $alerts = [];

    public function findByHousehold(string $householdId): array
    {
        $items = array_values(array_filter(
            $this->alerts,
            static fn (Alert $alert): bool => $alert->householdId() === $householdId
        ));

        usort(
            $items,
            static fn (Alert $a, Alert $b): int => $b->createdAt() <=> $a->createdAt()
        );

        return $items;
    }

    public function save(Alert $alert): Alert
    {
        $this->alerts[] = $alert;

        return $alert;
    }

    public function dismiss(int $id, string $householdId): bool
    {
        foreach ($this->alerts as $alert) {
            if ($alert->id() === $id && $alert->householdId() === $householdId) {
                $alert->dismiss();
                return true;
            }
        }

        return false;
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
