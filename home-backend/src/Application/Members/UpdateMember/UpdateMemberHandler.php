<?php

declare(strict_types=1);

namespace App\Application\Members\UpdateMember;

use App\Domain\Members\Member;
use App\Domain\Members\MemberRepositoryInterface;

final class UpdateMemberHandler
{
    public function __construct(
        private readonly MemberRepositoryInterface $memberRepository
    ) {
    }

    public function handle(UpdateMemberCommand $command): Member
    {
        $member = $this->memberRepository->findById($command->memberId);

        if ($member === null) {
            throw new \RuntimeException('Member not found.');
        }

        $member->update(
            name: $command->name,
            avatar: $command->avatar,
            color: $command->color,
        );

        $this->memberRepository->save($member);

        return $member;
    }
}
