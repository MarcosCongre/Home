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
}
