# MusicEngine modernization baseline

## Current application

- The product is a PHP application with a custom router, controller/model classes, and server-rendered `.phtml` views. The front end uses jQuery and a large shared stylesheet.
- Core routes cover accounts, discovery, tracks, collections, comments, messaging, statistics, payments, and admin workflows. Optional modules add store, video, spotlight, and contact features.
- The database is MySQL. The schema is in `Script/MusicEngine.sql`; the Docker development setup initializes from `MYSQL/MusicEngine.sql`.
- Track and video media, artwork, waveforms, and temporary uploads live under `Script/uploads`. This directory must be preserved with the database during any migration.
- Local development uses PHP 8.5 Apache and MySQL 8.4. No application-owned test suite, frontend build system, or CI workflow was present at baseline.

## Security and compatibility findings

- Removed a hardcoded feed request path that returned account email addresses.
- Artist statistics now validate time range and track ID and verify that a requested track belongs to the signed-in artist (or an administrator).
- New passwords and password resets use PHP's password hashing API; valid legacy MD5 passwords are upgraded when their owner next signs in. Session IDs rotate at authentication, and session/remember-me cookies use `HttpOnly`, `SameSite=Lax`, and production `Secure` attributes.
- Legacy APIs and routes are retained; broad CSRF and authorization changes require endpoint-by-endpoint compatibility review before production rollout.
- The code has a large vendor tree and prior dependency changes in the working tree. Keep these changes intact and review Composer compatibility and licensing before removing or replacing packages.
- PHP diagnostics are now hidden by default and configurable via `APP_DEBUG`; HTTPS URL generation is configurable with `APP_HTTPS`.

## Data and rollout constraints

- The checked-in SQL files are installation snapshots, not production backups. Do not initialize a production database from them when preserving a live installation.
- Back up the production database and `Script/uploads` together, restore both into a staging environment, and reconcile users, tracks, transactions, and media before cutover.
- The production Compose stack is isolated from the local development stack, keeps MySQL off public ports, serves HTTPS through Caddy, and has persistent database and media volumes.
- Production deployment still requires a real domain, VPS access, generated secrets, an authorized licensing path, and a rehearsed source-data migration.
