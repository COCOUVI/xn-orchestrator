# xn-orchestrator

[![Latest Version on Packagist](https://img.shields.io/packagist/v/cocouvi/xn-orchestrator.svg?style=flat-square)](https://packagist.org/packages/cocouvi/xn-orchestrator)
[![GitHub Tests Action Status](https://github.com/cocouvi/xn-orchestrator/actions/workflows/run-tests.yml/badge.svg)](https://github.com/cocouvi/xn-orchestrator/actions?query=workflow%3Arun-tests+branch%3Amain)
[![GitHub Code Style Action Status](https://github.com/cocouvi/xn-orchestrator/actions/workflows/fix-php-code-style-issues.yml/badge.svg)](https://github.com/cocouvi/xn-orchestrator/actions?query=workflow%3A"Fix+PHP+code+style+issues"+branch%3Amain)
[![Total Downloads](https://img.shields.io/packagist/dt/cocouvi/xn-orchestrator.svg?style=flat-square)](https://packagist.org/packages/cocouvi/xn-orchestrator)

Install and configure Laravel packages from a community-driven catalog directly from the CLI.

Browse categories, search packages, build an installation cart, resolve dependencies automatically, check Laravel/PHP compatibility, then install everything with a recap of each command.

## Features

- **Interactive installer** — browse by category or search by name/tags, accumulate packages in a cart across selections
- **YAML-driven catalog** — one file per package, easy to read and contribute; no PHP required to add entries
- **Dependency resolution** — missing `depends_on` packages are offered before installation, `conflicts_with` blocks incompatible combinations, circular dependencies are detected
- **Compatibility checks** — packages are tagged `⚠ incompatible` when they do not support your current Laravel or PHP version (or hidden entirely via config), with an explicit confirmation before forcing the install
- **Dry run & rollback** — preview every command without executing it (`--dry-run`); a failing step stops the run instead of leaving a half-installed state
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

Preview the full plan without executing anything:

```bash
php artisan x:install --dry-run
```

The main menu lets you:

1. **Browse the catalog** — pick a category, multi-select packages
2. **Search packages** — find packages by composer name or tag keywords
3. **View cart** — review and remove selected packages
4. **Finish and install** — resolve dependencies, display the ordered installation plan, confirm, execute

Before running, missing dependencies are offered automatically, conflicting packages block the installation, and incompatible packages require explicit confirmation.

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

## Overriding the catalog

Publish the catalog files into your application to customize them or hide entries you never use:

```bash
php artisan vendor:publish --tag=package-catalog
```

Then point the package to your own directory (see [Configuration](#configuration)).

## Contributing packages

Adding a package to the catalog is a single YAML file. Schema:

```yaml
name: spatie/laravel-permission      # composer package name (required)
category: Authorization              # category shown in the menu (required)
tags: [roles, permissions, acl]      # search keywords
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

## Configuration

Publish the config file:

```bash
php artisan vendor:publish --tag="xn-orchestrator-config"
```

```php
return [
    // Directory holding the YAML catalog. Null = the package's own copy.
    'catalog_path' => null,

    // true = incompatible packages are hidden from menus.
    // false (default) = shown with an "⚠ incompatible" marker + confirmation.
    'compatibility' => [
        'hide_incompatible' => false,
    ],

    // Dedicated log channel for installation runs.
    'logging' => [
        'channel' => 'package-orchestrator',
    ],
];
```

## Testing

```bash
composer test
```

## Changelog

Please see [CHANGELOG](CHANGELOG.md) for more information on what has changed recently.

## Contributing

Please see [CONTRIBUTING](CONTRIBUTING.md) for details.

## Security Vulnerabilities

Please review [our security policy](../../security/policy) on how to report security vulnerabilities.

## Credits

- [COCOUVI](https://github.com/COCOUVI)
- [All Contributors](../../contributors)

## License

The MIT License (MIT). Please see [License File](LICENSE.md) for more information.