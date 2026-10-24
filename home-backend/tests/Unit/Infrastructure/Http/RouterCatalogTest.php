<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Http;

use App\Domain\Tasks\Catalog\CatalogEntryNotFoundException;
use App\Infrastructure\Http\RouteNotFoundException;
use App\Infrastructure\Http\Router;
use App\Infrastructure\Persistence\InMemoryCategoryRepository;
use App\Infrastructure\Persistence\InMemoryRecurrenceTypeRepository;
use App\Infrastructure\Persistence\InMemoryTaskRepository;
use PHPUnit\Framework\TestCase;

final class RouterCatalogTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router(
            new InMemoryTaskRepository(),
            categoryRepository: new InMemoryCategoryRepository(),
            recurrenceTypeRepository: new InMemoryRecurrenceTypeRepository()
        );
    }

    public function testItRoutesCategoryListCreateUpdateAndDelete(): void
    {
        $listed = $this->get('/categories', 'house-9');
        $this->assertCount(8, $listed);
        $this->assertSame(['id' => $listed[0]['id'], 'householdId' => 'house-9', 'name' => 'Cleaning', 'color' => '#C4623A'], $listed[0]);

        $created = $this->send('POST', '/categories', ['householdId' => 'house-9', 'name' => ' Errands ']);
        $this->assertSame('Errands', $created['name']);
        $this->assertNull($created['color']);

        $updated = $this->send('PATCH', '/categories/' . $created['id'], ['householdId' => 'house-9', 'name' => 'Chores', 'color' => '#3A8FB5']);
        $this->assertSame(['id' => $created['id'], 'householdId' => 'house-9', 'name' => 'Chores', 'color' => '#3A8FB5'], $updated);

        $deleted = $this->router->dispatch([
            'REQUEST_METHOD' => 'DELETE',
            'PATH_INFO' => '/categories/' . $created['id'],
            'QUERY_STRING' => 'householdId=house-9',
        ]);
        $this->assertSame(['deleted' => true, 'id' => $created['id']], $deleted);
        $this->assertCount(8, $this->get('/categories', 'house-9'));
    }

    public function testItRoutesRecurrenceTypeListCreateUpdateAndDelete(): void
    {
        $listed = $this->get('/recurrence-types', 'house-9');
        $this->assertCount(6, $listed);
        $this->assertSame(['id' => $listed[0]['id'], 'householdId' => 'house-9', 'name' => 'Daily', 'intervalDays' => 1], $listed[0]);

        $created = $this->send('POST', '/recurrence-types', ['householdId' => 'house-9', 'name' => 'Every 10 days', 'intervalDays' => '10']);
        $this->assertSame(10, $created['intervalDays']);

        $updated = $this->send('PATCH', '/recurrence-types/' . $created['id'], ['householdId' => 'house-9', 'name' => 'Every 12 days', 'intervalDays' => 12]);
        $this->assertSame('Every 12 days', $updated['name']);
        $this->assertSame(12, $updated['intervalDays']);

        // DELETE also accepts the household in the JSON body.
        $deleted = $this->send('DELETE', '/recurrence-types/' . $created['id'], ['householdId' => 'house-9']);
        $this->assertSame(['deleted' => true, 'id' => $created['id']], $deleted);
        $this->assertCount(6, $this->get('/recurrence-types', 'house-9'));
    }

    public function testCatalogsAreIsolatedPerHousehold(): void
    {
        $created = $this->send('POST', '/categories', ['householdId' => 'house-1', 'name' => 'Errands']);

        $this->assertNotContains('Errands', array_column($this->get('/categories', 'house-2'), 'name'));

        $this->expectException(CatalogEntryNotFoundException::class);
        $this->send('PATCH', '/categories/' . $created['id'], ['householdId' => 'house-2', 'name' => 'Mine']);
    }

    public function testDeletingAnotherHouseholdsRecurrenceIsNotFound(): void
    {
        $created = $this->send('POST', '/recurrence-types', ['householdId' => 'house-1', 'name' => 'Every 10 days', 'intervalDays' => 10]);

        $this->expectException(CatalogEntryNotFoundException::class);
        $this->send('DELETE', '/recurrence-types/' . $created['id'], ['householdId' => 'house-2']);
    }

    public function testADuplicateCategoryNameIsAValidationError(): void
    {
        $this->get('/categories', 'house-1');

        $this->expectException(\InvalidArgumentException::class);
        $this->send('POST', '/categories', ['householdId' => 'house-1', 'name' => 'cleaning']);
    }

    /**
     * @return iterable<string, array{0: mixed}>
     */
    public static function invalidIntervals(): iterable
    {
        yield 'missing' => [null];
        yield 'fraction' => [1.5];
        yield 'text' => ['weekly'];
        yield 'decimal string' => ['7.0'];
        yield 'out of range' => [400];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('invalidIntervals')]
    public function testANonIntegerOrOutOfRangeIntervalIsAValidationError(mixed $intervalDays): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->send('POST', '/recurrence-types', ['householdId' => 'house-1', 'name' => 'Odd', 'intervalDays' => $intervalDays]);
    }

    public function testUpdateAndDeleteRequireAHouseholdId(): void
    {
        $created = $this->send('POST', '/categories', ['householdId' => 'house-1', 'name' => 'Errands']);

        try {
            $this->send('PATCH', '/categories/' . $created['id'], ['name' => 'Chores']);
            $this->fail('Expected a validation error for the missing householdId.');
        } catch (\InvalidArgumentException $exception) {
            $this->assertSame('householdId is required.', $exception->getMessage());
        }

        $this->expectException(\InvalidArgumentException::class);
        $this->send('DELETE', '/categories/' . $created['id'], []);
    }

    public function testListRequiresAHouseholdId(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->get('/recurrence-types', '');
    }

    public function testCatalogRoutesAreUnavailableWithoutRepositories(): void
    {
        $router = new Router(new InMemoryTaskRepository());

        $this->expectException(RouteNotFoundException::class);
        $router->dispatch(['REQUEST_METHOD' => 'GET', 'PATH_INFO' => '/categories', 'QUERY_STRING' => 'householdId=house-1']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function get(string $path, string $householdId): array
    {
        return $this->router->dispatch([
            'REQUEST_METHOD' => 'GET',
            'PATH_INFO' => $path,
            'QUERY_STRING' => http_build_query(['householdId' => $householdId]),
        ]);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function send(string $method, string $path, array $payload): array
    {
        return $this->router->dispatch([
            'REQUEST_METHOD' => $method,
            'PATH_INFO' => $path,
            'php://input' => json_encode($payload),
        ]);
    }
}
