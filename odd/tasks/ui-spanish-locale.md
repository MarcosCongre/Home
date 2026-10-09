# Feature: ui-spanish-locale

## Objective
Move the entire user-facing UI to Spanish and show alert times consistently as `h:i A` in the `America/Argentina/Buenos_Aires` timezone.

## Problem
- UI copy is English while backend-generated alert texts are Spanish ("Nueva tarea asignada", "Tarea completada").
- Demo alerts use relative times ("2 min ago") while the API returns `h:i A`.
- The backend configures no timezone (PHP, Docker, MySQL session), so displayed times are UTC.

## Why
Product decision by the user on 2026-10-09 (pending task #5 resolved): Spanish UI, `h:i A` time format, Buenos Aires timezone.

## Scope
- Backend (`home-backend/`): configurable app timezone defaulting to `America/Argentina/Buenos_Aires`; MySQL session aligned; keep `h:i A` in `AlertController`.
- Frontend (`src/`, `index.html`): Spanish copy everywhere, display-only label maps for days, categories, recurrences and priorities, demo data in Spanish with `h:i A` alert times.

## Constraints
- DAYS, CATEGORIES and RECURRENCES are stored values sent to the API (`TaskController.php:148` accepts only `Mon`…`Sun`). Translate them for display only; never change the values.
- `h:i A` is kept literally as requested: zero-padded hour and English `AM`/`PM`.
- No i18n library; strings live in a plain constants module.
- No frontend test runner exists; frontend checks are `pnpm build` plus visual run.

## Out of scope (next feature, queued by user)
- Cross-household alert dismiss (`Router.php:76-80`) and member lookup without household check (`AlertController.php:55-67`).
- Avatar never resolving: API returns full name, UI matches by initial (`homeScreens.tsx` Notifications).
- Alert body leaks internal member ID ("Miembro #%d" in `CreateTaskHandler.php:60`).

## Tasks
- [x] T1 — Backend timezone: `APP_TIMEZONE` config (default `America/Argentina/Buenos_Aires`) applied in `public/index.php`, PDO session `time_zone` aligned, Dockerfile/docker-compose updated, tests adjusted. Route: delegated (writer, 3+ backend files + tests). Commit `13c210f`. RED: 4 errors + `'08:30 AM'` expected vs `'11:30 AM'`; GREEN: `composer test` OK (44 tests, 168 assertions), parent re-run confirmed. Invalid `APP_TIMEZONE` falls back to default with `error_log` (display-only concern, no 500s). `AlertController` converts `createdAt` with `setTimezone` because offset-carrying datetimes ignore the default zone. Not verified: MySQL `SET time_zone` path (no Docker/MySQL locally).
- [x] T2 — Spanish strings module + display label maps (days, categories, recurrences, priorities). Route: delegated (together with T3). `src/presentation/i18n/es.ts`: `labelFor` with raw-value fallback; priorities keyed `low`/`med`/`high`; labels for `Uncategorized`, `Once`, `none`; count-aware plural helpers.
- [x] T3 — Translate `App.tsx`, `homeScreens.tsx`, `TaskModal.tsx`, hook error messages, `taskMapper` fallbacks. Route: delegated (writer, 2+ non-trivial files). `taskMapper`/`apiClient` unchanged (fallbacks are values, labeled in `es.ts`; other messages internal). Spanish `aria-label`s added to icon-only buttons.
- [x] T4 — Demo data in Spanish; alert NOTIFS times as `h:i A`; hardcoded dates in Spanish. Route: delegated (with T3). Stored values (DAYS/CATEGORIES/RECURRENCES, `categoryColor` keys) unchanged.
- [x] T6 — Persist `created_at`/timestamps as app-local `Y-m-d H:i:s` instead of `DATE_ATOM`. Found by verification against local XAMPP MariaDB 10.4.32 (user-authorized): writing `2026-10-04T11:30:00+00:00` is truncated (warning 1265) and stored as `11:30` local, rendering `11:30 AM` instead of `08:30 AM`; local-format write renders correctly. Strict mode would reject it. Route: delegated (writer, repositories + tests). `DbDateTime::format` (Persistence) used by `PdoAlertRepository` and `PdoTaskRepository` (`created_at`, `completed_at`); `TaskController` keeps `DATE_ATOM` (API output, not a DB write). RED: 2 failures (`T11:30:00+00:00` vs `08:30:00`); GREEN: `composer test` OK (46 tests, 175 assertions), parent re-run confirmed. MariaDB probe (TEMPORARY table): helper write stores `08:30:00`, renders `08:30 AM`. Note: rows written before this fix in the real DB keep wrong times (not migrated).
- Slice 2 checks: test-first exception (no frontend runner). `pnpm build` pass (writer); `npx tsc --noEmit -p .` exit 0 (writer + parent re-run); leftover-English scan clean except identifiers/names. Visual check in the running app: pending (user).
- Follow-ups noticed (not fixed): `TaskModal` offers `'All'` ("Todas") as a selectable category (pre-existing); demo alert "Recolección de basura mañana" mentions Tuesday while dashboard date is Wednesday (pre-existing).
- [x] T5 — `index.html` `lang="es"` and real title. Route: inline (mechanical). `lang="es"`, title "Tareas del hogar" (Figma placeholders were never substituted locally). Commit `358c617`. Check: structural readback.

## Acceptance criteria
- `composer test` passes; alert `time` is rendered in Buenos Aires local time with `h:i A`.
- `pnpm build` passes; no English user-facing copy remains in the UI.
- Creating/updating tasks still sends `Mon`…`Sun` and the original category/recurrence values.

## Checks
- Backend: `composer test` (PHPUnit 11) in `home-backend/`. Test-first: RED on timezone expectation before implementation.
- Frontend: `pnpm build`; test-first exception: no runner exists. Visual check in the running app.

## Delivery
- Branch: `feat/ui-spanish-locale`, created from `feat/alerts` (which holds 6 unpushed commits not yet in `main`).
- Forecast: ~550 authored changed lines (exceeds the ~400 budget). Strategy: `ask-on-risk`; chain strategy chosen by user: `feature-branch-chain`.
- Slice 1: T1 + T5 (backend timezone, `index.html`). Slice 2: T2 + T3 + T4 (frontend Spanish copy).
- RDD: on (global).

## Progress
- 2026-10-09: Feature document created. Exploration done (read-only mapping).
- 2026-10-09: Slice 1 committed (`13c210f`, `358c617`). RDD assess: high (`process_boundary` in `public/index.php`); user granted review; lineage `review-e9735640b3a524ab`, 4 lenses running.
- 2026-10-09: Slice 1 review approved (4 lenses, 0 findings) and acknowledged (authority burned). Reviewed boundary: `358c617`. T2–T4 delegated to one writer.

- 2026-10-09: Slice 2 committed (`954ac7b`). RDD assess `--base-ref 358c617 --committed-only`: medium (`executable_change` in `src/App.tsx`), 378 lines, `review_due: false` (`under_budget`) — stays pending in the slice; no review run.

## Next step
User: visual check of the Spanish UI in the running app; decide push/PR (`feature-branch-chain`). Then next feature (queued): cross-household alert security, avatar lookup, "Miembro #%d" leak.
