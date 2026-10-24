<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Http;

use App\Domain\Members\Member;
use App\Infrastructure\Http\RouteNotFoundException;
use App\Infrastructure\Http\Router;
use App\Infrastructure\Persistence\InMemoryMemberRepository;
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

    public function testItDispatchesDeleteTaskRoute(): void
    {
        $tasks = new InMemoryTaskRepository();
        $router = new Router($tasks);

        $created = $router->dispatch([
            'REQUEST_METHOD' => 'POST',
            'PATH_INFO' => '/tasks',
            'php://input' => '{"title":"Fold laundry","householdId":"house-1"}'
        ]);

        $response = $router->dispatch([
            'REQUEST_METHOD' => 'DELETE',
            'PATH_INFO' => '/tasks/' . $created['id'],
        ]);

        $this->assertSame(['deleted' => true, 'id' => $created['id']], $response);
        $this->assertNull($tasks->findById($created['id']));
    }

    public function testItForwardsTheNextAssigneeWhenCompletingATask(): void
    {
        $members = new InMemoryMemberRepository();
        $members->save(Member::create(0, 'Ana', 'A', '#C4963A', 'house-1'));
        $members->save(Member::create(0, 'Bruno', 'B', '#6B7C4E', 'house-1'));
        $router = new Router(new InMemoryTaskRepository(), $members);

        $created = $router->dispatch([
            'REQUEST_METHOD' => 'POST',
            'PATH_INFO' => '/tasks',
            'php://input' => '{"title":"Feed the cat","householdId":"house-1","assignedMemberId":1,"repeatable":true}'
        ]);

        $response = $router->dispatch([
            'REQUEST_METHOD' => 'PATCH',
            'PATH_INFO' => '/tasks/' . $created['id'] . '/complete',
            'php://input' => '{"userId":"1","nextAssigneeId":2}'
        ]);

        $this->assertSame('pending', $response['status']);
        $this->assertSame(2, $response['assignedMemberId']);
    }

    public function testItRejectsUnknownRoutes(): void
    {
        $router = new Router(new InMemoryTaskRepository());

        $this->expectException(RouteNotFoundException::class);

        $router->dispatch([
            'REQUEST_METHOD' => 'GET',
            'PATH_INFO' => '/unknown',
        ]);
    }

    public function testItDispatchesAutoAssignRouteWithoutCollidingWithTaskIdRoutes(): void
    {
        $tasks = new InMemoryTaskRepository();
        $members = new InMemoryMemberRepository();
        $members->save(Member::create(0, 'Ana', 'A', '#C4963A', 'house-1'));
        $router = new Router($tasks, $members);

        $router->dispatch([
            'REQUEST_METHOD' => 'POST',
            'PATH_INFO' => '/tasks',
            'php://input' => '{"title":"Fold laundry","householdId":"house-1"}'
        ]);

        $response = $router->dispatch([
            'REQUEST_METHOD' => 'POST',
            'PATH_INFO' => '/tasks/auto-assign',
            'php://input' => '{"householdId":"house-1"}'
        ]);

        $this->assertCount(1, $response);
        $this->assertSame('Fold laundry', $response[0]['title']);
        $this->assertSame(1, $response[0]['assignedMemberId']);
        $this->assertNotNull($response[0]['lastAssignedAt']);
    }

    public function testAutoAssignRequiresAHouseholdId(): void
    {
        $router = new Router(new InMemoryTaskRepository(), new InMemoryMemberRepository());

        $this->expectException(\InvalidArgumentException::class);

        $router->dispatch([
            'REQUEST_METHOD' => 'POST',
            'PATH_INFO' => '/tasks/auto-assign',
            'php://input' => '{}'
        ]);
    }
}
