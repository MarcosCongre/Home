# Feature: household-alert-scoping

## Objective
Prevent cross-household access in alerts: an alert can only be dismissed within its own household, and alert serialization only resolves members of the alert's household.

## Problem
- `PATCH /alerts/{id}/dismiss` (`Router.php:76-80`) dismisses any alert by id, regardless of household (`PdoAlertRepository::dismiss` updates `WHERE id = :id`).
- `AlertController::serializeAlert` (`AlertController.php:55-57`) resolves `memberId` with `findById` and exposes the member name without checking that the member belongs to the alert's household.

## Why
Queued by the user as pending task 1 after `ui-spanish-locale` (2026-10-09).

## Scope
- Backend (`home-backend/`): household-scoped dismiss (repository, command, handler, controller, router) and household-checked member lookup in alert serialization, with tests.
- Frontend: `src/infrastructure/http/alertApi.ts` sends `householdId` in the dismiss request body.

## Constraints
- There is no authentication: `householdId` is client-supplied. This change scopes operations to the declared household (defense in depth); it does not prove the caller belongs to it. Real authorization needs auth (out of scope).
- Dismissing an alert that is not in the given household responds 404 (no existence leak). Missing/empty `householdId` responds 400.
- Re-dismissing an already dismissed alert of the same household stays successful (idempotent). Do not rely on MySQL `rowCount()` for matched rows.

## Out of scope (still queued)
- Same cross-household pattern in tasks (`DELETE /tasks/{id}`, `PATCH /tasks/{id}/complete`) and members (`PATCH/DELETE /members/{id}`).
- Avatar lookup by initial, "Miembro #%d" leak, NaN% dashboard bug.

## Tasks
- [x] T1 — Household-scoped alert dismiss + household-checked member lookup in alert serialization; frontend sends `householdId`. Route: delegated (writer; 2+ non-trivial backend files + tests + 1 frontend file). RED: `composer test` 51 tests, 5 failures (e.g. `Expected AlertNotFoundException.`, `Failed asserting that 'Intruder' is null.`, `Failed asserting that null is false.`). GREEN: `composer test` OK (51 tests, 189 assertions); `npx tsc --noEmit -p .` exit 0. Parent re-run `composer test` OK (51/189). Commit `09e1840`. RDD assess (`--base-ref 8a3d9e0 --committed-only`): medium (`executable_change` in `public/index.php`), 271 lines, `review_due: false` (`under_budget`).

## Acceptance criteria
- Dismissing an alert with another household's id → 404 and the alert stays unread.
- Dismissing with the correct household → 200, alert dismissed; repeat → 200.
- Missing `householdId` on dismiss → 400.
- Alert whose `memberId` points to a member of another household serializes `user: null`.
- `composer test` passes; `pnpm build` passes.

## Checks
- Backend: `composer test` in `home-backend/`. Test-first: RED on the new cross-household tests before implementation.
- Frontend: `npx tsc --noEmit -p .` (no frontend test runner; test-first exception).

## Delivery
- Branch: `feat/household-alert-scoping`, created from `feat/ui-spanish-locale` (`8a3d9e0`, unpushed chain).
- Forecast: ~150 authored changed lines. Strategy: `ask-on-risk` (under budget).
- RDD: on (global).

## Progress
- 2026-10-09: Feature document created after read-only exploration.
- 2026-10-09: T1 implemented: `AlertRepositoryInterface::dismiss(int, string): bool` (existence checked via SELECT, not rowCount), new `Domain\Alerts\AlertNotFoundException` mapped to 404 in `public/index.php`, Router requires body `householdId` (400 when missing), serializer drops members of other households, frontend sends `householdId`.

- 2026-10-09: RDD review lineage `review-0fa48ce33be95224` (base `88d363b`, 40 files, 1111 lines, high, consent granted) resumed via bound STATUS; 4 lenses captured sequentially (risk, resilience, readability, reliability), all `completed`. Terminal state `approved`, no blockers (6 WARNING / 20 SUGGESTION, all informational). Acknowledged: authority `burned` (consumed revision `sha256:4dc7929a…5715e`).

## Review follow-ups (informational, non-blocking)
- R4: `public/index.php:58-61` sets MySQL session `time_zone` to a fixed numeric offset from "now", while PHP reads values with the named zone; DST zones (e.g. Europe/Madrid) can shift values across a DST boundary.
- R2: `src/infrastructure/config/environment.ts:8` flips demo mode to opt-in (`VITE_DEMO_MODE === 'true'`); undocumented behavior change.
- R3: `public/index.php:68-73` `API_BASE_PATH` prefix stripping is untested (exact base, prefixed path, prefix-only lookalike).

## Next step
User: decide push/PR. Follow-ups: review warnings above; same household scoping for task/member endpoints; `dismiss-all` accepts empty `householdId`; no auth exists (householdId is client-supplied).
