<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Tasks\Catalog;

use App\Domain\Tasks\Catalog\Category;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

final class CategoryTest extends TestCase
{
    public function testItCreatesACategoryWithATrimmedNameAndOptionalColor(): void
    {
        $now = new DateTimeImmutable('2026-10-24 10:00:00');
        $category = Category::create(0, 'house-1', '  Cleaning  ', '#C4623A', $now);

        $this->assertSame('Cleaning', $category->name());
        $this->assertSame('#C4623A', $category->color());
        $this->assertSame('house-1', $category->householdId());
        $this->assertEquals($now, $category->createdAt());
        $this->assertEquals($now, $category->updatedAt());
        $this->assertFalse($category->isDeleted());

        $plain = Category::create(0, 'house-1', 'Bills', null, $now);
        $this->assertNull($plain->color());
    }

    public function testAnEmptyColorMeansNoColor(): void
    {
        $category = Category::create(0, 'house-1', 'Bills', '  ', new DateTimeImmutable());

        $this->assertNull($category->color());
    }

    public function testItRejectsAnEmptyName(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Category::create(0, 'house-1', '   ', null, new DateTimeImmutable());
    }

    public function testItRejectsANameLongerThan64Characters(): void
    {
        Category::create(0, 'house-1', str_repeat('ñ', 64), null, new DateTimeImmutable());

        $this->expectException(\InvalidArgumentException::class);
        Category::create(0, 'house-1', str_repeat('a', 65), null, new DateTimeImmutable());
    }

    public function testItRejectsAColorThatIsNotSixDigitHex(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Category::create(0, 'house-1', 'Bills', '#FFF', new DateTimeImmutable());
    }

    public function testItRejectsAnEmptyHouseholdId(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        Category::create(0, ' ', 'Bills', null, new DateTimeImmutable());
    }

    public function testRenameUpdatesNameColorAndUpdatedAt(): void
    {
        $category = Category::create(0, 'house-1', 'Bills', null, new DateTimeImmutable('2026-10-24 10:00:00'));
        $later = new DateTimeImmutable('2026-10-25 10:00:00');

        $category->update(' Payments ', '#B53A3A', $later);

        $this->assertSame('Payments', $category->name());
        $this->assertSame('#B53A3A', $category->color());
        $this->assertEquals($later, $category->updatedAt());
    }

    public function testDeleteIsSoftAndTheFirstDateWins(): void
    {
        $category = Category::create(0, 'house-1', 'Bills', null, new DateTimeImmutable());
        $first = new DateTimeImmutable('2026-10-24 10:00:00');

        $category->delete($first);
        $category->delete(new DateTimeImmutable('2026-10-25 10:00:00'));

        $this->assertTrue($category->isDeleted());
        $this->assertEquals($first, $category->deletedAt());
    }
}
