<?php

declare(strict_types=1);

namespace Tests\Integration\Http;

use App\Infrastructure\Bootstrap\App;
use App\Infrastructure\Persistence\InMemoryTaskRepository;
use PHPUnit\Framework\TestCase;

final class TaskControllerTest extends TestCase
{
    public function testItHandlesCreateCompleteAndListWorkflow(): void
    {
        $repository = new InMemoryTaskRepository();
        $app = new App($repository);

        $created = $app->handle([
            'REQUEST_METHOD' => 'POST',
            'PATH_INFO' => '/tasks',
            'CONTENT_TYPE' => 'application/json',
            'php://input' => '{"title":"Wash dishes","householdId":"house-42","userId":"user-77"}'
        ]);

        $this->assertSame('Wash dishes', $created['title']);
        $this->assertSame('pending', $created['status']);

        $taskId = $created['id'];

        $completed = $app->handle([
            'REQUEST_METHOD' => 'PATCH',
            'PATH_INFO' => '/tasks/' . $taskId . '/complete',
            'CONTENT_TYPE' => 'application/json',
            'php://input' => '{"userId":"user-77"}'
        ]);

        $this->assertSame('completed', $completed['status']);

        $listed = $app->handle([
            'REQUEST_METHOD' => 'GET',
            'PATH_INFO' => '/tasks',
            'QUERY_STRING' => 'householdId=house-42'
        ]);

        $this->assertCount(1, $listed);
        $this->assertSame('completed', $listed[0]['status']);
    }

    public function testItHandlesRequestUriRoutesWithoutPathInfo(): void
    {
        $repository = new InMemoryTaskRepository();
        $app = new App($repository);

        $response = $app->handle([
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/tasks?householdId=house-9',
            'QUERY_STRING' => 'householdId=house-9',
        ]);

        $this->assertSame([], $response);
    }
}
