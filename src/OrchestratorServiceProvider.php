<?php

namespace Xn\Orchestrator;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Xn\Orchestrator\Catalog\CatalogRepositoryInterface;
use Xn\Orchestrator\Catalog\YamlCatalog;
use Xn\Orchestrator\Commands\InstallCommand;
use Xn\Orchestrator\Commands\CatalogValidatorCommand;
use Xn\Orchestrator\Support\CompatibilityChecker;

class OrchestratorServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('xn-orchestrator')
            ->hasConfigFile()
            ->hasCommand(InstallCommand::class)
            ->hasCommand(CatalogValidatorCommand::class);
    }

    public function registeringPackage(): void
    {
        $this->app->bind(CatalogRepositoryInterface::class, function (): YamlCatalog {
            $path = config('xn-orchestrator.catalog_path') ?: __DIR__.'/../resources/catalog';

            return new YamlCatalog($path);
        });

        $this->app->bind(CompatibilityChecker::class, fn (): CompatibilityChecker => new CompatibilityChecker(
            $this->app->version(),
            PHP_VERSION,
        ));
    }

    public function bootingPackage(): void
    {
        $this->publishes([
            __DIR__.'/../resources/catalog' => config_path('package-catalog'),
        ], 'package-catalog');
    }
}
