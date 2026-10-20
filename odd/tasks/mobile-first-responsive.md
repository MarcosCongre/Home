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
- [ ] T2 — Responsive screens (route: TBD after T1)
- [ ] T3 — Polish and breakpoint verification

## Progress / evidence

- T1: done in `67310e7`. Checks: `tsc --noEmit` exit 0, `vite build` OK. Visual check at 360/1280 px pending (user). RDD: medium, lens review-reliability approved and acknowledged (lineage `review-724a482ceb4a874a`); advisory R3-002 (error toast hidden under modal overlays) scheduled for T2. Deployed `8d2c073` with `deploy/deploy.sh` (2026-10-20): `GET /` 200 serving `index-ov46KEGQ.js` with `viewport-fit=cover`, `GET /api/tasks` 200.

## Next step

User validates T1 visually, then T2 (responsive screens: grids on md+, font sizes, touch targets).
