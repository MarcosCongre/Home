<?php

declare(strict_types=1);

namespace App\Application\Tasks\UpdateTask;

use App\Application\Tasks\AssigneeGuard\AssigneeGuard;
use App\Domain\Members\MemberRepositoryInterface;
use App\Domain\Tasks\Task;
use App\Domain\Tasks\TaskRepositoryInterface;

final class UpdateTaskHandler
{
    public function __construct(
        private readonly TaskRepositoryInterface $taskRepository,
        private readonly ?MemberRepositoryInterface $memberRepository = null
    ) {
    }

    public function handle(UpdateTaskCommand $command): Task
    {
        $task = $this->taskRepository->findById($command->taskId);

        if ($task === null) {
            throw new \RuntimeException('Task not found.');
        }

        (new AssigneeGuard($this->memberRepository))->ensureAssignable($command->assignedMemberId, $task->householdId());

        $task->update(
            title: $command->title,
            assignedMemberId: $command->assignedMemberId,
            status: $command->status,
            day: $command->day,
            time: $command->time,
            category: $command->category,
            recurrence: $command->recurrence,
            priority: $command->priority
        );
        $this->taskRepository->save($task);

        return $task;
    }
}