# Feature: task-catalogs

**Status:** T1–T2b done, review T2 + T2b next, then T3
**Branch:** `feat/task-catalogs` (stacked on `feat/repeatable-tasks`)

## Objective

Each household manages its own task categories and recurrence types (create, rename, delete) from a new Settings tab. Tasks pick from those catalogs, and auto-assignment derives frequency and cycle length from the recurrence.

## Context

This is requirement 3 of 4 from the 2026-10-24 request; each requirement lives on its own branch. Today category and recurrence are unvalidated free strings end to end. The lists are hard-coded in `src/infrastructure/demo/demoData.ts:6-7`, and recurrence meaning lives only in `TaskAssignmentPolicy::FREQUENCIES` and `cycleEnd()`. There is no households table: `household_id` is a `VARCHAR(128)` column on every scoped table.

## Decisions

- **D2** (user, 2026-10-24): a recurrence type is a name plus "every N days", and `TaskAssignmentPolicy` uses it. Categories are per household.
- **D3** (user, 2026-10-24): catalogs are managed in a new Settings tab.
- **D5** (user, 2026-10-24): delivery `ask-on-risk` → chain strategy `stacked-to-main` (forecast ~1500 authored lines). Slice boundaries are recorded per task.
- **D4** (agent default, stated to the user): tasks reference catalog entries by id, so renames do not break tasks; catalogs use soft delete; each household starts with today's default lists; frequency per week = 7/N and cycle = N days; "Monthly" becomes every 30 days.

## Tasks

- [x] T1 — Backend catalogs: `Category` and `RecurrenceType` entities, repositories (Pdo + InMemory), CRUD handlers, controllers, routes, wiring, migration `006`, default seeding per household.
  - Route: delegated (writer). Trigger: 2+ non-trivial files across domain, application and infrastructure.
  - Commit: `da5738e` (+2202/−5, of which 784 test lines; over the ~400-line heuristic because two full CRUD stacks with tests are one coherent unit).
  - Summary: domain in `src/Domain/Tasks/Catalog` (entities, `CatalogName` rules, `DefaultCatalogs`, `CatalogEntryNotFoundException`); handlers in `src/Application/Catalog`; Pdo (SQLite self-schema, SELECT-then-UPDATE/INSERT) and InMemory repositories; `CategoryController`/`RecurrenceTypeController`; routes `GET|POST /categories`, `PATCH|DELETE /categories/{id}` and the same for `/recurrence-types`. PATCH/DELETE take `householdId` from the query string or JSON body (missing → 400). Not found (unknown, deleted, other household) maps to 404 in `public/index.php`; duplicates and validation errors are 400. The List handlers seed defaults inside a transaction when the household has zero rows, soft-deleted included. Migration `006_create_task_catalogs.sql` adds both tables.
  - Review: assessed `high` (`high_risk`) vs `acb86d2`; consent granted; 4-lens review approved and acknowledged (`review-f0cd7520bae2ca09`). Reviewed boundary is now `cfdbe60`. A whole-stack review (from `bcf3255`, 87 files) was also granted but stopped with `lens_context_budget_exceeded`; the stack is already covered by `review-bab5102a7efbdcc9` (req1+req2) plus this T1 review.
  - Advisory follow-ups (non-blocking): `householdId` is client-asserted like members (no auth binding); GET list seeds any household id; update handlers mutate the entity before the duplicate check (InMemory keeps the mutation on reject); household id is not trimmed/bounded consistently; "Mon & Thu"/"Tue & Fri" as every 3 days is an approximation.
