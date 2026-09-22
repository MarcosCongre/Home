<?php

declare(strict_types=1);

namespace App\Application\Members\ListMembers;

use App\Domain\Members\Member;
use App\Domain\Members\MemberRepositoryInterface;

final class ListMembersHandler
{
    public function __construct(
        private readonly MemberRepositoryInterface $memberRepository
    ) {
    }

    /**
     * @return array<int, Member>
     */
    public function handle(ListMembersQuery $query): array
    {
        return $this->memberRepository->findByHouseholdId($query->householdId);
    }
}
