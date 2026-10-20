<?php

declare(strict_types=1);

namespace App\Application\Tasks\AutoAssignTasks;

use App\Application\Tasks\AssignmentAlert\AssignmentAlertNotifier;
use App\Domain\Alerts\AlertRepositoryInterface;
use App\Domain\Members\Member;
use App\Domain\Members\MemberRepositoryInterface;
use App\Domain\Tasks\Assignment\RandomTieBreaker;
use App\Domain\Tasks\Assignment\TaskAssignmentPolicy;
use App\Domain\Tasks\Assignment\TieBreaker;
use App\Domain\Tasks\Task;
use App\Domain\Tasks\TaskRepositoryInterface;
use DateTimeImmutable;

final class AutoAssignTasksHandler
{
    private readonly TaskAssignmentPolicy $policy;

    public function __construct(
        private readonly TaskRepositoryInterface $taskRepository,
        private readonly MemberRepositoryInterface $memberRepository,
        private readonly ?AlertRepositoryInterface $alertRepository = null,
        ?TieBreaker $tieBreaker = null
    ) {
        $this->policy = new TaskAssignmentPolicy($tieBreaker ?? new RandomTieBreaker());
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

        $decisions = $this->policy->assign($tasks, $memberIds, $now);
        $notifier = new AssignmentAlertNotifier($this->alertRepository);
        $updated = [];

        foreach ($tasks as $task) {
            if (!isset($decisions[$task->id()])) {
                continue;
            }

            $task->reassignTo($decisions[$task->id()], $now);
            $this->taskRepository->save($task);
            $notifier->notify($task, $decisions[$task->id()]);
            $updated[] = $task;
        }

        return $updated;
    }
}
