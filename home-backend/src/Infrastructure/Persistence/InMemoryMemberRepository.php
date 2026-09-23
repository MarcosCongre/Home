<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Members\Member;
use App\Domain\Members\MemberRepositoryInterface;

final class InMemoryMemberRepository implements MemberRepositoryInterface
{
    /** @var array<int, Member> */
    private array $members = [];

    public function save(Member $member): void
    {
        if ($member->id() === 0) {
            $member->assignGeneratedId($this->nextId());
        }

        $this->members[$member->id()] = $member;
    }

    private function nextId(): int
    {
        return $this->members === [] ? 1 : max(array_keys($this->members)) + 1;
    }

    public function findById(int $id): ?Member
    {
        return $this->members[$id] ?? null;
    }

    /**
     * @return array<int, Member>
     */
    public function findByHouseholdId(string $householdId): array
    {
        $members = array_values(array_filter(
            $this->members,
            static fn (Member $member): bool => $member->householdId() === $householdId
        ));

        usort($members, static fn (Member $a, Member $b): int => $a->id() <=> $b->id());

        return $members;
    }

    public function delete(int $id): void
    {
        unset($this->members[$id]);
    }
}
