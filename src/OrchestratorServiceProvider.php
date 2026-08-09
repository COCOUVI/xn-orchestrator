<?php

namespace Xn\Orchestrator;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Xn\Orchestrator\Catalog\CatalogRepositoryInterface;
use Xn\Orchestrator\Catalog\StaticCatalog;
use Xn\Orchestrator\Commands\OrchestratorCommand;

class OrchestratorServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('xn-orchestrator')
            ->hasCommand(OrchestratorCommand::class);
    }

    public function registeringPackage(): void
    {
        $this->app->bind(CatalogRepositoryInterface::class, StaticCatalog::class);
    }
}
