<?php

declare(strict_types=1);

namespace App\Application\Tasks\AutoAssignTasks;

use App\Application\Shared\ImmediateTransactionRunner;
use App\Application\Shared\TransactionRunner;
use App\Application\Tasks\AssignmentAlert\AssignmentAlertNotifier;
use App\Application\Tasks\HouseholdCatalogs\HouseholdCatalogs;
use App\Domain\Alerts\AlertRepositoryInterface;
use App\Domain\Members\Member;
use App\Domain\Members\MemberRepositoryInterface;
use App\Domain\Tasks\Assignment\RandomTieBreaker;
use App\Domain\Tasks\Assignment\TaskAssignmentPolicy;
use App\Domain\Tasks\Assignment\TieBreaker;
use App\Domain\Tasks\Catalog\RecurrenceTypeRepositoryInterface;
use App\Domain\Tasks\Task;
use App\Domain\Tasks\TaskRepositoryInterface;
use DateTimeImmutable;

final class AutoAssignTasksHandler
{
    private readonly TaskAssignmentPolicy $policy;
    private readonly TransactionRunner $transactionRunner;

    public function __construct(
        private readonly TaskRepositoryInterface $taskRepository,
        private readonly MemberRepositoryInterface $memberRepository,
        private readonly ?AlertRepositoryInterface $alertRepository = null,
        ?TieBreaker $tieBreaker = null,
        ?TransactionRunner $transactionRunner = null,
        private readonly ?RecurrenceTypeRepositoryInterface $recurrenceTypeRepository = null
    ) {
        $this->policy = new TaskAssignmentPolicy($tieBreaker ?? new RandomTieBreaker());
        $this->transactionRunner = $transactionRunner ?? new ImmediateTransactionRunner();
    }

    /**
     * @return array<int, Task> the tasks whose assignee changed
     */
    public function handle(AutoAssignTasksCommand $command): array
    {
        if (trim($command->householdId) === '') {
            throw new \InvalidArgumentException('householdId is required.');
        }

        $now = $command->now ?? new DateTimeImmutable();
        $tasks = $this->taskRepository->findByHouseholdId($command->householdId);
        $memberIds = array_map(
            static fn (Member $member): int => $member->id(),
            $this->memberRepository->findByHouseholdId($command->householdId)
        );

        $intervals = HouseholdCatalogs::load(null, $this->recurrenceTypeRepository, $command->householdId)
            ->intervalDaysByRecurrenceId();

        $decisions = $this->policy->assign($tasks, $memberIds, $now, $intervals);
        $updated = [];

        foreach ($tasks as $task) {
            if (isset($decisions[$task->id()])) {
                $updated[] = $task;
            }
        }

        // All reassignments are persisted together; alerts go out only after the commit.
        $this->transactionRunner->run(function () use ($updated, $decisions, $now): void {
            foreach ($updated as $task) {
                $task->reassignTo($decisions[$task->id()], $now);
                $this->taskRepository->save($task);
            }
        });

        $notifier = new AssignmentAlertNotifier($this->alertRepository);
        foreach ($updated as $task) {
            $notifier->notify($task, $decisions[$task->id()]);
        }

        return $updated;
    }
}