- [x] T2 — Backend tasks use catalogs: tasks reference `categoryId`/`recurrenceId`, validated against the household (unknown, deleted or foreign → 400); backfill existing string values; `TaskAssignmentPolicy` uses `intervalDays`.
  - Route: delegated (writer). Trigger: 2+ non-trivial files across domain, application, infrastructure and a migration.
  - Commit: `3031265` (+1052/−255 including tests; one commit because the policy reads `Task::recurrenceId()`, so it is not cleanly separable).
  - Summary: `Task` holds `?int categoryId`/`recurrenceId` (the string fields are gone from domain, commands, handlers and repositories); `changeCategory()`/`changeRecurrence()` set them. `CatalogGuard` (like `AssigneeGuard`) rejects an unknown, soft-deleted or other-household entry with `InvalidArgumentException` (400) before anything changes; with no catalog repository wired, a non-null reference is rejected rather than trusted. PATCH: an absent `categoryId`/`recurrenceId` keeps the value, an explicit `null` or `""` clears it (`UpdateTaskCommand::clearCategory`/`clearRecurrence`). Responses return `categoryId`, `recurrenceId` and read-only `category`/`recurrence` names, resolved by `HouseholdCatalogs` with one read per catalog per request (no N+1). `TaskAssignmentPolicy::assign()` takes a `recurrenceId → intervalDays` map (built by `AutoAssignTasksHandler` from the household's recurrence types): weight = (7/N) × priority, cycle = +N days (lock replaced by T2b), null = one-off exactly as before; `FREQUENCIES`, `normalize()` and the calendar-month logic are removed. `Router` builds every `TaskController` with all repositories. SQLite schema has `category_id`/`recurrence_id` (an existing table gains them via `ALTER`); the legacy string columns are no longer written. Migration `007_task_catalog_refs.sql`.
  - Behavior changes (D2/D4/D6): a recurring task no longer keeps its assignee for a fixed cycle; per T2b it stays with its assignee until they complete it and may rotate from the next calendar day after completion (app timezone). Weights: "Mon & Thu"/"Tue & Fri" (3 days) weigh 7/3 instead of 2 per week; "Monthly" (30 days) weighs 7/30 instead of 0.25; Daily, Weekly (7) and Biweekly (14) weights are unchanged. A task pointing at a recurrence type that can no longer be resolved (soft-deleted) is still recurring and weighs as every 7 days, like an unknown string before.
  - Deploy notes: apply `006` then `007` on MariaDB before deploying this backend (the backend writes `category_id`/`recurrence_id`). `007` was written for MariaDB 11.8 but not run locally (no MariaDB available); dry-run it on a copy of the production database first. The legacy `tasks.category`/`tasks.recurrence` columns stay, frozen at their pre-migration values, for rollback to the previous backend; tasks edited after the deploy will have stale strings there.
- [x] T2b — Rotation rule (D6, user 2026-10-24): the assignee keeps a recurring task until it is completed, and it becomes eligible to rotate the day after completion (e.g. a Mon & Fri task rotates on Tue and Sat). Replaces the "cycle = N days" lock from T2; the 7/N weight stays. If it is not completed on its day, it stays with the same assignee until they complete it (user, 2026-10-24). Review of T2 is deferred so one candidate covers T2 + T2b, since T2b rewrites the T2 lock.
  - Route: delegated (writer). Trigger: non-trivial policy change plus two test files.
  - Commit: `5daed26` (+134/−61, mostly tests).
  - Summary: `TaskAssignmentPolicy::cycleEnd()` is replaced by `rotatableFrom(Task, $now)`: null while the current assignment is not completed (`completedAt` null, or earlier than `lastAssignedAt`), otherwise the start of the calendar day after `completedAt` in `$now`'s timezone (the app timezone in production, since `now` comes from `new DateTimeImmutable()` under `date_default_timezone_set`). A recurring task is locked while `rotatableFrom` is null or still in the future; one-off tasks are unchanged (always locked to an assignee, completed ones skipped). The intervalDays map stays for the 7/N weight. Non-repeatable recurring tasks with status `completed` follow the same rule (they were already eligible regardless of status). Behavior changes: an assigned recurring task with no `lastAssignedAt` and no completion (legacy rows) now keeps its assignee instead of rotating; a completed one rotates from the next day. `AutoAssignTasksHandlerTest` now proves the interval through load balancing (weight) instead of the lock.
  - Review (T2 + T2b, base `cfdbe60`): assessed `high`; consent granted; 4-lens review approved and acknowledged (`review-940212cec4fa218a`). Reviewed boundary is now `c376c80`.
  - Advisory follow-ups (non-blocking):
    - **Deploy together:** the backend now ignores the legacy `category`/`recurrence` strings, so deploying it before T4 would save new tasks as one-off and lose category edits. Ship backend and frontend together after T4.
    - **Migration 007 unproved:** never run against MariaDB. Dry-run it on a copy of production before deploying.
    - **Re-run claim:** the 007 header said "safe to re-run"; fixed to "run once, do not re-run after the new backend is live".
    - **Completed non-repeatable recurring tasks:** they rotate once, then stay locked to the new assignee, because their status stays completed and no new `completedAt` arrives. Open product question.
- [ ] T3 — Frontend Settings tab: API clients, hooks, and CRUD UI for categories and recurrence types.
- [ ] T4 — Frontend tasks use catalogs: TaskModal, Dashboard filter/badges and TaskDetail read the household catalogs instead of hard-coded lists.

## Acceptance criteria

- A household can list, create, rename and delete categories and recurrence types; other households never see them.
- A task can only reference categories and recurrence types of its own household that are not deleted; otherwise HTTP 400.
- Renaming a catalog entry is reflected on its tasks.
- Auto-assignment uses the recurrence's interval for the weight (7/N per week); a recurring task keeps its assignee until completed and may rotate from the next day; tasks without recurrence stay one-off.
- Existing tasks keep their category and recurrence after migration.

## Known notes

- The MySQL migrations 001–005 never add `day`, `time`, `category`, `recurrence` or `priority` to `tasks`; only the SQLite schema in `PdoTaskRepository` has them. Verified on the server on 2026-10-24 (`SHOW CREATE TABLE tasks`, read-only, user-authorized SSH): they exist there, added by hand: `day varchar(3)`, `time varchar(8)`, `category varchar(64)`, `recurrence varchar(32)`, `priority varchar(8) NOT NULL DEFAULT 'med'`. T2's migration closes the drift with `ADD COLUMN IF NOT EXISTS`.

## Checks

- `composer test` (in `home-backend`)
- `npm run build`

## Progress

- 2026-10-24: exploration done; feature document created.
- 2026-10-24: T1 done (`da5738e`). RED: `CategoryTest::testItCreatesACategoryWithATrimmedNameAndOptionalColor` failed with `Error: Class "App\Domain\Tasks\Catalog\Category" not found` (likewise handler tests on missing `InMemoryCategoryRepository` and Pdo tests on missing `PdoCategoryRepository`). GREEN: `composer test` → OK (205 tests, 505 assertions); baseline was 150/390.
- 2026-10-24: T2 done (`3031265`). RED: `TaskCatalogReferencesTest::testItCreatesATaskReferencingCatalogEntriesOfItsHousehold` failed with `Error: Unknown named parameter $categoryRepository` (guard tests: `Class "App\Application\Tasks\CatalogGuard\CatalogGuard" not found`); `TaskAssignmentPolicyTest` failed with `TypeError: ...weightOf(): Argument #1 ($recurrence) must be of type ?string, int given`; `TaskControllerTest` new cases failed with `Unknown named parameter $categoryRepository`. GREEN: `composer test` → OK (228 tests, 566 assertions).
- 2026-10-24: T2b done (`5daed26`). RED: `TaskAssignmentPolicyTest` 7 failures, e.g. `testRecurringTaskStaysWithItsAssigneeWhileNotCompletedEvenDaysLater` failed with `Failed asserting that two arrays are identical` (expected `[]`, got `[1 => 2]`), likewise the completion-day, timezone, completion-at-assignment, completed non-repeatable, unresolved-recurrence and no-assignment-date cases. GREEN: `composer test` → OK (226 tests, 565 assertions); 12 obsolete N-day cycle cases removed, 10 added.

## Open notes from T2

- Deleting a catalog entry does not touch its tasks: they keep the id, their `category`/`recurrence` name resolves to `null`, and auto-assign treats a dangling recurrence as every 7 days. Re-sending that same id on PATCH is rejected (400), so T4 must send only changed fields or drop dangling ids.
- A household with no catalog rows still gets defaults only from the catalog GET endpoints; listing tasks does not seed.

## Open notes from T1

- Members do not actually return 404: `Member not found.` is a plain `RuntimeException`, which `public/index.php` maps to 500, and member PATCH/DELETE do not check the household. Catalogs use a dedicated exception mapped to 404 instead; members were left unchanged.
- PATCH replaces the whole entry (name required; for categories an absent `color` clears it), like members. T3 must send every field.
- Seeding is check-then-insert: two concurrent first lists for the same household could seed twice. Duplicate-name checks have the same race. Acceptable for a household app; a lock or unique key would be needed to close it.

## Next step

- T3. Before deploy: dry-run 006 + 007 on a production copy; deploy backend and frontend together after T4.
