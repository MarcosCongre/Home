# Feature: task-auto-assign

## Objective
Add an on-demand "auto-assign" action that distributes household tasks among members, balancing load by frequency and priority and rotating each task only when its cycle ends.

## Problem
Assignment is manual only (`TaskModal.tsx` assignee field, `assignedMemberId` in `POST/PATCH /tasks`). No random or balanced assignment exists anywhere in the repo.

## Why
User request (2026-10-19): random assignment that respects task characteristics — a weekly task must not be reassigned daily to different members; priority must count. Trigger chosen by the user: on demand (option a), `POST /tasks/auto-assign` + a UI button. No cron.

## Scope
- Domain: pure assignment policy (weights, cycle lengths, balancing, rotation), deterministic via an injected tie-breaker.
- Persistence: `last_assigned_at` column on `tasks` (MariaDB migration `003_*` + SQLite schema in `PdoTaskRepository`), set whenever the assignee changes.
- Application + HTTP: `AutoAssignTasks` command/handler, `POST /tasks/auto-assign` returning the updated tasks; assignment alerts for changed assignees, mirroring `CreateTaskHandler`.
- Frontend: `autoAssignTasks` in `taskApi.ts`, a "Repartir tareas" button, refresh tasks after it.

## Rules
- Weight = frequency (times per week) x priority. Frequency: `Daily`=7, `Mon & Thu`/`Tue & Fri`=2, `Weekly`=1, `Biweekly`=0.5, `Monthly`=0.25, `Once`/`none`/unknown=1. Priority: `low`=1, `med`/`medium`=2, `high`=3, unknown=2.
- Cycle: `Daily`, `Mon & Thu`, `Tue & Fri`, `Weekly` = 7 days (someone owns a daily task for a whole week, not one day); `Biweekly` = 14 days; `Monthly` = 1 calendar month. Unknown recurring values = 7 days.
- Eligible: recurring tasks in any status; one-off (`Once`/`none`/null) tasks only when pending.
- Locked: an assigned recurring task whose `lastAssignedAt + cycle > now` keeps its assignee; an assigned one-off task keeps its assignee. Locked tasks still count toward member load.
- Reassigned: unassigned eligible tasks, and recurring tasks whose cycle has expired (or `lastAssignedAt` is null).
- Balancing: reassigned tasks sorted by weight desc; each goes to the member with the lowest accumulated load, excluding the previous assignee when 2+ members exist; ties broken by the injected tie-breaker (random in production).
- No members -> no changes (empty result, not an error).

## Constraints
- Hexagonal layout (Domain / Application / Infrastructure), naming like `CompleteTask` / `ReopenTask`.
- Branch `feat/task-auto-assign` from `fix/task-reopen` HEAD `68a922d`.
- Delivery: `ask-on-risk`; forecast ~600 authored lines (above the ~400 budget -> ask chain strategy before the commit that crosses it). Chain strategy chosen by the user (2026-10-20): `stacked-to-main`.
- Slices: PR1 = backend (T1 `43ea133` + T1b); PR2 = frontend (T2), stacked on PR1. Note: this branch sits on `fix/task-reopen` (28 commits not in `main`), so PR1 can only target `main` cleanly after that branch merges.
- Server deploy needs the new migration applied; deploy is a user decision.

