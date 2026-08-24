<?php

namespace Xn\Orchestrator\Commands;

use Illuminate\Console\Command;
use Xn\Orchestrator\Cart\InstallationCart;
use Xn\Orchestrator\Catalog\CatalogRepositoryInterface;
use Xn\Orchestrator\Cli\CliContext;
use Xn\Orchestrator\Cli\CommandCliIO;
use Xn\Orchestrator\Cli\InstallerSession;
use Xn\Orchestrator\Support\CompatibilityChecker;
use Xn\Orchestrator\Support\DependencyResolver;
use Xn\Orchestrator\Support\ProcessRunner;

class InstallCommand extends Command
{
    public $signature = 'x:install {--dry-run}';

    public $description = 'Install Laravel packages from the catalog through an interactive session';

    public function __construct(
        private CatalogRepositoryInterface $catalog,
        private ProcessRunner $processRunner,
        private DependencyResolver $resolver,
        private CompatibilityChecker $compatibility,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $context = new CliContext(
            catalog: $this->catalog,
            cart: new InstallationCart,
            compatibility: $this->compatibility,
            io: new CommandCliIO($this->output),
            hideIncompatible: (bool) config('xn-orchestrator.compatibility.hide_incompatible', false),
            dryRun: (bool) $this->option('dry-run'),
        );

        return (new InstallerSession($context, $this->resolver, $this->processRunner))->run();
    }
}
