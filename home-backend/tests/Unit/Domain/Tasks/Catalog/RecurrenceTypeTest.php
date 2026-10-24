<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Tasks\Catalog;

use App\Domain\Tasks\Catalog\CatalogName;
use App\Domain\Tasks\Catalog\RecurrenceType;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class RecurrenceTypeTest extends TestCase
{
    public function testItCreatesARecurrenceTypeWithAnInterval(): void
    {
        $recurrence = RecurrenceType::create(0, 'house-1', ' Weekly ', 7, new DateTimeImmutable());

        $this->assertSame('Weekly', $recurrence->name());
        $this->assertSame(7, $recurrence->intervalDays());
        $this->assertSame('house-1', $recurrence->householdId());
    }

    public function testItAcceptsTheIntervalBounds(): void
    {
        $this->assertSame(1, RecurrenceType::create(0, 'house-1', 'Daily', 1, new DateTimeImmutable())->intervalDays());
        $this->assertSame(365, RecurrenceType::create(0, 'house-1', 'Yearly', 365, new DateTimeImmutable())->intervalDays());
    }

    public function testItRejectsAZeroInterval(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        RecurrenceType::create(0, 'house-1', 'Never', 0, new DateTimeImmutable());
    }

    public function testItRejectsAnIntervalAbove365(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        RecurrenceType::create(0, 'house-1', 'Rarely', 366, new DateTimeImmutable());
    }

    public function testItRejectsAnEmptyName(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        RecurrenceType::create(0, 'house-1', '', 7, new DateTimeImmutable());
    }

    public function testUpdateChangesNameAndInterval(): void
    {
        $recurrence = RecurrenceType::create(0, 'house-1', 'Monthly', 30, new DateTimeImmutable('2026-10-24 10:00:00'));
        $later = new DateTimeImmutable('2026-10-25 10:00:00');

        $recurrence->update('Every four weeks', 28, $later);

        $this->assertSame('Every four weeks', $recurrence->name());
        $this->assertSame(28, $recurrence->intervalDays());
        $this->assertEquals($later, $recurrence->updatedAt());
    }

    public function testCatalogNamesCompareCaseInsensitively(): void
    {
        $this->assertTrue(CatalogName::equals('Mon & Thu', ' mon & thu'));
        $this->assertTrue(CatalogName::equals('ÑANDÚ', 'ñandú'));
        $this->assertFalse(CatalogName::equals('Weekly', 'Biweekly'));
    }
}
