<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Http;

use App\Infrastructure\Http\Router;
use App\Infrastructure\Persistence\InMemoryTaskRepository;
use PHPUnit\Framework\TestCase;

final class RouterTest extends TestCase
{
    public function testItDispatchesCreateTaskRoute(): void
    {
        $router = new Router(new InMemoryTaskRepository());

        $response = $router->dispatch([
            'REQUEST_METHOD' => 'POST',
            'PATH_INFO' => '/tasks',
            'php://input' => '{"title":"Fold laundry","householdId":"house-1","userId":"user-2"}'
        ]);

        $this->assertSame('Fold laundry', $response['title']);
        $this->assertSame('pending', $response['status']);
        $this->assertSame('house-1', $response['householdId']);
    }
}
