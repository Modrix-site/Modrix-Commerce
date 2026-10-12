# Changelog

All notable changes to this project are documented here. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project uses [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [0.1.0] - 2026-10-12

### Added

- Phase 0 documentation: architecture overview, ADRs 001–011, database schema, testing strategy, roadmap and third-party notices.
- Laravel 13 / React 19 / TypeScript / Inertia 3 / Vite 8 application scaffold (from `laravel/react-starter-kit`).
- Installer: the `ecomodular:install` command and a token-gated `/install` web installer that locks itself after installation. `ecomodular:installer-token` command.
- RBAC: `roles`, `permissions`, `permission_role` and `role_user` tables, a `PermissionRegistry`, seven system roles, custom roles, `EscalationGuard`, and the `ecomodular:sync-permissions` command.
- Staff user management (invite by password-setup link, role assignment, disable/enable) behind password re-confirmation.
- Append-only audit log with secret redaction. Authentication events are audited.
- Store settings (name, email, currency, timezone, units) with a cached settings repository.
- Admin shell: permission-filtered server navigation, dashboard, users, roles, store settings, audit log and system status pages.
- Disabled-account enforcement at login and on every request.
- Docker Compose development stack (PHP-FPM 8.4, Nginx, MySQL 8.4, Redis, Mailpit, queue worker, scheduler).
- CI: PHP 8.3/8.4 matrix, MySQL 8.4 test job, dependency audit.

### Changed

- `/dashboard` now sends staff to `/admin` and customers to their account settings.
- `/settings` now uses an explicit GET redirect instead of `Route::redirect`, which fixes the Wayfinder TypeScript error (ADR-011).

### Removed

- Unused starter-kit placeholder dashboard, header layout and external starter-kit links.
