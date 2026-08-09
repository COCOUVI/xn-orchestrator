<?php

namespace Xn\Orchestrator\Catalog;

final class StaticCatalog implements CatalogRepositoryInterface
{
    /** @return list<PackageDefinition> */
    public function getAll(): array
    {
        return [
            $this->filament(),
            $this->sanctum(),
            $this->permission(),
            $this->horizon(),
            $this->telescope(),
            $this->debugbar(),
            $this->breeze(),
            $this->jetstream(),
        ];
    }

    public function findByName(string $name): ?PackageDefinition
    {
        foreach ($this->getAll() as $package) {
            if ($package->name === $name) {
                return $package;
            }
        }

        return null;
    }

    /** @return list<PackageDefinition> */
    public function findByCategory(string $category): array
    {
        return array_values(array_filter(
            $this->getAll(),
            fn (PackageDefinition $package) => $package->category === $category,
        ));
    }

    private function filament(): PackageDefinition
    {
        return new PackageDefinition(
            name: 'filament/filament',
            category: 'Admin Panels',
            tags: ['filament', 'panel', 'admin', 'tui'],
            installSteps: [
                'composer require filament/filament:"^3.2" -W',
                'php artisan filament:install --panels',
                'php artisan migrate',
            ],
            supportedLaravel: ['^11.0', '^12.0', '^13.0'],
            supportedPhp: '^8.3',
        );
    }

    private function sanctum(): PackageDefinition
    {
        return new PackageDefinition(
            name: 'laravel/sanctum',
            category: 'Authentication',
            tags: ['api', 'token', 'auth'],
            installSteps: [
                'composer require laravel/sanctum',
                'php artisan vendor:publish --provider="Laravel\\Sanctum\\SanctumServiceProvider"',
                'php artisan migrate',
            ],
            supportedLaravel: ['^12.0', '^13.0'],
            supportedPhp: '^8.3',
        );
    }

    private function permission(): PackageDefinition
    {
        return new PackageDefinition(
            name: 'spatie/laravel-permission',
            category: 'Authorization',
            tags: ['roles', 'permissions', 'acl'],
            installSteps: [
                'composer require spatie/laravel-permission',
                'php artisan vendor:publish --provider="Spatie\\Permission\\PermissionServiceProvider"',
                'php artisan migrate',
            ],
            supportedLaravel: ['^11.0', '^12.0', '^13.0'],
            supportedPhp: '^8.3',
        );
    }

    private function horizon(): PackageDefinition
    {
        return new PackageDefinition(
            name: 'laravel/horizon',
            category: 'Queues',
            tags: ['queue', 'redis', 'dashboard'],
            installSteps: [
                'composer require laravel/horizon',
                'php artisan vendor:publish --provider="Laravel\\Horizon\\HorizonServiceProvider"',
                'php artisan migrate',
            ],
            supportedLaravel: ['^12.0', '^13.0'],
            supportedPhp: '^8.3',
        );
    }

    private function telescope(): PackageDefinition
    {
        return new PackageDefinition(
            name: 'laravel/telescope',
            category: 'Debugging',
            tags: ['debug', 'requests', 'queries'],
            installSteps: [
                'composer require laravel/telescope',
                'php artisan telescope:install',
                'php artisan migrate',
            ],
            supportedLaravel: ['^11.0', '^12.0', '^13.0'],
            supportedPhp: '^8.3',
        );
    }

    private function debugbar(): PackageDefinition
    {
        return new PackageDefinition(
            name: 'barryvdh/laravel-debugbar',
            category: 'Debugging',
            tags: ['debug', 'toolbar', 'queries'],
            installSteps: [
                'composer require barryvdh/laravel-debugbar --dev',
            ],
            supportedLaravel: ['^11.0', '^12.0', '^13.0'],
            supportedPhp: '^8.3',
        );
    }

    private function breeze(): PackageDefinition
    {
        return new PackageDefinition(
            name: 'laravel/breeze',
            category: 'Authentication',
            tags: ['auth', 'scaffolding', 'tailwind'],
            installSteps: [
                'composer require laravel/breeze --dev',
                'php artisan breeze:install blade',
                'npm install',
                'npm run build',
            ],
            supportedLaravel: ['^11.0', '^12.0', '^13.0'],
            supportedPhp: '^8.3',
            conflictsWith: ['laravel/jetstream'],
        );
    }

    private function jetstream(): PackageDefinition
    {
        return new PackageDefinition(
            name: 'laravel/jetstream',
            category: 'Authentication',
            tags: ['auth', 'teams', 'two-factor'],
            installSteps: [
                'composer require laravel/jetstream',
                'php artisan jetstream:install livewire',
                'npm install',
                'npm run build',
                'php artisan migrate',
            ],
            supportedLaravel: ['^11.0', '^12.0', '^13.0'],
            supportedPhp: '^8.3',
            conflictsWith: ['laravel/breeze'],
        );
    }
}
