<?php

declare(strict_types=1);

namespace App\Application\Tasks\AssigneeGuard;

use App\Domain\Members\MemberRepositoryInterface;

// Members are soft-deleted, so the tasks.assigned_member_id foreign key no longer
// rejects a deleted assignee; the repository read (which hides deleted rows) does.
final class AssigneeGuard
{
    public function __construct(private readonly ?MemberRepositoryInterface $memberRepository)
    {
    }

    /**
     * @throws \InvalidArgumentException when the member is unknown, deleted, or in another household
     */
    public function ensureAssignable(?int $memberId, string $householdId): void
    {
        if ($memberId === null || $this->memberRepository === null) {
            return;
        }

        $member = $this->memberRepository->findById($memberId);
        if ($member === null || $member->householdId() !== $householdId) {
            throw new \InvalidArgumentException('Assigned member does not exist in this household.');
        }
    }
}
