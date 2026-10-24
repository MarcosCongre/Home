# Feature: task-catalogs

**Status:** T1–T4 done, reviewed, deployed to the home server and pushed; PRs pending (user)
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
- [x] T3 — Frontend Settings tab: API clients, hooks, and CRUD UI for categories and recurrence types.
  - Route: delegated (writer). Trigger: 2+ non-trivial files across domain, application, infrastructure and presentation.
  - Commit: `03f620c` (+941/−2; over the ~400-line heuristic because the screen with two modals, validation and delete flow is one coherent unit; about 520 lines are `SettingsScreen.tsx`).
  - Summary: `src/domain/catalogs/catalog.ts` holds the `Category`/`RecurrenceType` types and the backend's limits (name 1..64 code points, interval 1..365, optional `#RRGGBB`). `categoryApi.ts`/`recurrenceTypeApi.ts` follow `memberApi.ts`; PATCH and DELETE send `householdId` in the query (PATCH also in the body) and PATCH always sends the full entry (`color: null` clears it). `useCatalog` is a generic `useReducer` hook (load/add/update/remove) wrapped by `useCategories`/`useRecurrenceTypes`; `App` owns both so T4 can reuse them. In demo mode (`VITE_DEMO_MODE=true`) the hooks never call the API: they start from `DEMO_CATEGORIES`/`DEMO_RECURRENCE_TYPES` (built in `demoData.ts` from `CATEGORIES`/`RECURRENCES`, matching `DefaultCatalogs`) and apply the same case-insensitive duplicate rule in memory. New `settings` tab (icon `⚙`, label "Configuración") renders `SettingsScreen`: two sections (Categorías with a color swatch, Recurrencias with "Cada N días"), a bottom-sheet/centered modal for add/edit with inline validation, an inline message for 400 (duplicate or invalid) and 404, and `ConfirmDialog` with `pending`/`error` for deletes (a 404 on delete closes the dialog and reloads). Default names are stored in English and shown through `categoryLabel`/`recurrenceLabel`; the edit form shows the translated label and sends the stored name back if it is left unchanged, so opening and saving does not rename an entry. Bottom-nav labels now truncate so five tabs fit at phone width. Tasks still use the hard-coded lists (T4).
  - Test policy: no frontend test runner exists, so test-first does not apply; verified with `npx tsc --noEmit` and `npm run build`.
  - Manual check: with the backend running, open Configuración → both lists show the seeded defaults; add a category with a custom color, rename it, try a duplicate name in another case (inline 400 message), delete it through the dialog; add a recurrence "Cada 10 días", try 0 and 366 (inline validation); reload and confirm the changes persisted. With `VITE_DEMO_MODE=true`, repeat without a backend (changes live in memory until reload).
  - Formatter note: `npm run format` (oxfmt 0.2) rewrote all 31 files to double quotes and produced code that does not compile (`{ id: number name: string }`, missing separators). Its output was discarded; the repo's existing style was kept by hand.
  - Review: assessed medium (slice_budget_reached) vs c376c80; consent granted; reliability review approved and acknowledged (review-8467c4336fed80bb). Reviewed boundary is now eaf8d00. Advisory: delete dialog state race (SettingsScreen.tsx:71-95); a load can overwrite local mutations (useCatalog.ts:100-108); pure catalog rules unproved by tests (no runner).
