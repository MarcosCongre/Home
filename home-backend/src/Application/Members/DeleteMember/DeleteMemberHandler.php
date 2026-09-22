<?php

declare(strict_types=1);

namespace App\Application\Members\DeleteMember;

use App\Domain\Members\MemberRepositoryInterface;

final class DeleteMemberHandler
{
    public function __construct(
        private readonly MemberRepositoryInterface $memberRepository
    ) {
    }

    public function handle(DeleteMemberCommand $command): void
    {
        $member = $this->memberRepository->findById($command->memberId);

        if ($member === null) {
            throw new \RuntimeException('Member not found.');
        }

        $this->memberRepository->delete($command->memberId);
    }
}
