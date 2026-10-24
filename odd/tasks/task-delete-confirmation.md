# Feature: task-delete-confirmation

**Status:** done, pending review
**Branch:** `feat/task-delete-confirmation` (from `origin/main`)

## Objective

Tasks can be deleted from the UI after a confirmation prompt. The confirmation is a reusable component that is also used to delete members.

## Context

This is requirement 1 of 4 from the 2026-10-24 request. The user asked for each requirement on its own branch. The work was first built on `feat/task-delete-and-repeatable` together with requirement 2, then cherry-picked here.

## Tasks

- [x] T1 — Reusable `ConfirmDialog` + delete task with confirmation. Route: delegated. Commit `56c4220` (cherry-pick of `4043018`). The delete button is in TaskModal edit mode (Calendar). `App.tsx` now wires `useTasks().deleteTask`. Router test covers `DELETE /tasks/{id}`.
- [x] T2 — Member deletion uses `ConfirmDialog`; the old bespoke `ConfirmDelete` was removed. Route: delegated. Commit `8d6bb76` (cherry-pick of `ad0f8f8`).

## Acceptance criteria

- Deleting a task or a member always asks for confirmation; cancel keeps the item.
- `ConfirmDialog` is accessible: `role="alertdialog"`, `aria-modal`, focus starts on Cancel, Escape cancels.

## Checks

- `npm run build`: see progress.
- `composer test`: see progress.

## Progress

- 2026-10-24: T1, T2 cherry-picked onto their own branch. `npm run build` OK; `composer test` OK (112 tests, 305 assertions).
