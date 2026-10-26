<?php

declare(strict_types=1);

namespace Tests\Integration\Persistence;

use App\Domain\Tasks\Task;
use App\Infrastructure\Persistence\PdoTaskRepository;
use PHPUnit\Framework\TestCase;

final class PdoTaskRepositoryTest extends TestCase
{
    public function testItPersistsAndRetrievesTasksInSqlite(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $repository = new PdoTaskRepository($pdo);

        $task = Task::create(
            id: 100,
            title: 'Sort pantry',
            householdId: 'house-9',
            createdAt: new \DateTimeImmutable('2026-09-16 11:00:00')
        );

        $repository->save($task);

        $stored = $repository->findById(100);

        $this->assertNotNull($stored);
        $this->assertSame('Sort pantry', $stored->title()->value());
        $this->assertSame('house-9', $stored->householdId());
        $this->assertSame(100, $stored->id());

        $tasks = $repository->findByHouseholdId('house-9');
        $this->assertCount(1, $tasks);
        $this->assertSame('Sort pantry', $tasks[0]->title()->value());
    }

    public function testItPersistsDeletedAtAndHidesSoftDeletedTasks(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $repository = new PdoTaskRepository($pdo);
        $kept = Task::create(id: 110, title: 'Keep me', householdId: 'house-11', createdAt: new \DateTimeImmutable('2026-10-01 08:00:00'));
        $deleted = Task::create(id: 111, title: 'Delete me', householdId: 'house-11', createdAt: new \DateTimeImmutable('2026-10-01 09:00:00'));
        $repository->save($kept);
        $repository->save($deleted);

        $deleted->delete(new \DateTimeImmutable('2026-10-24 10:30:00'));
        $repository->save($deleted);

        $row = $pdo->query('SELECT deleted_at FROM tasks WHERE id = 111')->fetch(\PDO::FETCH_ASSOC);
        $this->assertSame('2026-10-24 10:30:00', $row['deleted_at']);
        $this->assertNull($repository->findById(111));
        $this->assertNull($pdo->query('SELECT deleted_at FROM tasks WHERE id = 110')->fetchColumn());
        $this->assertSame([110], array_map(static fn (Task $task): int => $task->id(), $repository->findByHouseholdId('house-11')));
    }

    public function testItPersistsTaskMetadataAndDefaultPriority(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $repository = new PdoTaskRepository($pdo);

        $task = new Task(
            id: 101,
            title: new \App\Domain\Tasks\TaskTitle('Clean kitchen'),
            householdId: 'house-10',
            createdAt: new \DateTimeImmutable('2026-09-16 12:00:00'),
            day: 'monday',
            time: '08:30',
            category: 'household',
            recurrence: 'weekly',
            priority: 'high'
        );

        $repository->save($task);

        $stored = $repository->findById(101);
        $this->assertNotNull($stored);
        $this->assertSame('monday', $stored->day());
        $this->assertSame('08:30', $stored->time());
        $this->assertSame('household', $stored->category());
        $this->assertSame('weekly', $stored->recurrence());
        $this->assertSame('high', $stored->priority());

        $tasks = $repository->findByHouseholdId('house-10');
        $this->assertCount(1, $tasks);
        $this->assertSame('monday', $tasks[0]->day());
        $this->assertSame('high', $tasks[0]->priority());

        $defaultPriorityTask = new Task(
            id: 102,
            title: new \App\Domain\Tasks\TaskTitle('Vacuum living room'),
            householdId: 'house-10',
            createdAt: new \DateTimeImmutable('2026-09-17 09:00:00')
        );

        $repository->save($defaultPriorityTask);

        $persistedDefault = $repository->findById(102);
        $this->assertNotNull($persistedDefault);
        $this->assertSame('med', $persistedDefault->priority());
        $this->assertNull($persistedDefault->day());
        $this->assertNull($persistedDefault->time());
        $this->assertNull($persistedDefault->category());
        $this->assertNull($persistedDefault->recurrence());
    }

