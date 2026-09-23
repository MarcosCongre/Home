<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Tasks;

use App\Application\Tasks\ListTasks\ListTasksHandler;
use App\Application\Tasks\ListTasks\ListTasksQuery;
use App\Domain\Tasks\Task;
use App\Infrastructure\Persistence\InMemoryTaskRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ListTasksHandler::class)]
final class ListTasksHandlerTest extends TestCase
{
    public function testItReturnsAllTasksForTheGivenHousehold(): void
    {
        $repository = new InMemoryTaskRepository();

        $first = Task::create(
            id: 1,
            title: 'Wash dishes',
            householdId: 'house-42',
            createdAt: new \DateTimeImmutable('2026-09-16 08:00:00')
        );
        $second = Task::create(
            id: 2,
            title: 'Take out trash',
            householdId: 'house-42',
            createdAt: new \DateTimeImmutable('2026-09-16 09:00:00')
        );
        $otherHousehold = Task::create(
            id: 3,
            title: 'Vacuum living room',
            householdId: 'house-99',
            createdAt: new \DateTimeImmutable('2026-09-16 10:00:00')
        );

        $repository->save($first);
        $repository->save($second);
        $repository->save($otherHousehold);

        $handler = new ListTasksHandler($repository);

        $tasks = $handler->handle(new ListTasksQuery(
            householdId: 'house-42'
        ));

        $this->assertCount(2, $tasks);
        $this->assertSame([1, 2], array_map(static fn (Task $task): int => $task->id(), $tasks));
    }
}
