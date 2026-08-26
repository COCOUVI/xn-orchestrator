<?php

namespace Xn\Orchestrator\Cli\Screens;

use Illuminate\Support\Facades\Log;
use Xn\Orchestrator\Catalog\PackageDefinition;
use Xn\Orchestrator\Cli\CliContext;
use Xn\Orchestrator\Cli\Screen;
use Xn\Orchestrator\Cli\ScreenHandler;
use Xn\Orchestrator\Cli\ScreenResult;
use Xn\Orchestrator\Exceptions\CircularDependencyException;
use Xn\Orchestrator\Exceptions\PackageInstallationException;
use Xn\Orchestrator\Support\DependencyResolver;
use Xn\Orchestrator\Support\ProcessRunner;

final class ReviewScreen implements ScreenHandler
{
    public function __construct(
        private readonly DependencyResolver $resolver,
        private readonly ProcessRunner $processRunner,
    ) {}

    public function handle(CliContext $context, ?string $payload = null): ScreenResult
    {
        if ($context->cart->count() === 0) {
            $context->io->warn('Your cart is empty. Add packages first.');

            return ScreenResult::goto(Screen::Menu);
        }

        if (! $this->resolveDependencies($context)) {
            return ScreenResult::goto(Screen::Menu);
        }

        $this->displayRecap($context);

        if (! $this->confirmCompatibility($context)) {
            $context->io->info('Installation cancelled.');

            return ScreenResult::backTo(Screen::Packages);
        }

        if (! confirm('Proceed with installation?')) {
            $context->io->info('Installation cancelled.');

            return ScreenResult::backTo(Screen::Packages);
        }

        return $this->execute($context);
    }

    private function resolveDependencies(CliContext $context): bool
    {
        $packages = $context->cart->all();

        $conflicts = $this->resolver->findConflicts($packages);

        if ($conflicts !== []) {
            $context->io->error('The following packages conflict and cannot be installed together:');

            foreach ($conflicts as [$first, $second]) {
                $context->io->error(sprintf('  %s conflicts with %s', $first, $second));
            }

            $context->io->info('Remove one of the conflicting packages from the cart and try again.');

            return false;
        }

        foreach ($this->resolver->findMissingDependencies($packages) as $dependencyName) {
            $this->negotiateDependency($context, $dependencyName);
        }

        if ($context->cart->count() === 0) {
            $context->io->warn('Your cart is empty after resolving dependencies.');

            return false;
        }

        return $this->resolveOrder($context);
    }

    private function negotiateDependency(CliContext $context, string $dependencyName): void
    {
        $dependency = $context->catalog->findByName($dependencyName);

        if ($dependency === null) {
            $context->io->warn("Dependency {$dependencyName} is not available in the catalog.");

            return;
        }

        if (confirm("{$dependencyName} is required. Add it to the cart?")) {
            $context->cart->add($dependency);
            $context->io->info("Added {$dependencyName} to the cart.");

            return;
        }

        $dependants = collect($context->cart->all())
            ->filter(
                fn (PackageDefinition $package) => in_array($dependencyName, $package->dependsOn, true),
            )
            ->pluck('name');

        foreach ($dependants as $dependantName) {
            $context->cart->remove($dependantName);
            $context->io->warn("Removed {$dependantName} from the cart because its dependency {$dependencyName} was declined.");
        }
    }

    private function resolveOrder(CliContext $context): bool
    {
        try {
            $ordered = $this->resolver->resolveOrder($context->cart->all());
        } catch (CircularDependencyException $exception) {
            $context->io->error($exception->getMessage());

            return false;
        }

        $context->cart->replace($ordered);

        return true;
    }

    private function displayRecap(CliContext $context): void
    {
        $context->io->newLine();
        $context->io->info("Installation de {$context->cart->all()[0]->name} et autres packages...");
        $context->io->newLine();
    }

    private function confirmCompatibility(CliContext $context): bool
    {
        $incompatible = collect($context->cart->all())
            ->filter(fn (PackageDefinition $package) => ! $context->compatibility->isCompatible($package))
            ->pluck('name')
            ->values()
            ->all();

        if ($incompatible === []) {
            return true;
        }

        $context->io->warn('The following packages are not compatible with your Laravel or PHP version:');

        foreach ($incompatible as $name) {
            $context->io->warn("  - {$name}");
        }

        return confirm('Install incompatible packages anyway?');
    }

    private function execute(CliContext $context): ScreenResult
    {
        $installed = [];
        $failedPackages = [];

        foreach ($context->cart->all() as $package) {
            if (! $this->installPackage($context, $package, $installed)) {
                $failedPackages[] = $package->name;

                if (! $context->dryRun) {
                    $this->rollback($context, $installed, $failedPackages);

                    return ScreenResult::failure();
                }
            }
        }

        $this->displaySummary($context, $installed, $failedPackages);

        return ScreenResult::success();
    }

    private function installPackage(CliContext $context, PackageDefinition $package, array &$installed): bool
    {
        foreach ($package->installSteps as $step) {
            if ($context->dryRun) {
                $context->io->info("Installation en mode dry-run de {$package->name}.");

                continue;
            }

            try {
                $this->processRunner->runOrThrow($step, "Installing {$package->name}");

                $context->io->info("✓ {$package->name} installé avec succès.");

                Log::info('Package step executed', [
                    'package' => $package->name,
                    'step' => $step,
                    'status' => 'success',
                ]);
            } catch (PackageInstallationException $exception) {
                $context->io->info("✗ {$package->name} a échoué.");

                $context->io->error($exception->getMessage());

                Log::error('Package step failed', [
                    'package' => $package->name,
                    'step' => $step,
                    'error' => $exception->getMessage(),
                    'output' => $exception->result->output,
                ]);

                return false;
            }
        }

        if ($context->dryRun) {
            Log::info('Package dry-run completed', [
                'package' => $package->name,
                'status' => 'completed',
            ]);
        } else {
            $installed[] = $package->name;

            Log::info('Package installed successfully', [
                'package' => $package->name,
                'status' => 'installed',
            ]);
        }

        return true;
    }

    /**
     * @param  list<string>  $installed
     * @param  list<string>  $failed
     */
    private function rollback(CliContext $context, array $installed, array $failed): void
    {
        $context->io->warn('Rolling back...');

        foreach (array_reverse($installed) as $packageName) {
            $context->io->info("  Revert: {$packageName}");
        }

        $context->io->warn('Installation failed. The following packages were partially installed and rolled back:');

        foreach ($failed as $name) {
            $context->io->warn("  - {$name}");
        }
    }

    /**
     * @param  list<string>  $installed
     * @param  list<string>  $failed
     */
    private function displaySummary(CliContext $context, array $installed, array $failed): void
    {
        $context->io->newLine();

        if ($installed === [] && $failed === []) {
            $context->io->info('Aucun package n\'a été installé.');

            return;
        }

        $context->io->newLine();

        if ($installed !== []) {
            $context->io->line("<fg=green;options=bold>✓</> Installation(s) terminée(s) avec succès !");

            foreach ($installed as $name) {
                $context->io->line("  - {$name}");
            }
        }

        if ($failed !== []) {
            $context->io->newLine();

            $context->io->line("<fg=red;options=bold>✗</> Installation terminée avec erreurs :");

            foreach ($failed as $name) {
                $context->io->line("  - {$name}");
            }
        }

        $context->io->newLine();
    }
}