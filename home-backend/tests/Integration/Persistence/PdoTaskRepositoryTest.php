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
            id: 'task-100',
            title: 'Sort pantry',
            householdId: 'house-9',
            createdAt: new \DateTimeImmutable('2026-09-16 11:00:00')
        );

        $repository->save($task);

        $stored = $repository->findById('task-100');

        $this->assertNotNull($stored);
        $this->assertSame('Sort pantry', $stored->title()->value());
        $this->assertSame('house-9', $stored->householdId());
        $this->assertSame('task-100', $stored->id());

        $tasks = $repository->findByHouseholdId('house-9');
        $this->assertCount(1, $tasks);
        $this->assertSame('Sort pantry', $tasks[0]->title()->value());
    }
}
