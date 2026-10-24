<?php

declare(strict_types=1);

namespace App\Application\Tasks\UpdateTask;

use App\Application\Tasks\AssigneeGuard\AssigneeGuard;
use App\Application\Tasks\CatalogGuard\CatalogGuard;
use App\Domain\Members\MemberRepositoryInterface;
use App\Domain\Tasks\Catalog\CategoryRepositoryInterface;
use App\Domain\Tasks\Catalog\RecurrenceTypeRepositoryInterface;
use App\Domain\Tasks\Task;
use App\Domain\Tasks\TaskRepositoryInterface;

final class UpdateTaskHandler
{
    public function __construct(
        private readonly TaskRepositoryInterface $taskRepository,
        private readonly ?MemberRepositoryInterface $memberRepository = null,
        private readonly ?CategoryRepositoryInterface $categoryRepository = null,
        private readonly ?RecurrenceTypeRepositoryInterface $recurrenceTypeRepository = null
    ) {
    }

    public function handle(UpdateTaskCommand $command): Task
    {
        $task = $this->taskRepository->findById($command->taskId);

        if ($task === null) {
            throw new \RuntimeException('Task not found.');
        }

        // Validate everything before mutating, so a rejected update changes nothing.
        // A null id is a no-op for the guard, so keeping or clearing the assignee skips validation.
        (new AssigneeGuard($this->memberRepository))->ensureAssignable(
            $command->clearAssignee ? null : $command->assignedMemberId,
            $task->householdId()
        );
        (new CatalogGuard($this->categoryRepository, $this->recurrenceTypeRepository))->ensureReferences(
            $command->clearCategory ? null : $command->categoryId,
            $command->clearRecurrence ? null : $command->recurrenceId,
            $task->householdId()
        );

        $task->update(
            title: $command->title,
            assignedMemberId: $command->clearAssignee ? null : ($command->assignedMemberId ?? $task->assignedMemberId()),
            status: $command->status,
            day: $command->day,
            time: $command->time,
            priority: $command->priority,
            repeatable: $command->repeatable
        );

        if ($command->clearCategory) {
            $task->changeCategory(null);
        } elseif ($command->categoryId !== null) {
            $task->changeCategory($command->categoryId);
        }

        if ($command->clearRecurrence) {
            $task->changeRecurrence(null);
        } elseif ($command->recurrenceId !== null) {
            $task->changeRecurrence($command->recurrenceId);
        }

        $this->taskRepository->save($task);

        return $task;
    }
}
