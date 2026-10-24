# Feature: task-delete-confirmation

**Status:** T1–T5 done, pending review
**Branch:** `feat/task-delete-confirmation` (from `origin/main`)

## Objective

Tasks can be deleted from the UI after a confirmation prompt. The confirmation is a reusable component that is also used to delete members.

## Context

This is requirement 1 of 4 from the 2026-10-24 request. The user asked for each requirement on its own branch. The work was first built on `feat/task-delete-and-repeatable` together with requirement 2, then cherry-picked here.

## Tasks

- [x] T1 — Reusable `ConfirmDialog` + delete task with confirmation. Route: delegated. Commit `56c4220` (cherry-pick of `4043018`). The delete button is in TaskModal edit mode (Calendar). `App.tsx` now wires `useTasks().deleteTask`. Router test covers `DELETE /tasks/{id}`.
- [x] T2 — Member deletion uses `ConfirmDialog`; the old bespoke `ConfirmDelete` was removed. Route: delegated. Commit `8d6bb76` (cherry-pick of `ad0f8f8`).
- [x] T3 — Handle delete failures and prevent double submit (from native review). Route: delegated (writer, 4 files). Commit `6ce5001`. `ConfirmDialog` gains `pending`/`pendingLabel`/`error`: while pending both buttons are disabled, Escape/backdrop are ignored and the confirm shows "Eliminando…"; an error renders as `role="alert"` and the dialog stays open. Its backdrop click no longer bubbles to a parent modal. `TaskModal` awaits `onDelete` (now `() => Promise<void>`) and shows `es.taskModal.deleteFailed`; `Members` awaits `onRemoveUser` and only unassigns/closes on success, else shows `es.members.removeFailed`.
- [x] T4 — Soft delete for tasks and members. Route: delegated (writer, backend). Commit `a55b579`. Migration `004_add_soft_delete.sql` adds nullable `deleted_at DATETIME` to `tasks` and `members`. Entities get `delete($now)` (first date wins), `isDeleted()`, `deletedAt()`; `Task::unassign()` keeps `lastAssignedAt`. Handlers stamp and save instead of removing rows; hard `delete()` was removed from both repository interfaces. All Pdo and InMemory reads filter deleted rows. `DeleteMemberHandler` now unassigns the member's tasks inside a `TransactionRunner` (the FK `ON DELETE SET NULL` no longer fires); `MemberController`/`Router` pass the task repository and runner. HTTP contract unchanged.
- [x] T5 — Reject assignment to deleted or foreign members (from native review: the soft delete keeps the row, so the `assigned_member_id` FK no longer rejects a deleted assignee). Route: delegated (writer, backend). Commit: see Progress. New `AssigneeGuard` checks the assignee via `MemberRepositoryInterface::findById` (hides deleted rows) and the task's household; `CreateTaskHandler` and `UpdateTaskHandler` take an optional member repository (same optional pattern as the alert repository) and validate before any mutation. `TaskController` passes it; `Router` now passes it for `POST /tasks` and `PATCH /tasks/{id}` (`index.php` already injects `PdoMemberRepository`). Rejection throws `InvalidArgumentException` → HTTP 400, the same mapping as other invalid payload ids. Auto-assign already uses `findByHouseholdId` and is covered by `testItSkipsSoftDeletedMembersAndTasks`. `CompleteTask` has no next-assignee input on this branch.

## Acceptance criteria

- Deleting a task or a member always asks for confirmation; cancel keeps the item.
- `ConfirmDialog` is accessible: `role="alertdialog"`, `aria-modal`, focus starts on Cancel, Escape cancels.
- A failed delete keeps the dialog open with an error; Delete cannot fire twice while a request is pending.
- Deleted tasks and members keep their rows with `deleted_at` set and never appear in any read; deleting again is not found.
- Creating or updating a task with an unknown, deleted, or other-household assignee is rejected with HTTP 400 and changes nothing.

## Checks

- `npm run build`: see progress.
- `composer test`: see progress.

## Progress

- 2026-10-24: T1, T2 cherry-picked onto their own branch. `npm run build` OK; `composer test` OK (112 tests, 305 assertions).
- 2026-10-24: T3 — no frontend test runner; `npm run build` OK, `npx tsc --noEmit` exit 0.
- 2026-10-24: T4 — RED: `composer test` 128 tests, 8 errors, 2 failures (missing `delete`/`unassign`/`deletedAt`, no `deleted_at` column, member tasks not unassigned). GREEN: `composer test` OK (128 tests, 344 assertions). `npm run build` OK.
- 2026-10-24: T5 — RED: `composer test` 138 tests, 4 errors, 4 failures (handlers had no member repository; deleted/unknown/foreign assignees accepted; router accepted a deleted member). GREEN: `composer test` OK (138 tests, 360 assertions).

## Next step

- Apply `004_add_soft_delete.sql` manually on the home server MariaDB before deploying the backend; without it every task/member query fails on the missing column.
- `PdoMemberRepository` has no automated test (its `ON DUPLICATE KEY` SQL is MySQL-only, so it cannot run on SQLite); verify member delete/list against MariaDB.
