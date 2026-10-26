<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Members;

use App\Domain\Members\Member;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Member::class)]
final class MemberTest extends TestCase
{
    public function testANewMemberIsNotDeleted(): void
    {
        $member = Member::create(1, 'Ana', 'A', '#C4963A', 'house-1');

        $this->assertNull($member->deletedAt());
        $this->assertFalse($member->isDeleted());
    }

    public function testDeletingRecordsTheGivenDateAndKeepsTheFirstOne(): void
    {
        $member = Member::create(1, 'Ana', 'A', '#C4963A', 'house-1');
        $at = new \DateTimeImmutable('2026-10-24 09:00:00');

        $member->delete($at);
        $member->delete(new \DateTimeImmutable('2026-10-25 09:00:00'));

        $this->assertTrue($member->isDeleted());
        $this->assertSame($at, $member->deletedAt());
    }
}
