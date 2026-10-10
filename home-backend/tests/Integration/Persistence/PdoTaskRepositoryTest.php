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
}