    public function testItPersistsTimestampsAsAppLocalValues(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $repository = new PdoTaskRepository($pdo);
        $createdAt = new \DateTimeImmutable('2026-10-04T11:30:00+00:00');
        $completedAt = new \DateTimeImmutable('2026-10-04T13:45:00+00:00');

        $repository->save(new Task(
            id: 200,
            title: new \App\Domain\Tasks\TaskTitle('Water plants'),
            householdId: 'house-11',
            createdAt: $createdAt,
            completedAt: $completedAt
        ));

        $row = $pdo->query('SELECT created_at, completed_at FROM tasks WHERE id = 200')->fetch(\PDO::FETCH_ASSOC);
        $this->assertSame('2026-10-04 08:30:00', $row['created_at']);
        $this->assertSame('2026-10-04 10:45:00', $row['completed_at']);

        $stored = $repository->findById(200);
        $this->assertNotNull($stored);
        $this->assertSame($createdAt->getTimestamp(), $stored->createdAt()->getTimestamp());
        $this->assertSame($completedAt->getTimestamp(), $stored->completedAt()?->getTimestamp());
    }

    public function testItPersistsTheLastAssignmentDate(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $repository = new PdoTaskRepository($pdo);
        $assignedAt = new \DateTimeImmutable('2026-10-19T15:00:00+00:00');

        $repository->save(new Task(
            id: 201,
            title: new \App\Domain\Tasks\TaskTitle('Take out trash'),
            householdId: 'house-12',
            createdAt: new \DateTimeImmutable('2026-10-01 08:00:00'),
            assignedMemberId: 3,
            recurrence: 'Weekly',
            lastAssignedAt: $assignedAt
        ));
        $repository->save(new Task(
            id: 202,
            title: new \App\Domain\Tasks\TaskTitle('Unassigned'),
            householdId: 'house-12',
            createdAt: new \DateTimeImmutable('2026-10-02 08:00:00')
        ));

        $row = $pdo->query('SELECT last_assigned_at FROM tasks WHERE id = 201')->fetch(\PDO::FETCH_ASSOC);
        $this->assertSame('2026-10-19 12:00:00', $row['last_assigned_at']);

        $stored = $repository->findById(201);
        $this->assertNotNull($stored);
        $this->assertSame($assignedAt->getTimestamp(), $stored->lastAssignedAt()?->getTimestamp());
        $this->assertNull($repository->findById(202)?->lastAssignedAt());

        $stored->reassignTo(4, new \DateTimeImmutable('2026-10-26T15:00:00+00:00'));
        $repository->save($stored);

        $updated = $repository->findByHouseholdId('house-12')[0];
        $this->assertSame(4, $updated->assignedMemberId());
        $this->assertSame('2026-10-26 12:00:00', $updated->lastAssignedAt()?->format('Y-m-d H:i:s'));
    }

    public function testItReadsTimestampsBackInTheTimezoneUsedToWriteThem(): void
    {
        $previous = date_default_timezone_get();
        date_default_timezone_set('Asia/Tokyo');

        try {
            $pdo = new \PDO('sqlite::memory:');
            $repository = new PdoTaskRepository($pdo);
            $createdAt = new \DateTimeImmutable('2026-10-01T08:00:00+00:00');
            $completedAt = new \DateTimeImmutable('2026-10-02T09:00:00+00:00');
            $assignedAt = new \DateTimeImmutable('2026-10-19T15:00:00+00:00');

            $repository->save(new Task(
                id: 300,
                title: new \App\Domain\Tasks\TaskTitle('Mop floors'),
                householdId: 'house-13',
                createdAt: $createdAt,
                completedAt: $completedAt,
                assignedMemberId: 3,
                recurrence: 'Weekly',
                lastAssignedAt: $assignedAt
            ));

            $row = $pdo->query('SELECT last_assigned_at FROM tasks WHERE id = 300')->fetch(\PDO::FETCH_ASSOC);
            $this->assertSame('2026-10-20 00:00:00', $row['last_assigned_at']);

            $stored = $repository->findById(300);
            $this->assertNotNull($stored);
            $this->assertSame($createdAt->getTimestamp(), $stored->createdAt()->getTimestamp());
            $this->assertSame($completedAt->getTimestamp(), $stored->completedAt()?->getTimestamp());
            $this->assertSame($assignedAt->getTimestamp(), $stored->lastAssignedAt()?->getTimestamp());
            $this->assertSame('Asia/Tokyo', $stored->lastAssignedAt()?->getTimezone()->getName());
        } finally {
            date_default_timezone_set($previous);
        }
    }
}
