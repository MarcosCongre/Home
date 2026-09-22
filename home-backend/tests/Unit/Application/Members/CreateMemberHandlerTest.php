<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Members;

use App\Application\Members\CreateMember\CreateMemberCommand;
use App\Application\Members\CreateMember\CreateMemberHandler;
use App\Infrastructure\Persistence\InMemoryMemberRepository;
use PHPUnit\Framework\TestCase;

final class CreateMemberHandlerTest extends TestCase
{
    public function testItCreatesAMemberAndPersistsIt(): void
    {
        $repository = new InMemoryMemberRepository();
        $handler = new CreateMemberHandler($repository);

        $member = $handler->handle(new CreateMemberCommand(
            name: 'Maya',
            avatar: 'M',
            color: '#C4623A',
            householdId: 'house-42',
        ));

        $this->assertSame('Maya', $member->name());
        $this->assertSame('M', $member->avatar());
        $this->assertSame('#C4623A', $member->color());
        $this->assertSame('house-42', $member->householdId());
        $this->assertNotNull($repository->findById($member->id()));
    }
}
