<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Tasks;

use App\Application\Tasks\CreateTask\CreateTaskCommand;
use App\Application\Tasks\CreateTask\CreateTaskHandler;
use App\Domain\Tasks\TaskStatus;
use App\Infrastructure\Persistence\InMemoryTaskRepository;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(CreateTaskHandler::class)]
final class CreateTaskHandlerTest extends TestCase
{
    public function testItCreatesATaskAndPersistsIt(): void
    {
        $repository = new InMemoryTaskRepository();
        $handler = new CreateTaskHandler($repository);

        $task = $handler->handle(new CreateTaskCommand(
            title: 'Take out trash',
            householdId: 'house-42',
            userId: 'user-77',
            day: 'monday',
            time: '20:00',
            category: 'home',
            recurrence: 'weekly',
            priority: 'high'
        ));

        $this->assertSame('Take out trash', $task->title()->value());
        $this->assertSame(TaskStatus::PENDING, $task->status());
        $this->assertSame('house-42', $task->householdId());
        $this->assertSame('monday', $task->day());
        $this->assertSame('20:00', $task->time());
        $this->assertSame('home', $task->category());
        $this->assertSame('weekly', $task->recurrence());
        $this->assertSame('high', $task->priority());
        $this->assertNotNull($repository->findById($task->id()));
    }
}