## Tasks
- [x] T1 — Domain policy + persistence column + handler + `POST /tasks/auto-assign`, with tests (RED first). Route: delegated (writer trigger: 2+ non-trivial files).
  - RED: `composer test` -> `Tests: 108, Assertions: 211, Errors: 49, Failures: 1` (missing policy/handler classes, unknown `lastAssignedAt` parameter, unrouted `/tasks/auto-assign`).
  - GREEN: `composer test` -> `OK (108 tests, 289 assertions)`; `php -l public/index.php` -> no syntax errors.
  - Notes: policy in `App\Domain\Tasks\Assignment\TaskAssignmentPolicy` (+ `TieBreaker`, `RandomTieBreaker`); recurrence/priority matched case-insensitively; monthly cycle clamps to the last day of a shorter month; a task assigned to a member not in the household is treated as unassigned; ineligible tasks (completed or in-progress one-offs) do not count toward load. `Task::update` sets `lastAssignedAt` only when the assignee changes (null when unassigned). Assignment alerts extracted to `AssignmentAlertNotifier`, shared with `CreateTaskHandler`. Route requires `householdId` in the POST body (400 when missing). `public/index.php` needed no change (Router already receives the member and alert repositories). SQLite: only the CREATE gained the column (no ALTER for pre-existing SQLite tables, matching repo practice).
  - RDD: assess `68a922d..43ea133` -> medium, `review_due` true (`slice_budget_reached`); consent granted; lens `review-reliability` -> approved, acknowledged (lineage `review-42686c8c285556f5`). Advisory findings: R3-create-assignee-no-date (WARNING), R3-tz-parse (WARNING), R3-schema-dependency (WARNING, deploy order), R3-non-atomic-loop (SUGGESTION).
- [x] T1b — Fix advisory findings inside T1 scope: set `lastAssignedAt` when a task is created with an assignee (breaks acceptance criterion otherwise); parse `last_assigned_at` with the same timezone used to write it; wrap auto-assign saves in a transaction if the repo pattern allows. Route: delegated.
  - RED: `composer test` -> `Tests: 111, Assertions: 298, Errors: 1` (`PdoTransactionRunner` not found in the mid-batch failure test). The two other new tests passed on first run (see notes).
  - GREEN: `composer test` -> `OK (111 tests, 303 assertions)`; `php -l public/index.php` -> no syntax errors.
  - Notes: R3-create-assignee-no-date refuted by evidence: `CreateTaskHandler` routes the assignee through `Task::update`, which stamps `lastAssignedAt` when the assignee changes from null; regression test `testATaskCreatedWithAnAssigneeKeepsItOnTheNextAutoAssign` kept. R3-tz-parse refuted: `DbDateTime::format` writes in `date_default_timezone_get()` (the app timezone, set in `index.php`) and the bare `new DateTimeImmutable` read uses the same default zone, like `created_at`/`completed_at`; regression test `testItReadsTimestampsBackInTheTimezoneUsedToWriteThem` (Asia/Tokyo) kept, no code change. R3-non-atomic-loop fixed: `App\Application\Shared\TransactionRunner` port, `ImmediateTransactionRunner` default (in-memory), `App\Infrastructure\Persistence\PdoTransactionRunner` (joins an open transaction, rolls back and rethrows on failure), wired `index.php` -> `Router` -> `TaskController` -> `AutoAssignTasksHandler`. Alerts are now sent after the commit, so a rolled-back batch sends none.
- [ ] T2 — Frontend API + button + refresh. Route: delegated (same writer context, preparation trigger).
- [ ] T3 — Deploy (migration + code) to the home server. Route: inline, only on user request.

## Acceptance criteria
- A weekly task assigned 3 days ago keeps its assignee after auto-assign; one assigned 8 days ago rotates to a different member.
- Total weight is balanced: the heaviest tasks do not pile on one member.
- Unassigned tasks get an assignee; completed one-off tasks are untouched.
- Existing tests stay green.

## Checks
- `cd home-backend && composer test` (phpunit).
- `pnpm build` and `pnpm exec tsc --noEmit` (no frontend test runner exists).

## Progress
- 2026-10-19: document created on branch `feat/task-auto-assign`.
- 2026-10-19: T1 done (backend auto-assign, migration `003_add_task_last_assigned_at.sql`, tests green). ~1016 authored changed lines (≈419 src+migration, ≈597 tests), above the ~400 budget.
- 2026-10-20: T1b done (transactional auto-assign; two review findings refuted with regression tests). Tests green.

## Next step
T2 (frontend). Server deploy (T3) must apply migration 003 before the new backend code.
