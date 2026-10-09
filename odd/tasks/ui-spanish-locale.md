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
- [ ] T1 — Backend timezone: `APP_TIMEZONE` config (default `America/Argentina/Buenos_Aires`) applied in `public/index.php`, PDO session `time_zone` aligned, Dockerfile/docker-compose updated, tests adjusted. Route: delegated (writer, 3+ backend files + tests).
- [ ] T2 — Spanish strings module + display label maps (days, categories, recurrences, priorities). Route: delegated (together with T3).
- [ ] T3 — Translate `App.tsx`, `homeScreens.tsx`, `TaskModal.tsx`, hook error messages, `taskMapper` fallbacks. Route: delegated (writer, 2+ non-trivial files).
- [ ] T4 — Demo data in Spanish; alert NOTIFS times as `h:i A`; hardcoded dates in Spanish. Route: delegated (with T3).
- [ ] T5 — `index.html` `lang="es"` and real title. Route: inline (mechanical). Edited: `lang="es"`, title "Tareas del hogar" (Figma placeholders were never substituted locally); pending commit.

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

## Next step
T1 backend timezone.
