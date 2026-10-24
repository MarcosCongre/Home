<?php

declare(strict_types=1);

namespace Tests\Integration\Persistence;

use App\Domain\Tasks\Catalog\RecurrenceType;
use App\Infrastructure\Persistence\PdoRecurrenceTypeRepository;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class PdoRecurrenceTypeRepositoryTest extends TestCase
{
    private PdoRecurrenceTypeRepository $repository;

    protected function setUp(): void
    {
        $pdo = new \PDO('sqlite::memory:');
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->repository = new PdoRecurrenceTypeRepository($pdo);
    }

    public function testItInsertsUpdatesAndReadsRecurrenceTypes(): void
    {
        $recurrence = RecurrenceType::create(0, 'house-1', 'Monthly', 30, new DateTimeImmutable('2026-10-24 10:00:00'));
        $this->repository->save($recurrence);
        $this->assertGreaterThan(0, $recurrence->id());

        $recurrence->update('Every four weeks', 28, new DateTimeImmutable('2026-10-25 11:00:00'));
        $this->repository->save($recurrence);

        $found = $this->repository->findById($recurrence->id());
        $this->assertNotNull($found);
        $this->assertSame('Every four weeks', $found->name());
        $this->assertSame(28, $found->intervalDays());
        $this->assertSame('2026-10-25 11:00:00', $found->updatedAt()->format('Y-m-d H:i:s'));
    }

    public function testSoftDeletedRecurrenceTypesAreHiddenButStillCounted(): void
    {
        $recurrence = RecurrenceType::create(0, 'house-1', 'Weekly', 7, new DateTimeImmutable());
        $this->repository->save($recurrence);
        $this->repository->save(RecurrenceType::create(0, 'house-2', 'Daily', 1, new DateTimeImmutable()));

        $recurrence->delete(new DateTimeImmutable());
        $this->repository->save($recurrence);

        $this->assertNull($this->repository->findById($recurrence->id()));
        $this->assertSame([], $this->repository->findByHouseholdId('house-1'));
        $this->assertSame(1, $this->repository->countAllByHouseholdId('house-1'));
        $this->assertCount(1, $this->repository->findByHouseholdId('house-2'));
    }
}