- [x] T4 — Frontend tasks use catalogs: TaskModal, Dashboard filter/badges and TaskDetail read the household catalogs instead of hard-coded lists.
  - Route: delegated (writer). Trigger: 2+ non-trivial files across domain, application, infrastructure and presentation.
  - Commit: `cc56370` (+316/−178).
  - Summary: `Task` holds `categoryId`/`recurrenceId` (`number | null`) plus the API-resolved `category`/`recurrence` names (`string | null`); `taskMapper` reads the ids (non-positive or missing → null) and no longer invents 'Uncategorized'/'Once'. `taskChanges(original, edited)` in `src/domain/tasks/task.ts` keeps only fields that differ; `useTasks.updateTask(taskId, changes)` diffs against the stored task, skips the request when nothing changed, and `taskApi.updateTask` builds the PATCH body from present keys only, so an unchanged (possibly dangling) catalog id is never re-sent and an untouched assignee is no longer re-sent either. `createTask` sends `categoryId`/`recurrenceId` (null allowed); the legacy strings are gone from both requests. `src/presentation/components/taskCatalogDisplay.ts` derives display text (catalog name by id → API name → "Sin categoría"/"Una vez", translated through `categoryLabel`/`recurrenceLabel`, custom names as-is) and the badge color (catalog `color`, else neutral `#888`). TaskModal pickers list the household catalogs plus "Sin categoría"/"Una vez"; a new task defaults to no category and one-off; a dangling id highlights the "none" chip and is only sent if the user changes it. Dashboard filter: 'All' sentinel plus the household categories (horizontal scroll on mobile, wraps on desktop; a filter whose category disappears falls back to 'All'). Calendar, Members and TaskDetail badges use the same helpers. TaskDetail now persists through `updateTask` (before it only edited local state), with a saving state and an inline error; its recurrence picker uses the catalog plus "Una vez", so changing only the recurrence sends only `recurrenceId`. Demo: `DEMO_CATEGORIES`/`DEMO_RECURRENCE_TYPES` are explicit literals (same ids as before) and `INIT_TASKS` reference them; in demo mode create/update apply in memory. Removed `CATEGORIES`, `RECURRENCES`, `categoryColor` and the unused `Uncategorized`/`Once`/`none` labels.
  - Test policy: no frontend test runner exists, so test-first does not apply; verified with `npx tsc --noEmit` and `npm run build`.
  - Manual check: with the new backend running, open Inicio → the filter shows "Todas" plus the household categories and badges use their colors; Calendario → "+" → the pickers show "Sin categoría"/"Una vez" selected; create a task with a category and a recurrence, reload, confirm both persisted. Edit it and change only the title: the PATCH body (devtools) has no `categoryId`/`recurrenceId`. In Configuración delete that category: the task shows "Sin categoría" with the neutral color, and editing its title still saves (no 400). Open the task detail, change only the recurrence and save: the PATCH body is `{"recurrenceId": N}`. Rename a category in Configuración: task badges show the new name. With `VITE_DEMO_MODE=true`, repeat create/edit without a backend.
  - Not changed: demo-mode delete still calls the API (pre-existing).

## Acceptance criteria

- A household can list, create, rename and delete categories and recurrence types; other households never see them.
- A task can only reference categories and recurrence types of its own household that are not deleted; otherwise HTTP 400.
- Renaming a catalog entry is reflected on its tasks.
- Auto-assignment uses the recurrence's interval for the weight (7/N per week); a recurring task keeps its assignee until completed and may rotate from the next day; tasks without recurrence stay one-off.
- Existing tasks keep their category and recurrence after migration.

## Known notes

- `npm run format` (oxfmt ^0.2) rewrites to double quotes and outputs uncompilable TS; do not run it until fixed.
- Migrations 006 and 007 were applied on the server by the user on 2026-10-24; a read-only check showed hh_001 with 8 categories and 6 recurrence types, and 0 of 25 tasks missing ids. The deployed backend is still the old one, so re-run only the 007 backfill right before deploying.
- The MySQL migrations 001–005 never add `day`, `time`, `category`, `recurrence` or `priority` to `tasks`; only the SQLite schema in `PdoTaskRepository` has them. Verified on the server on 2026-10-24 (`SHOW CREATE TABLE tasks`, read-only, user-authorized SSH): they exist there, added by hand: `day varchar(3)`, `time varchar(8)`, `category varchar(64)`, `recurrence varchar(32)`, `priority varchar(8) NOT NULL DEFAULT 'med'`. T2's migration closes the drift with `ADD COLUMN IF NOT EXISTS`.

## Checks

- `composer test` (in `home-backend`)
- `npm run build`

## Progress

