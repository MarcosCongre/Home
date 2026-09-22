<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Http;

use App\Infrastructure\Http\Router;
use App\Infrastructure\Persistence\InMemoryMemberRepository;
use App\Infrastructure\Persistence\InMemoryTaskRepository;
use PHPUnit\Framework\TestCase;

final class RouterMemberTest extends TestCase
{
    public function testItRoutesMemberCreateListAndUpdate(): void
    {
        $taskRepository = new InMemoryTaskRepository();
        $memberRepository = new InMemoryMemberRepository();
        $router = new Router($taskRepository, $memberRepository);

        $created = $router->dispatch([
            'REQUEST_METHOD' => 'POST',
            'PATH_INFO' => '/members',
            'php://input' => json_encode([
                'name' => 'Archie',
                'avatar' => 'A',
                'color' => '#C4963A',
                'householdId' => 'house-9',
            ]),
        ]);

        $this->assertSame('Archie', $created['name']);
        $this->assertSame('house-9', $created['householdId']);

        $updated = $router->dispatch([
            'REQUEST_METHOD' => 'PATCH',
            'PATH_INFO' => '/members/' . $created['id'],
            'php://input' => json_encode([
                'name' => 'Archi',
                'avatar' => 'A',
                'color' => '#C4963A',
            ]),
        ]);

        $this->assertSame('Archi', $updated['name']);

        $listed = $router->dispatch([
            'REQUEST_METHOD' => 'GET',
            'PATH_INFO' => '/members',
            'QUERY_STRING' => 'householdId=house-9',
        ]);

        $this->assertCount(1, $listed);
        $this->assertSame('Archi', $listed[0]['name']);
    }
}
