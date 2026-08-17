<?php

namespace Xn\Orchestrator\Commands;

use Illuminate\Console\Command;
use Xn\Orchestrator\Cart\InstallationCart;
use Xn\Orchestrator\Catalog\CatalogRepositoryInterface;
use Xn\Orchestrator\Catalog\PackageDefinition;
use Xn\Orchestrator\Exceptions\CircularDependencyException;
use Xn\Orchestrator\Exceptions\PackageInstallationException;
use Xn\Orchestrator\Support\CompatibilityChecker;
use Xn\Orchestrator\Support\DependencyResolver;
use Xn\Orchestrator\Support\ProcessRunner;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\search;
use function Laravel\Prompts\select;

class InstallCommand extends Command
{
    public $signature = 'x:install';

    public $description = 'Install a Laravel package from the catalog';

    private const BACK = '← Back to main menu';

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
        $packages = $this->catalog->getAll();

        if ($packages === []) {
            $this->components->warn('The catalog is empty. Nothing to install.');

            return self::SUCCESS;
        }

        $cart = new InstallationCart;

        while (true) {
            $choice = select(
                label: 'What do you want to do?',
                options: ['Browse the catalog', 'Search packages', 'View cart', 'Finish and install', 'Quit'],
            );

            $result = match ($choice) {
                'Browse the catalog' => $this->browseCatalog($cart),
                'Search packages' => $this->searchPackages($cart),
                'View cart' => $this->viewCart($cart),
                'Finish and install' => $this->installCart($cart),
                'Quit' => self::SUCCESS,
                default => null,
            };

            if ($result !== null) {
                return $result;
            }
        }
    }

    private function browseCatalog(InstallationCart $cart): void
    {
        while (true) {
            $category = select(
                label: 'Select a category',
                options: [...$this->categoryNames(), self::BACK],
            );

            if ($category === self::BACK) {
                return;
            }

            $this->selectPackages($category, $cart);
        }
    }

    private function selectPackages(string $category, InstallationCart $cart): void
    {
        $packages = $this->catalog->findByCategory($category);

        if ($packages === []) {
            return;
        }

        $options = $this->packageOptions($packages);

        if ($options === []) {
            $this->components->info("No compatible packages in {$category}.");

            return;
        }

        $selected = multiselect(
            label: "Select packages in {$category}",
            options: $options,
        );

        foreach ($packages as $package) {
            if (in_array($package->name, $selected, true)) {
                $cart->add($package);
            }
        }
    }

    private function viewCart(InstallationCart $cart): void
    {
        if ($cart->count() === 0) {
            $this->components->info('Your cart is empty.');

            return;
        }

        while (true) {
            $this->components->info("Packages in the cart ({$cart->count()})");

            $choice = select(
                label: 'Select a package to remove',
                options: [...$cart->names(), self::BACK],
            );

            if ($choice === self::BACK) {
                return;
            }

            $cart->remove($choice);
            $this->components->info("Removed {$choice} from the cart.");

            if ($cart->count() === 0) {
                $this->components->info('Your cart is empty.');

                return;
            }
        }
    }

    private function searchPackages(InstallationCart $cart): void
    {
        $name = search(
            label: 'Search for a package',
            options: function (string $value) {
                $needle = strtolower(trim($value));

                return collect($this->catalog->getAll())
                    ->filter(
                        fn (PackageDefinition $package) => $needle === ''
                            || str_contains(strtolower($package->name), $needle)
                            || collect($package->tags)->contains(
                                fn (string $tag) => str_contains(strtolower($tag), $needle),
                            )
                    )
                    ->map(fn (PackageDefinition $package) => $package->name)
                    ->values()
                    ->all();
            },
        );

        $package = $this->catalog->findByName($name);

        if ($package === null) {
            $this->components->error("Package '{$name}' was not found in the catalog.");

            return;
        }

        $cart->add($package);
        $this->components->info("Added {$package->name} to the cart.");
    }

    private function installCart(InstallationCart $cart): ?int
    {
        if ($cart->count() === 0) {
            $this->components->warn('Your cart is empty. Add packages first.');

            return null;
        }

        if (! $this->resolveDependencies($cart)) {
            return null;
        }

        $this->displayRecap($cart);

        if (! $this->confirmCompatibility($cart)) {
            $this->components->info('Installation cancelled.');

            return null;
        }

        if (! confirm('Proceed with installation?')) {
            $this->components->info('Installation cancelled.');

            return null;
        }

        foreach ($cart->all() as $package) {
            $result = $this->install($package);

            if ($result !== self::SUCCESS) {
                return $result;
            }
        }

        return self::SUCCESS;
    }

    private function resolveDependencies(InstallationCart $cart): bool
    {
        $packages = $cart->all();

        $conflicts = $this->resolver->findConflicts($packages);

        if ($conflicts !== []) {
            $this->components->error('The following packages conflict and cannot be installed together:');

            foreach ($conflicts as [$first, $second]) {
                $this->components->error(sprintf('  %s conflicts with %s', $first, $second));
            }

            $this->components->info('Remove one of the conflicting packages from the cart and try again.');

            return false;
        }

        $missing = $this->resolver->findMissingDependencies($packages);

        foreach ($missing as $dependencyName) {
            $dependency = $this->catalog->findByName($dependencyName);

            if ($dependency === null) {
                $this->components->warn("Dependency {$dependencyName} is not available in the catalog.");

                continue;
            }

            if (confirm("{$dependencyName} is required. Add it to the cart?")) {
                $cart->add($dependency);
                $this->components->info("Added {$dependencyName} to the cart.");
            } else {
                $dependants = collect($cart->all())
                    ->filter(
                        fn (PackageDefinition $package) => in_array($dependencyName, $package->dependsOn, true),
                    )
                    ->pluck('name');

                foreach ($dependants as $dependantName) {
                    $cart->remove($dependantName);
                    $this->components->warn("Removed {$dependantName} from the cart because its dependency {$dependencyName} was declined.");
                }
            }
        }

        if ($cart->count() === 0) {
            $this->components->warn('Your cart is empty after resolving dependencies.');

            return false;
        }

        return $this->resolveOrder($cart);
    }

    private function resolveOrder(InstallationCart $cart): bool
    {
        try {
            $ordered = $this->resolver->resolveOrder($cart->all());
        } catch (CircularDependencyException $exception) {
            $this->components->error($exception->getMessage());

            return false;
        }

        $cart->replace($ordered);

        return true;
    }

    private function displayRecap(InstallationCart $cart): void
    {
        $this->components->info('Installation plan');
        $this->newLine();

        $stepNumber = 1;

        foreach ($cart->all() as $package) {
            $this->components->info($package->name);

            foreach ($package->installSteps as $step) {
                $this->line(sprintf('  %d. %s', $stepNumber++, $step));
            }
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

    /** @return list<string> */
    private function categoryNames(): array
    {
        return collect($this->catalog->getAll())
            ->map(fn (PackageDefinition $package) => $package->category)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  list<PackageDefinition>  $packages
     * @return array<string, string>
     */
    private function packageOptions(array $packages): array
    {
        $options = [];

        foreach ($packages as $package) {
            if ($this->hideIncompatible() && ! $this->compatibility->isCompatible($package)) {
                continue;
            }

            $options[$package->name] = $this->compatibility->isCompatible($package)
                ? $package->name
                : $package->name.' ⚠ incompatible';
        }

        return $options;
    }

    private function hideIncompatible(): bool
    {
        return (bool) config('xn-orchestrator.compatibility.hide_incompatible', false);
    }

    private function confirmCompatibility(InstallationCart $cart): bool
    {
        $incompatible = collect($cart->all())
            ->filter(fn (PackageDefinition $package) => ! $this->compatibility->isCompatible($package))
            ->pluck('name')
            ->values()
            ->all();

        if ($incompatible === []) {
            return true;
        }

        $this->components->warn('The following packages are not compatible with your Laravel or PHP version:');

        foreach ($incompatible as $name) {
            $this->components->warn("  - {$name}");
        }

        return confirm('Install incompatible packages anyway?');
    }
}
