# Feature: repeatable-tasks

**Status:** T1–T3 done, pending review
**Branch:** `feat/repeatable-tasks` (stacked on `feat/task-delete-confirmation`)

## Objective

A task can be marked as repeatable: it is done several times a day. When a member completes it, they pick who does it next, and the task goes back to pending assigned to that member.

## Context

This is requirement 2 of 4 from the 2026-10-24 request. The user asked for each requirement on its own branch. The work was first built on `feat/task-delete-and-repeatable` (without the soft-delete, T3 and T5 work of requirement 1), then cherry-picked here on top of `feat/task-delete-confirmation`.

## Decisions

- **D1** (user, 2026-10-24): a repeatable task is a single task that returns to pending, reassigned to a member chosen by whoever completed it. Rejected: a copy of the task per repetition; a per-day completion counter.

## Tasks

- [x] T1 — Backend: `repeatable` flag and complete-and-reassign. Route: delegated (writer, cherry-pick). Commit `58ed810` (cherry-pick of `7e851a5`). `Task` gets `repeatable` (entity, create/update commands, Pdo/InMemory repositories, JSON). Completing a repeatable task records `completedAt`, keeps the completion alert, returns the task to pending and, with `nextAssigneeId`, hands it to that member (plus an assignment alert). Non-repeatable tasks complete as before and ignore `nextAssigneeId`. Conflicts in `Task.php` and `PdoTaskRepository.php` were resolved keeping both `deleted_at` and `repeatable` (constructor, SQLite schema, UPDATE/INSERT columns, binds, hydration). The migration was renamed to `005_add_task_repeatable.sql` in the same resolution; the commit message still says "migration 004" because the original message was kept.
- [x] T2 — Frontend: repeatable toggle and reassign picker. Route: delegated (writer, cherry-pick). Commit `9c1d59b` (cherry-pick of `d344f90`). `TaskModal` has a repeatable toggle; the Dashboard shows a badge and, when completing a repeatable task, a `ConfirmDialog` (`tone="primary"`) with a member picker that defaults to the current assignee and sends `nextAssigneeId`. Applied without conflicts on top of the T3 `ConfirmDialog` (`pending`/`error`) changes.
- [x] T3 — Renumber migration and validate the next assignee with `AssigneeGuard`. Route: delegated (writer, backend). Migration renamed inside T1 (004 is taken by `004_add_soft_delete.sql`, already applied on the server). Commit `cc853f3`: `CompleteTaskHandler` drops its own household check and uses the shared `AssigneeGuard`, so an unknown, soft-deleted, or other-household next assignee is rejected with `InvalidArgumentException` → HTTP 400 and the task is left untouched.

## Acceptance criteria

- A task can be created or edited as repeatable; the flag round-trips through the API.
- Completing a repeatable task asks who does it next; the task returns to pending assigned to that member and `completedAt` holds the latest completion.
- Completing a non-repeatable task behaves exactly as before.
- An unknown, soft-deleted, or other-household next assignee is rejected with HTTP 400 and changes nothing.
- Soft delete (`deleted_at`), `AssigneeGuard` and delete pending/error handling from requirement 1 keep working.

## Known notes

- There is no backend completion-history store; `completedAt` keeps only the latest completion of a repeatable task.
- Handing a repeatable task to another member also creates a "Nueva tarea asignada" alert, in addition to the completion alert.
- `005_add_task_repeatable.sql` must be applied manually on the home server MariaDB BEFORE deploying the backend; without it task writes and reads fail on the missing `repeatable` column.

## Checks

- `composer test` (in `home-backend`)
- `npx tsc --noEmit`
- `npm run build`

## Progress

- 2026-10-24: T1 — after resolving conflicts, `composer test` OK (149 tests, 386 assertions).
- 2026-10-24: T2 — cherry-pick applied cleanly; `npx tsc --noEmit` exit 0; `npm run build` OK.
- 2026-10-24: T3 — RED: new `testItRejectsASoftDeletedNextAssignee` failed (expected the `AssigneeGuard` message, got "Member 2 does not belong to this household."). The deleted member was already rejected because `findById` hides deleted rows; the RED is on the shared contract, not on acceptance. GREEN: `composer test` OK (150 tests, 390 assertions).

## Next step

- Apply `005_add_task_repeatable.sql` on the home server before deploying.
- No frontend test runner: verify the hand-off picker manually in the app.
