# Feature: home-server-deploy

## Objective
Run the HomeApp backend (`home-backend/`, branch `main`) and its MariaDB data on the LAN server `marcos@192.168.0.236` (host "Artur", Debian 13), reachable only from the LAN.

## Problem
The backend and database only run on the developer PC (XAMPP MariaDB 10.4.32). The server has MariaDB 11.8.6 (empty `home` database expected) but no PHP, web server, composer or git.

## Why
Requested by the user (2026-10-17/18): the home server becomes the always-on backend host.

## Scope
- Server packages: nginx, php8.4-fpm, php8.4-mysql, composer.
- Dedicated app DB user with privileges only on `home`.
- Copy data from the local XAMPP `home` database to the server.
- Deploy `main` via `git archive` (no git credentials on the server), `.env` outside the repo, nginx site + php-fpm, LAN-only.
- Smoke test the API from the PC.

## Constraints
- `sudo` needs a password: every privileged step is a command block the user runs themselves (decided 2026-10-19; temporary NOPASSWD sudo rejected).
- Credentials never go into the repo, chat or memory; they live in the server `.env` (mode 600).
- Claude Code auto mode blocks Claude from changing account access files (e.g. `authorized_keys`); the user applies those.
- Server has ~600 MB RAM free: keep php-fpm pool and nginx workers small.
- API stays LAN-only (192.168.0.0/24). External access only via VPN later.

## Decisions
- Deploy branch: `main` (all `feat/*` branches are merged; `main-prod` is 17 commits behind and unused).
- Data: copy existing local data (user, 2026-10-19), not migrations-only.
- SSH hardened 2026-10-19: key passphrase + Windows ssh-agent; `authorized_keys` restricted with `from="192.168.0.0/24"`.

- Frontend is served from the server too (user, 2026-10-19): static `dist/` via nginx, same-origin `/api`.

## Tasks
- [x] T1 — Install server packages (user runs sudo block). Route: inline. Evidence (2026-10-19): PHP 8.4.26 CLI with `pdo_mysql` and `mbstring`; Composer 2.8.8; `nginx` and `php8.4-fpm` installed and enabled but not started (started in T4 after config); only ports 22 and 3306 listening; 559 MB RAM available.
- [x] T2 — Create app DB user on the server; confirm `home` is empty (user runs sudo block). Route: inline. Evidence (2026-10-19): `home` exists with 0 tables; `homeapp@localhost` created with a random password generated on the server (stored only in `~/homeapp/.env.db` and `~/.my.cnf`, both mode 600); grants verified: SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, REFERENCES, INDEX, ALTER, LOCK TABLES on `home`.*. A pasted GRANT line got truncated by the terminal; re-run as a shorter single line.
- [x] T3 — Dump local XAMPP `home` (MariaDB 10.4) and import on the server (11.8); verify row counts match. Route: inline. Evidence (2026-10-19): `mysqldump --single-transaction --no-tablespaces` (10.8 KB, 3 tables); copied via scp, imported as `homeapp`; row counts match local and server: members 5, tasks 25, alerts 16. Dump deleted on both sides. Note: local XAMPP MariaDB needed crash recovery on start (previous unclean shutdown); recovered without errors.
- [x] T4 — Deploy `main` to the server with the frontend served too, `.env` outside the web root, composer autoload, nginx + php-fpm site, LAN-only. Route: inline (small, already-understood config files; user ran sudo parts). Commit `f6a78ee` adds `deploy/nginx/homeapp.conf` (static `web/` at `/`, PHP at `/api/`, allow 192.168.0.0/24 only), `deploy/php-fpm/homeapp.conf` (ondemand pool, max 3 children, 64M) and `deploy/deploy.sh` (builds with `VITE_API_BASE_URL=/api`, ships `git archive HEAD:home-backend` + `dist`, swaps dirs). Layout: `/srv/homeapp/{backend,web,shared}` owned by `marcos`, group `www-data`, setgid; `shared/.env` mode 640, symlinked into `backend/.env`. Default nginx site and `www` fpm pool disabled. Evidence (2026-10-19): `nginx -t` and `php-fpm8.4 -t` OK; `nginx` and `php8.4-fpm` active; `deploy/deploy.sh` printed `Deployed to /srv/homeapp`. RDD: commit review high risk, consent granted, 4 lenses approved with 15 non-blocking findings; branch review (base `d4ad52b`) high risk, consent granted, approved with 14 non-blocking findings. Both acknowledged.
- [x] T5 — Smoke test API from the PC against the server. Route: inline. Evidence (2026-10-19): `GET /` 200 (index.html); `GET /api/members|tasks|alerts?householdId=hh_001` 200 with 5 members, 25 tasks, 14 alerts (the 14 unread; server and local both hold 14 unread + 2 dismissed); `GET /api/nope` 404 JSON; `GET /.env` serves the SPA index (no secret exposure); 554 MB RAM available after deploy.
- [x] T5b — Fix: deployed bundle had `apiBaseUrl` = `C:/Program Files/Git/api` (Git Bash MSYS path conversion of `VITE_API_BASE_URL=/api` passed to Windows `pnpm`), so the UI showed no data although the API worked. T5 missed it because it only curled the API. Fix in `deploy/deploy.sh`: `MSYS2_ENV_CONV_EXCL=VITE_API_BASE_URL` plus an abort if `dist/` contains `Program Files`. Route: inline (one mechanical file). Evidence (2026-10-19): commit `23bfc3a` redeployed; served bundle `index-Bn-27_xV.js` has `apiBaseUrl` `/api` and `householdId` `hh_001`. The first guard used `rg` (not on the script PATH, so it never ran); switched to `grep -r` and verified it aborts on a dist containing `Program Files`. RDD: branch review (base `d4ad52b`) high risk, consent granted, 4 lenses approved with 16 non-blocking findings (new: guard only catches the `Program Files` prefix, R3-msys-guard-narrow), acknowledged.
- [ ] T6 — Follow-up: harden `deploy/deploy.sh` from the review advisories (check `shared/.env` exists before swapping, `mkdir -p` the remote staging dir, keep the previous release until the swap succeeds for rollback, clean remote tarballs on failure). Route: inline.

## Acceptance criteria
- `curl` from the PC to the server API returns data matching the local database.
- Row counts per table match between local and server.
- API is not reachable from outside 192.168.0.0/24.
- No credentials in the repo or chat.

## Checks
- Row counts per table (local vs server).
- HTTP smoke test of a read endpoint.
- Test-first exception: infrastructure/deploy work with no runnable deterministic test; functional checks above instead.

## Progress
- 2026-10-19: document created. Local XAMPP MariaDB is stopped (needs to be started for T3). Server DB needs credentials (no `~/.my.cnf`, `marcos`@localhost denied).

## Next step
App live at http://192.168.0.236/ (LAN only). Next: T6 deploy script hardening when the user wants it. Security follow-ups outside the repo: remove the "Server Credentials" section from the local `home-backend/.env` and rotate the sudo password; Defender offline scan.
