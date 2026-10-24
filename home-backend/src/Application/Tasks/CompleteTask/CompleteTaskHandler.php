<?php

declare(strict_types=1);

namespace App\Application\Tasks\CompleteTask;

use App\Application\Alerts\CreateAlert\CreateAlertCommand;
use App\Application\Alerts\CreateAlert\CreateAlertHandler;
use App\Application\Tasks\AssignmentAlert\AssignmentAlertNotifier;
use App\Domain\Alerts\AlertRepositoryInterface;
use App\Domain\Members\MemberRepositoryInterface;
use App\Domain\Tasks\Task;
use App\Domain\Tasks\TaskRepositoryInterface;
use DateTimeImmutable;
use Throwable;

final class CompleteTaskHandler
{
    public function __construct(
        private TaskRepositoryInterface $taskRepository,
        private ?AlertRepositoryInterface $alertRepository = null,
        private ?MemberRepositoryInterface $memberRepository = null
    ) {
    }

    public function handle(CompleteTaskCommand $command): Task
    {
        $task = $this->taskRepository->findById($command->taskId);

        if ($task === null) {
            throw new \RuntimeException(sprintf('Task %s was not found.', $command->taskId));
        }

        if ($task->isRepeatable() && $command->nextAssigneeId !== null) {
            $this->assertMemberBelongsToHousehold($command->nextAssigneeId, $task->householdId());
        }

        // Credit the completion to whoever held the task before it is handed on.
        $memberId = $task->assignedMemberId();
        $task->complete($command->nextAssigneeId);
        $this->taskRepository->save($task);

        $nextAssigneeId = $task->assignedMemberId();
        if ($task->isRepeatable() && $nextAssigneeId !== null && $nextAssigneeId !== $memberId) {
            (new AssignmentAlertNotifier($this->alertRepository))->notify($task, $nextAssigneeId);
        }

        if ($this->alertRepository !== null) {
            $description = $memberId !== null
                ? sprintf(
                    'Tarea completada: "%s". Asignada a miembro #%d.',
                    $task->title()->value(),
                    $memberId
                )
                : sprintf('Tarea completada: "%s".', $task->title()->value());

            // The task is already persisted; an alert failure must not fail the request.
            try {
                $alertHandler = new CreateAlertHandler($this->alertRepository);
                $alertHandler->handle(new CreateAlertCommand(
                    householdId: $task->householdId(),
                    memberId: $memberId,
                    title: 'Tarea completada',
                    body: $description,
                    icon: 'check-circle',
                    urgent: false,
                    createdAt: new DateTimeImmutable()
                ));
            } catch (Throwable $exception) {
                error_log(sprintf('Unable to create task completion alert: %s', $exception->getMessage()));
            }
        }

        return $task;
    }

    private function assertMemberBelongsToHousehold(int $memberId, string $householdId): void
    {
        // Without a member repository (e.g. isolated tests) the id is trusted as given.
        if ($this->memberRepository === null) {
            return;
        }

        $member = $this->memberRepository->findById($memberId);
        if ($member === null || $member->householdId() !== $householdId) {
            throw new \InvalidArgumentException(sprintf('Member %d does not belong to this household.', $memberId));
        }
    }
}
