# Feature: task-catalogs

**Status:** T1 done, T2 next
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
- [ ] T2 — Backend tasks use catalogs: tasks reference `categoryId`/`recurrenceId`, validated against the household (unknown, deleted or foreign → 400); backfill existing string values; `TaskAssignmentPolicy` uses `intervalDays`.
- [ ] T3 — Frontend Settings tab: API clients, hooks, and CRUD UI for categories and recurrence types.
- [ ] T4 — Frontend tasks use catalogs: TaskModal, Dashboard filter/badges and TaskDetail read the household catalogs instead of hard-coded lists.

## Acceptance criteria

- A household can list, create, rename and delete categories and recurrence types; other households never see them.
- A task can only reference categories and recurrence types of its own household that are not deleted; otherwise HTTP 400.
- Renaming a catalog entry is reflected on its tasks.
- Auto-assignment uses the recurrence's interval: weight uses 7/N per week, the assignee keeps the task for N days, tasks without recurrence stay one-off.
- Existing tasks keep their category and recurrence after migration.

## Known notes

- The MySQL migrations 001–005 never add `day`, `time`, `category`, `recurrence` or `priority` to `tasks`; only the SQLite schema in `PdoTaskRepository` has them. The server works, so they were probably added by hand. Verify before writing the backfill in `006`.

## Checks

- `composer test` (in `home-backend`)
- `npm run build`

## Progress

- 2026-10-24: exploration done; feature document created.
- 2026-10-24: T1 done (`da5738e`). RED: `CategoryTest::testItCreatesACategoryWithATrimmedNameAndOptionalColor` failed with `Error: Class "App\Domain\Tasks\Catalog\Category" not found` (likewise handler tests on missing `InMemoryCategoryRepository` and Pdo tests on missing `PdoCategoryRepository`). GREEN: `composer test` → OK (205 tests, 505 assertions); baseline was 150/390.

## Open notes from T1

- Members do not actually return 404: `Member not found.` is a plain `RuntimeException`, which `public/index.php` maps to 500, and member PATCH/DELETE do not check the household. Catalogs use a dedicated exception mapped to 404 instead; members were left unchanged.
- PATCH replaces the whole entry (name required; for categories an absent `color` clears it), like members. T3 must send every field.
- Seeding is check-then-insert: two concurrent first lists for the same household could seed twice. Duplicate-name checks have the same race. Acceptable for a household app; a lock or unique key would be needed to close it.

## Next step

- T2.
