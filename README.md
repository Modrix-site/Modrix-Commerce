# EcoModular

A self-hosted, modular, open-source eCommerce platform built on **Laravel 13**, **React 19**, **TypeScript**, **Inertia 3** and **Vite**.

The core is MIT-licensed and fully usable on its own. The platform is designed for free and premium themes, modules and integrations, distributed later through an official marketplace (see [docs/marketplace/licensing.md](docs/marketplace/licensing.md)). Premium extensions are never required to run a store.

> **Status: early development (v0.1.0, Phase 1 of 8).** The platform foundation is in place: installer, authentication, role-based access control, audit log, settings and the admin shell. Catalog, cart, checkout and payments arrive in Phases 2 and 3. **Do not use it for a live store yet.** See [docs/roadmap.md](docs/roadmap.md).

## Features available today

- Secure installation: CLI (`php artisan ecomodular:install`) or a token-gated web installer that disables itself once installation completes.
- Authentication via Laravel Fortify: email verification, password reset, two-factor authentication and passkeys.
- Server-enforced RBAC: seven system roles, granular permissions, custom roles, and guards against privilege escalation.
- Password re-confirmation before user and role management.
- Append-only audit log with secret redaction, covering sign-ins, role changes, settings and installation.
- Store settings: name, contact email, currency, timezone and units.
- Responsive, accessible admin shell whose navigation is filtered by permission.
- System status page with configuration warnings.

## Requirements

- PHP **8.3 or 8.4**, with these extensions: `bcmath`, `ctype`, `curl`, `dom`, `fileinfo`, `filter`, `intl`, `mbstring`, `openssl`, `pdo_mysql` (or `pdo_sqlite`), `session`, `tokenizer`, `xml`, `zip`
- Composer 2
- Node.js 22+ and npm (to build the frontend)
- MySQL 8.0+ / 8.4 LTS (production) or SQLite (evaluation and tests)
- Redis (optional; recommended for cache, queues and sessions)

> Windows note: make sure `php -v` reports 8.3 or newer. If several PHP versions are installed, put the 8.4 directory first on `PATH`.

## Quick start (local)

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
npm run build
php artisan ecomodular:install
php artisan serve
```

Then open http://127.0.0.1:8000 and sign in with the owner account you created.

To use the web installer instead, skip `ecomodular:install`, visit `/install`, and paste the setup token printed by `php artisan ecomodular:installer-token`.

### Demo data (development only)

```bash
php artisan migrate:fresh
php artisan db:seed --class=DevelopmentSeeder
```

This creates `<role>@example.test` accounts for every system role, plus `customer@example.test`. All of them use the password `password`. The seeder refuses to run in production.

### Docker Compose

```bash
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
docker compose exec app php artisan ecomodular:install
```

The site runs at http://127.0.0.1:8080 and Mailpit at http://127.0.0.1:8025. MySQL and Redis are not exposed to the host.

## Quality gates

```bash
composer test          # pint --test, larastan (level 7), phpunit
npm run types:check    # tsc
npm run check          # format + lint
npm run build          # production assets
```

CI runs these on PHP 8.3 and 8.4, runs the full suite against MySQL 8.4, and runs `composer audit` and `npm audit`.

## Documentation

| Topic                     | Document                                                                       |
| ------------------------- | ------------------------------------------------------------------------------ |
| Architecture              | [docs/architecture/overview.md](docs/architecture/overview.md)                 |
| Decisions (ADRs)          | [docs/architecture/decisions.md](docs/architecture/decisions.md)               |
| Database schema           | [docs/database/schema.md](docs/database/schema.md)                             |
| Production install        | [docs/installation/production.md](docs/installation/production.md)             |
| Backup and restore        | [docs/operations/backup-and-restore.md](docs/operations/backup-and-restore.md) |
| Module development        | [docs/extensions/module-development.md](docs/extensions/module-development.md) |
| Theme development         | [docs/extensions/theme-development.md](docs/extensions/theme-development.md)   |
| Licensing and marketplace | [docs/marketplace/licensing.md](docs/marketplace/licensing.md)                 |
| Testing strategy          | [docs/testing/strategy.md](docs/testing/strategy.md)                           |
| Roadmap and progress      | [docs/roadmap.md](docs/roadmap.md)                                             |

## License

The EcoModular core is released under the [MIT License](LICENSE), Copyright (c) 2026 Eleydo. Third-party components keep their own licenses; see [THIRD_PARTY_NOTICES.md](THIRD_PARTY_NOTICES.md). Commercial themes and modules sold through the official marketplace may carry separate license terms. Those terms never apply to this core.
