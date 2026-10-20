# Feature: task-reopen

## Objective
Let users uncheck a completed task and check it again, persisting both transitions.

## Problem
- `src/application/tasks/useTasks.ts:173`: unchecking only updates the UI optimistically and returns before calling the API, so the task stays `completed` in the database.
- Re-checking calls `PATCH /tasks/{id}/complete`; `Task::complete()` (`home-backend/src/Domain/Tasks/Task.php:55`) throws `Task is already completed.`, surfaced as HTTP 500, and the UI reverts to unchecked.
- `PATCH /tasks/{id}` cannot be reused: `Task::update` replaces every field, so a status-only payload wipes assignee, day, time, etc.

## Why
Reported by the user (2026-10-19) on http://192.168.0.236/: most tasks show checked (server holds 20 completed, 5 pending), unchecking does not stick, re-checking fails.

## Scope
- Backend: `Task::reopen()` (status `pending`, `completedAt` null; error if not completed), `ReopenTask` command/handler, `PATCH /tasks/{id}/reopen` route + controller action, tests.
- Backend: map "already completed" / "not completed" domain conflicts to HTTP 409 instead of 500.
- Frontend: `reopenTask` in `taskApi.ts`; `completeTaskById` calls it when unchecking and rolls back on failure.
- Redeploy to the home server and verify.

## Constraints
- Follow existing hexagonal layout (Domain / Application / Infrastructure) and naming of `CompleteTask`.
- Delivery: `ask-on-risk`; forecast ~200 authored lines, single PR.

## Tasks
- [x] T1 — Backend reopen endpoint + 409 for task state conflicts, with tests (RED first). Route: delegated (writer trigger: 2+ non-trivial files).
- [x] T2 — Frontend: call reopen on uncheck, roll back on failure. Route: delegated together with T1 (same writer, preparation trigger).
- [ ] T3 — Redeploy with `deploy/deploy.sh`; verify uncheck/re-check against the server API. Route: inline.

## Acceptance criteria
- Unchecking a completed task persists `pending` and clears `completedAt`.
- Re-checking it persists `completed` again.
- Completing an already completed task returns 409, not 500.
- Existing tests stay green.

## Checks
- `cd home-backend && composer test` (phpunit).
- `pnpm build` (TypeScript + Vite; no frontend test runner exists).

## Progress
- 2026-10-19: document created on branch `fix/task-reopen` (from `chore/home-server-deploy`).

- 2026-10-19: T1 done (route: delegated writer). Added `TaskStateConflict` (extends `RuntimeException`, safe message), `Task::reopen()`, `ReopenTask` command/handler, `PATCH /tasks/{id}/reopen`, and 409 mapping in `public/index.php`.
  - RED: `composer test` -> 7 errors (ReopenTaskHandler class missing, `Task::reopen()` undefined, `TaskStateConflict` missing) across TaskTest, ReopenTaskHandlerTest and TaskControllerTest.
  - GREEN: `composer test` -> OK (58 tests, 209 assertions). `php -l public/index.php` -> no syntax errors.
  - The 409 mapping lives in the top-level `public/index.php` script, which has no test harness; it is covered by lint only and must be verified against the server in T3.
  - Commit: `7c1c370` feat(tasks): add reopen endpoint and return 409 on task state conflicts.
- 2026-10-19: T2 done (route: delegated writer). `reopenTask` in `taskApi.ts`; `completeTaskById` now calls reopen when unchecking and rolls back to the previous task on failure.
  - No frontend test runner exists (documented exception); checks: `pnpm build` -> built OK; `pnpm exec tsc --noEmit` -> no errors (`vite build` alone does not type-check).
  - Commit: the `fix(tasks)` commit that contains this progress entry.

## Next step
T3: redeploy with `deploy/deploy.sh` and verify uncheck/re-check plus the 409 response against the server API.
