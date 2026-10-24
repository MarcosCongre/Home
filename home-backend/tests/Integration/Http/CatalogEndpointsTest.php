<?php

declare(strict_types=1);

namespace Tests\Integration\Http;

use App\Infrastructure\Bootstrap\App;
use App\Infrastructure\Persistence\InMemoryTaskRepository;
use App\Infrastructure\Persistence\PdoCategoryRepository;
use App\Infrastructure\Persistence\PdoRecurrenceTypeRepository;
use PHPUnit\Framework\TestCase;

// End to end through App with the SQLite-backed catalog repositories.
final class CatalogEndpointsTest extends TestCase
{
    private App $app;

    protected function setUp(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->app = new App(
            new InMemoryTaskRepository(),
            categoryRepository: new PdoCategoryRepository($pdo),
            recurrenceTypeRepository: new PdoRecurrenceTypeRepository($pdo)
        );
    }

    public function testDefaultsAreSeededOnceAndNotAfterDeletingEverything(): void
    {
        $categories = $this->list('/categories', 'house-1');
        $this->assertSame(['Cleaning', 'Laundry', 'Pets', 'Garden', 'Shopping', 'Waste', 'Bills', 'Kitchen'], array_column($categories, 'name'));

        foreach ($categories as $category) {
            $this->app->handle([
                'REQUEST_METHOD' => 'DELETE',
                'PATH_INFO' => '/categories/' . $category['id'],
                'QUERY_STRING' => 'householdId=house-1',
            ]);
        }

        $this->assertSame([], $this->list('/categories', 'house-1'));
        $this->assertCount(8, $this->list('/categories', 'house-2'));
    }

    public function testRecurrenceTypesRoundTripThroughSqlite(): void
    {
        $this->assertSame([1, 3, 3, 7, 14, 30], array_column($this->list('/recurrence-types', 'house-1'), 'intervalDays'));

        $created = $this->app->handle([
            'REQUEST_METHOD' => 'POST',
            'PATH_INFO' => '/recurrence-types',
            'php://input' => json_encode(['householdId' => 'house-1', 'name' => 'Quarterly', 'intervalDays' => 90]),
        ]);

        $this->assertContains(['id' => $created['id'], 'householdId' => 'house-1', 'name' => 'Quarterly', 'intervalDays' => 90], $this->list('/recurrence-types', 'house-1'));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function list(string $path, string $householdId): array
    {
        return $this->app->handle([
            'REQUEST_METHOD' => 'GET',
            'PATH_INFO' => $path,
            'QUERY_STRING' => 'householdId=' . $householdId,
        ]);
    }
}
