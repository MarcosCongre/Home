<?php

declare(strict_types=1);

namespace App\Application\Members\DeleteMember;

use App\Application\Shared\ImmediateTransactionRunner;
use App\Application\Shared\TransactionRunner;
use App\Domain\Members\MemberRepositoryInterface;
use App\Domain\Tasks\TaskRepositoryInterface;
use DateTimeImmutable;

final class DeleteMemberHandler
{
    private readonly TransactionRunner $transactionRunner;

    public function __construct(
        private readonly MemberRepositoryInterface $memberRepository,
        private readonly TaskRepositoryInterface $taskRepository,
        ?TransactionRunner $transactionRunner = null
    ) {
        $this->transactionRunner = $transactionRunner ?? new ImmediateTransactionRunner();
    }

    public function handle(DeleteMemberCommand $command): void
    {
        // Already-deleted members are not returned, so they are reported as not found too.
        $member = $this->memberRepository->findById($command->memberId);

        if ($member === null) {
            throw new \RuntimeException('Member not found.');
        }

        // The row is kept, so the old ON DELETE SET NULL no longer fires: unassign here.
        $this->transactionRunner->run(function () use ($member): void {
            foreach ($this->taskRepository->findByHouseholdId($member->householdId()) as $task) {
                if ($task->assignedMemberId() === $member->id()) {
                    $task->unassign();
                    $this->taskRepository->save($task);
                }
            }

            $member->delete(new DateTimeImmutable());
            $this->memberRepository->save($member);
        });
    }
}
