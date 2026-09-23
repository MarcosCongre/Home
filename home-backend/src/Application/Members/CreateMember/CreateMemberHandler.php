<?php

declare(strict_types=1);

namespace App\Application\Members\CreateMember;

use App\Domain\Members\Member;
use App\Domain\Members\MemberRepositoryInterface;

final class CreateMemberHandler
{
    public function __construct(
        private readonly MemberRepositoryInterface $memberRepository
    ) {
    }

    public function handle(CreateMemberCommand $command): Member
    {
        $member = Member::create(
            id: 0,
            name: $command->name,
            avatar: $command->avatar,
            color: $command->color,
            householdId: $command->householdId,
        );

        $this->memberRepository->save($member);

        return $member;
    }
}
