<?php

namespace Xn\Orchestrator\Commands;

use Illuminate\Console\Command;
use Xn\Orchestrator\Catalog\CatalogRepositoryInterface;
use Xn\Orchestrator\Catalog\PackageDefinition;
use Xn\Orchestrator\Exceptions\PackageInstallationException;
use Xn\Orchestrator\Support\ProcessRunner;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\select;

class InstallCommand extends Command
{
    public $signature = 'x:install';

    public $description = 'Install a Laravel package from the catalog';

    public function __construct(
        private CatalogRepositoryInterface $catalog,
        private ProcessRunner $processRunner,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $packages = $this->catalog->getAll();

        if ($packages === []) {
            $this->components->warn('The catalog is empty. Nothing to install.');

            return self::SUCCESS;
        }

        $selected = select(
            label: 'Select a package to install',
            options: $this->packageNames($packages),
        );

        $package = $this->catalog->findByName($selected);

        if ($package === null) {
            $this->components->error("Package '{$selected}' was not found in the catalog.");

            return self::FAILURE;
        }

        $this->displayPlan($package);

        if (! confirm('Proceed with installation?')) {
            $this->components->info('Installation cancelled.');

            return self::SUCCESS;
        }

        return $this->install($package);
    }

    /**
     * @param  list<PackageDefinition>  $packages
     * @return list<string>
     */
    private function packageNames(array $packages): array
    {
        return array_map(
            fn (PackageDefinition $package) => $package->name,
            $packages,
        );
    }

    private function displayPlan(PackageDefinition $package): void
    {
        $this->components->info("Installing {$package->name} ({$package->category})");
        $this->newLine();

        foreach ($package->installSteps as $index => $step) {
            $this->line(sprintf('  %d. %s', $index + 1, $step));
        }

        $this->newLine();
    }

    private function install(PackageDefinition $package): int
    {
        foreach ($package->installSteps as $step) {
            try {
                $this->processRunner->runOrThrow($step, "Executing: {$step}");

                $this->components->info("  ✓ {$step}");
            } catch (PackageInstallationException $exception) {
                $this->components->error("  ✗ {$step}");
                $this->components->error($exception->getMessage());

                return self::FAILURE;
            }
        }

        $this->components->info("  {$package->name} installed successfully.");

        return self::SUCCESS;
    }
}
