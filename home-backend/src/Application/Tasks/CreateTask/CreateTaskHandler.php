<?php

declare(strict_types=1);

namespace App\Application\Tasks\CreateTask;

use App\Application\Tasks\AssigneeGuard\AssigneeGuard;
use App\Application\Tasks\AssignmentAlert\AssignmentAlertNotifier;
use App\Application\Tasks\CatalogGuard\CatalogGuard;
use App\Domain\Alerts\AlertRepositoryInterface;
use App\Domain\Members\MemberRepositoryInterface;
use App\Domain\Tasks\Catalog\CategoryRepositoryInterface;
use App\Domain\Tasks\Catalog\RecurrenceTypeRepositoryInterface;
use App\Domain\Tasks\Task;
use App\Domain\Tasks\TaskRepositoryInterface;
use DateTimeImmutable;

final class CreateTaskHandler
{
    public function __construct(
        private readonly TaskRepositoryInterface $taskRepository,
        private readonly ?AlertRepositoryInterface $alertRepository = null,
        private readonly ?MemberRepositoryInterface $memberRepository = null,
        private readonly ?CategoryRepositoryInterface $categoryRepository = null,
        private readonly ?RecurrenceTypeRepositoryInterface $recurrenceTypeRepository = null
    ) {
    }

    public function handle(CreateTaskCommand $command): Task
    {
        (new AssigneeGuard($this->memberRepository))->ensureAssignable($command->assignedMemberId, $command->householdId);
        (new CatalogGuard($this->categoryRepository, $this->recurrenceTypeRepository))
            ->ensureReferences($command->categoryId, $command->recurrenceId, $command->householdId);

        $task = Task::create(
            id: 0,
            title: $command->title,
            householdId: $command->householdId,
            createdAt: new DateTimeImmutable(),
            day: $command->day,
            time: $command->time,
            categoryId: $command->categoryId,
            recurrenceId: $command->recurrenceId,
            priority: $command->priority,
            repeatable: $command->repeatable
        );

        if ($command->assignedMemberId !== null) {
            $task->update(
                title: $command->title,
                assignedMemberId: $command->assignedMemberId,
                day: $command->day,
                time: $command->time,
                priority: $command->priority
            );
        }

        $this->taskRepository->save($task);

        if ($command->assignedMemberId !== null) {
            (new AssignmentAlertNotifier($this->alertRepository))->notify($task, $command->assignedMemberId);
        }

        return $task;
    }
}
