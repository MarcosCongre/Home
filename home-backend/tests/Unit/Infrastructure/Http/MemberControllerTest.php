<?php

declare(strict_types=1);

namespace Tests\Unit\Infrastructure\Http;

use App\Domain\Members\Member;
use App\Infrastructure\Http\MemberController;
use App\Infrastructure\Persistence\InMemoryMemberRepository;
use PHPUnit\Framework\TestCase;

final class MemberControllerTest extends TestCase
{
    public function testItCreatesAMemberFromPayload(): void
    {
        $repository = new InMemoryMemberRepository();
        $controller = new MemberController($repository);

        $member = $controller->create([
            'name' => 'Lily',
            'avatar' => 'L',
            'color' => '#9B7DB5',
            'householdId' => 'house-1',
        ]);

        $this->assertSame('Lily', $member['name']);
        $this->assertSame('L', $member['avatar']);
        $this->assertSame('#9B7DB5', $member['color']);
        $this->assertSame('house-1', $member['householdId']);
    }

    public function testItListsMembersForTheHousehold(): void
    {
        $repository = new InMemoryMemberRepository();
        $first = new Member(
            id: 1,
            name: 'Maya',
            avatar: 'M',
            color: '#C4623A',
            householdId: 'house-2'
        );
        $second = new Member(
            id: 2,
            name: 'James',
            avatar: 'J',
            color: '#6B7C4E',
            householdId: 'house-2'
        );

        $repository->save($first);
        $repository->save($second);

        $controller = new MemberController($repository);
        $members = $controller->list('house-2');

        $this->assertCount(2, $members);
        $this->assertSame([1, 2], array_map(static fn (array $member): int => $member['id'], $members));
    }
}
