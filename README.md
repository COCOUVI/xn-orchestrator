# xn-orchestrator

[![Latest Version on Packagist](https://img.shields.io/packagist/v/cocouvi/xn-orchestrator.svg?style=flat-square)](https://packagist.org/packages/cocouvi/xn-orchestrator)
[![GitHub Tests Action Status](https://img.shields.io/github/actions/status/cocouvi/xn-orchestrator?style=flat-square)](https://github.com/cocouvi/xn-orchestrator/actions)
[![Total Downloads](https://img.shields.io/packagist/dt/cocouvi/xn-orchestrator.svg?style=flat-square)](https://packagist.org/packages/cocouvi/xn-orchestrator)
[![PHPStan Status](https://img.shields.io/badge/phpstan-enabled-brightgreen?style=flat-square)](https://github.com/cocouvi/xn-orchestrator/blob/main/phpstan.neon.dist)
[![Pint Code Style](https://img.shields.io/badge/pint-enabled-brightgreen?style=flat-square)](https://github.com/cocouvi/xn-orchestrator/blob/main/.phpunit.cache)
[![Pest Tests](https://img.shields.io/badge/pest-enabled-brightgreen?style=flat-square)](https://github.com/cocouvi/xn-orchestrator/actions)

Install and configure Laravel packages from a catalog directly from the CLI.

## Features

- **Interactive installer** — browse by category and accumulate packages in a cart across selections
- **YAML-driven catalog** — one file per package, easy to read and contribute; no PHP required to add entries
- **Dependency resolution** — missing `depends_on` packages are offered before installation, `conflicts_with` blocks incompatible combinations, circular dependencies are detected
- **Compatibility checks** — packages are tagged `⚠ incompatible` when they do not support your current Laravel or PHP version (or hidden entirely via config), with an explicit confirmation before forcing the install

- **Failure reporting** — a failing step stops the installation and reports the affected packages
- **Install logging** — installations are recorded in a dedicated log channel for auditing what was run and when

## Installation

You can install the package via composer:

```bash
composer require cocouvi/xn-orchestrator --dev
```

## Usage

Start the interactive installer:

```bash
php artisan x:install
```

The main menu lets you:

1. **Browse categories** — choose a category, select packages, then press `Enter` to add them to the cart
2. **Finish and install** — resolve dependencies, confirm compatibility when needed, then execute the selected package steps
3. **Quit** — leave the installer

Press `Esc` to go back or exit. `Esc` cancels the current package selection; use `Enter` to save the checked packages first. The menu always displays the current number of selected packages.



## The catalog

The bundled catalog ships 24 essential packages across 15 categories:

| Category | Packages |
| --- | --- |
| Admin Panels | filament/filament |
| Authentication | laravel/sanctum, laravel/breeze, laravel/jetstream, laravel/socialite |
| Authorization | spatie/laravel-permission |
| Backups | spatie/laravel-backup |
| Billing | laravel/cashier |
| Debugging | laravel/telescope, barryvdh/laravel-debugbar, laravel/pulse |
| Development | barryvdh/laravel-ide-helper, reliese/laravel, mrpunyapal/laravel-auditor |
| Exports | maatwebsite/excel, barryvdh/laravel-dompdf |
| Frontend | livewire/livewire |
| Media | spatie/laravel-medialibrary |
| Queues | laravel/horizon |
| Real-Time | laravel/reverb |
| Scaffolding | devalade/crudify |
| Search | laravel/scout |
| Testing | pestphp/pest, laravel/dusk |

## Contributing packages

Adding a package to the catalog is a single YAML file. Schema:

```yaml
name: spatie/laravel-permission      # composer package name (required)
category: Authorization              # category shown in the menu (required)
tags: [roles, permissions, acl]      # package metadata
install:                             # commands executed in order (required)
  - 'composer require spatie/laravel-permission'
  - 'php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"'
  - 'php artisan migrate'
supported:
  laravel: ['^11.0', '^12.0', '^13.0']  # version constraints checked against app()->version()
  php: '^8.3'                            # constraint checked against PHP_VERSION
depends_on: []                       # composer names installed first
conflicts_with: []                   # composer names that cannot coexist
```

Validate your entry before submitting a PR:

```bash
php artisan x:catalog:validate
# or validate a specific directory
php artisan x:catalog:validate resources/catalog
```

Invalid or malformed files are skipped with a warning at load time, so one broken entry never breaks the whole catalog — but they will be reported by the validator.

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Credits

- [COCOUVI](https://github.com/COCOUVI)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.
