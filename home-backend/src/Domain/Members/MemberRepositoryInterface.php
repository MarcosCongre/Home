<?php

declare(strict_types=1);

namespace App\Domain\Members;

interface MemberRepositoryInterface
{
    public function save(Member $member): void;

    public function findById(int $id): ?Member;

    /**
     * @return array<int, Member>
     */
    public function findByHouseholdId(string $householdId): array;

    public function delete(int $id): void;
}