- 2026-10-24: exploration done; feature document created.
- 2026-10-24: T1 done (`da5738e`). RED: `CategoryTest::testItCreatesACategoryWithATrimmedNameAndOptionalColor` failed with `Error: Class "App\Domain\Tasks\Catalog\Category" not found` (likewise handler tests on missing `InMemoryCategoryRepository` and Pdo tests on missing `PdoCategoryRepository`). GREEN: `composer test` → OK (205 tests, 505 assertions); baseline was 150/390.
- 2026-10-24: T2 done (`3031265`). RED: `TaskCatalogReferencesTest::testItCreatesATaskReferencingCatalogEntriesOfItsHousehold` failed with `Error: Unknown named parameter $categoryRepository` (guard tests: `Class "App\Application\Tasks\CatalogGuard\CatalogGuard" not found`); `TaskAssignmentPolicyTest` failed with `TypeError: ...weightOf(): Argument #1 ($recurrence) must be of type ?string, int given`; `TaskControllerTest` new cases failed with `Unknown named parameter $categoryRepository`. GREEN: `composer test` → OK (228 tests, 566 assertions).
- 2026-10-24: T2b done (`5daed26`). RED: `TaskAssignmentPolicyTest` 7 failures, e.g. `testRecurringTaskStaysWithItsAssigneeWhileNotCompletedEvenDaysLater` failed with `Failed asserting that two arrays are identical` (expected `[]`, got `[1 => 2]`), likewise the completion-day, timezone, completion-at-assignment, completed non-repeatable, unresolved-recurrence and no-assignment-date cases. GREEN: `composer test` → OK (226 tests, 565 assertions); 12 obsolete N-day cycle cases removed, 10 added.
- 2026-10-24: T3 done (`03f620c`). No frontend test runner, so no RED/GREEN (exception recorded on T3). `npx tsc --noEmit` → exit 0, no errors; `npm run build` → built in 1.01s (288.08 kB JS). `npm run format` was run and reverted because its output did not compile.
- 2026-10-24: T4 done (`cc56370`). No frontend test runner, so no RED/GREEN (exception recorded on T4). `npx tsc --noEmit` → exit 0, no errors; `npm run build` → built in 1.68s (291.25 kB JS).

## Open notes from T2

- Deleting a catalog entry does not touch its tasks: they keep the id, their `category`/`recurrence` name resolves to `null`, and auto-assign treats a dangling recurrence as every 7 days. Re-sending that same id on PATCH is rejected (400), so T4 must send only changed fields or drop dangling ids.
- A household with no catalog rows still gets defaults only from the catalog GET endpoints; listing tasks does not seed.

## Open notes from T1

- Members do not actually return 404: `Member not found.` is a plain `RuntimeException`, which `public/index.php` maps to 500, and member PATCH/DELETE do not check the household. Catalogs use a dedicated exception mapped to 404 instead; members were left unchanged.
- PATCH replaces the whole entry (name required; for categories an absent `color` clears it), like members. T3 must send every field.
- Seeding is check-then-insert: two concurrent first lists for the same household could seed twice. Duplicate-name checks have the same race. Acceptable for a household app; a lock or unique key would be needed to close it.

## Next step

- User: open the stacked PRs (`feat/task-delete-confirmation` → `main`, `feat/repeatable-tasks` → `feat/task-delete-confirmation`, `feat/task-catalogs` → `feat/repeatable-tasks`) and check the app manually.
- Open product questions: completed non-repeatable recurring tasks stay locked after one rotation; tasks keep dangling ids when a catalog entry is deleted; `ApiError` does not carry backend messages; `npm run format` is broken.

## Delivery

- 2026-10-24: T4 review: assessed medium (`slice_budget_reached`) vs `eaf8d00`; consent granted; reliability review approved and acknowledged. Advisory: R3-001 (`useTasks.ts:176-181`), R3-002 (`useTasks.ts:170-171`), R3-003 (`task.ts:37-47`).
- 2026-10-24: 007 re-run on the server right before the deploy: 25 tasks, 0 missing ids; 8 categories, 6 recurrence types.
- 2026-10-24: `deploy/deploy.sh` deployed `9817d69`. Smoke test: home page 200; `/api/categories` and `/api/recurrence-types` return the `hh_001` catalogs; `/api/tasks` returns 24 live tasks with `categoryId`/`recurrenceId` and names.
- 2026-10-24: pushed `feat/task-delete-confirmation` (`6880b76`), `feat/repeatable-tasks` (`acb86d2`) and `feat/task-catalogs` to origin (user-authorized).
