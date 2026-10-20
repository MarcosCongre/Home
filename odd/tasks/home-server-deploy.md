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

## Open questions
- Should the frontend also be served from the server?

## Tasks
- [x] T1 — Install server packages (user runs sudo block). Route: inline. Evidence (2026-10-19): PHP 8.4.26 CLI with `pdo_mysql` and `mbstring`; Composer 2.8.8; `nginx` and `php8.4-fpm` installed and enabled but not started (started in T4 after config); only ports 22 and 3306 listening; 559 MB RAM available.
- [x] T2 — Create app DB user on the server; confirm `home` is empty (user runs sudo block). Route: inline. Evidence (2026-10-19): `home` exists with 0 tables; `homeapp@localhost` created with a random password generated on the server (stored only in `~/homeapp/.env.db` and `~/.my.cnf`, both mode 600); grants verified: SELECT, INSERT, UPDATE, DELETE, CREATE, DROP, REFERENCES, INDEX, ALTER, LOCK TABLES on `home`.*. A pasted GRANT line got truncated by the terminal; re-run as a shorter single line.
- [x] T3 — Dump local XAMPP `home` (MariaDB 10.4) and import on the server (11.8); verify row counts match. Route: inline. Evidence (2026-10-19): `mysqldump --single-transaction --no-tablespaces` (10.8 KB, 3 tables); copied via scp, imported as `homeapp`; row counts match local and server: members 5, tasks 25, alerts 16. Dump deleted on both sides. Note: local XAMPP MariaDB needed crash recovery on start (previous unclean shutdown); recovered without errors.
- [ ] T4 — Deploy `main` to the server, `.env` outside repo, composer autoload, nginx + php-fpm site, LAN-only. Route: inline (server config, user runs sudo parts).
- [ ] T5 — Smoke test API from the PC against the server. Route: inline.

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
T4: decide whether the frontend is served from the server, then deploy.
