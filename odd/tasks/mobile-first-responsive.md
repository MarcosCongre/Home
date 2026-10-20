# Mobile-first responsive layout

## Objective

Make the frontend a real mobile-first, responsive web app instead of a simulated phone mockup.

## Problem

`src/App.tsx` renders a fixed 375x780 phone frame with a fake status bar (time, WiFi, battery).
On a real phone this duplicates the device status bar; on desktop the app stays a small phone in the middle of the screen.

## Scope

- Phase 1 (T1): app shell — remove fake status bar and phone frame, full-viewport layout, safe-area insets,
  bottom nav on mobile, sidebar nav on desktop, modals as fixed overlays (bottom sheet on mobile, centered dialog on desktop).
- Phase 2 (T2): screens — responsive layouts inside each screen (grids on wider screens, readable font sizes, touch targets).
- Phase 3 (T3): polish — meta tags, final breakpoint verification (360, 768, 1280 px).

## Constraints

- Tailwind v4 utilities, no new dependencies.
- No frontend test runner exists: test-first exception; checks are `tsc --noEmit`, `vite build`, and visual verification.

## Delivery

- Strategy: ask-on-risk. Forecast: ~250 authored changed lines across all phases.
- Branch: `feat/mobile-first-responsive` (from `feat/task-auto-assign`).

## Tasks

- [x] T1 — App shell (route: inline; App.tsx + mechanical overlay class changes in 2 screen files)
- [x] T2 — Responsive screens (route: delegated writer; preparation trigger: full read of 806-line homeScreens.tsx). Includes R3-002 fix (error toast above modal overlays).
- [x] T3 — Polish and breakpoint verification. Scope from review `review-d3dd4f510e3995a7` (approved, acknowledged; T1+T2 range): add env(safe-area-inset-left/right) for landscape notched phones; make assignee button rows wrap in TaskDetail/TaskModal (360px and md grid cell); record visual check at 360/768/1280.

## Progress / evidence

- T1: done in `67310e7`. Checks: `tsc --noEmit` exit 0, `vite build` OK. Visual check at 360/1280 px pending (user). RDD: medium, lens review-reliability approved and acknowledged (lineage `review-724a482ceb4a874a`); advisory R3-002 (error toast hidden under modal overlays) scheduled for T2. Deployed `8d2c073` with `deploy/deploy.sh` (2026-10-20): `GET /` 200 serving `index-ov46KEGQ.js` with `viewport-fit=cover`, `GET /api/tasks` 200.
- T2: delegated writer. Grids md:2 / xl:3 cols (dashboard, calendar, members, notifications), TaskDetail 2 cols on md, 44px touch targets, min text 11px (no text-[9px]/[10px] left), scrollable day/filter chips on mobile, main wrapper max-w-5xl, R3-002 fixed (toast z-[60]). Checks (parent re-run): `tsc --noEmit` exit 0, `vite build` OK. Open risk for T3: assignee button rows in TaskDetail/TaskModal do not wrap (many members may overflow at 360px).
- T2 deploy: `6c95273` via `deploy/deploy.sh` (2026-10-20): `GET /` 200 serving `index-DPWPis6c.js`, `GET /api/tasks` 200. Visual check by user pending.
- T3: inline. Root shell pads env(safe-area-inset-left/right); assignee rows in TaskDetail/TaskModal use flex-wrap with basis-20 min-w-0 buttons and truncated names. Also fixed pre-existing "NaN% completado" on Dashboard/Members when there are no tasks (division by zero; found during visual check). Checks: `tsc --noEmit` exit 0, `vite build` OK. Visual (headless Chrome on `vite preview`): 1280 px sidebar + 3 stat cards OK; 768 px sidebar, chips wrap; 360 px (iframe, headless clamps windows below ~500 px) bottom nav with 4 tabs, 3 stat cards fit. Same-origin probe: 360 -> scroll 360x780, md=false; 768 -> scroll 768x780, md=true (no horizontal overflow). Not covered: modals and populated lists (no local API data).
- T3 RDD: medium, lens review-reliability approved and acknowledged (lineage `review-249263fc548e5c88`). Advisory: R3-001 modal overlays (sheet scroll, backdrop, toast above) not visually verified -> user checks on server; R3-002 `h-dvh` has no fallback for iOS Safari < 15.4 (optional `h-screen` fallback).

## Next step

T1 approved visually by the user (2026-10-20). T1-T3 done. Next: user decides deploy of T3 and PR/merge.
