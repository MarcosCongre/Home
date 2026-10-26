<?php

declare(strict_types=1);

namespace App\Domain\Members;

// Members are soft-deleted (saved with a deletedAt); reads never return them.
interface MemberRepositoryInterface
{
    public function save(Member $member): void;

    public function findById(int $id): ?Member;

    /**
     * @return array<int, Member>
     */
    public function findByHouseholdId(string $householdId): array;
}
