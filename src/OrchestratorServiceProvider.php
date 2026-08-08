<?php

namespace Xn\Orchestrator;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;
use Xn\Orchestrator\Commands\OrchestratorCommand;

class OrchestratorServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('xn-orchestrator')
            ->hasCommand(OrchestratorCommand::class);
    }
}
