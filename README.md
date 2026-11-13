# Modrix Commerce

Modular, open-source e-commerce built on Laravel 13, Inertia and React.

The core is a complete store: catalog, cart and checkout, orders, invoices, customers, staff roles and permissions, multi-language storefronts, backups and one-click core updates. Everything else, such as payment gateways, marketing tools and extra themes, is added as a module or theme from **Modrix Cloud**.

## What's in this repository

| Part                                                             | What it is                                                                                                          |
| ---------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------- |
| Core platform (`app/`, `resources/`, `routes/`)                  | The store and its admin                                                                                             |
| [Modrix Cloud Connector](modules/modrixcommerce/cloud-connector) | Connects your store to Modrix Cloud: browse and install modules and themes, sync license keys, update with rollback |
| [Circuit Market](themes/modrixcommerce/circuit)                  | The starter theme, active by default                                                                                |

Every other module and theme lives on the Modrix Cloud marketplace. Install them from **Admin → Extensions** once the Cloud Connector is enabled, or upload a package ZIP there.

## Requirements

- PHP 8.3 or 8.4 with `bcmath ctype curl dom fileinfo intl mbstring openssl pdo session tokenizer xml zip gd`
- Composer 2 and Node 22+
- MySQL 8 (8.4 LTS recommended) or MariaDB for production; SQLite works for local development

## Quick start (local)

```bash
git clone https://github.com/Modrix-site/Modrix-Commerce.git
cd Modrix-Commerce
composer setup
composer dev
```

`composer setup` installs dependencies, creates `.env`, generates the app key, runs the migrations and builds the frontend. `composer dev` starts the app. Then open `/install` to create the owner account and the store.

## Deploying

- **Git platforms** (Uplary and similar): the database comes from the platform and the installer only creates the owner. See [deployment modes](docs/installation/deployment-modes.md).
- **Shared hosting** (cPanel, Plesk, DirectAdmin and others, no SSH needed): the web installer at `/install` walks you through the database and store setup.
- **VPS:** see [production deployment](docs/installation/production.md).

Operations guides: [backups](docs/operations/backup-and-restore.md), [core updates](docs/operations/core-updates.md), [monitoring](docs/operations/monitoring.md), [troubleshooting](docs/operations/troubleshooting.md).

## Building modules and themes

- [Getting started with extensions](docs/extensions/getting-started.md)
- [Module development](docs/extensions/module-development.md)
- [Theme development](docs/extensions/theme-development.md). Start a new theme from Circuit with `php artisan ecomodular:make:theme acme/my-theme`.

## Development

```bash
composer ci:check   # lint, formatting, types, PHPStan and the test suite
```

Tests run on SQLite by default and in CI on MySQL 8.4 too. See [CONTRIBUTING.md](CONTRIBUTING.md) for the ground rules and [SECURITY.md](SECURITY.md) to report a vulnerability.

## License

[MIT](LICENSE) © Orlando Eleydo
