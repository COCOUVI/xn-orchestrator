<?php

namespace Xn\Orchestrator\Commands;

use Illuminate\Console\Command;
use Xn\Orchestrator\Cart\InstallationCart;
use Xn\Orchestrator\Catalog\CatalogRepositoryInterface;
use Xn\Orchestrator\Catalog\PackageDefinition;
use Xn\Orchestrator\Exceptions\PackageInstallationException;
use Xn\Orchestrator\Support\ProcessRunner;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\select;

class InstallCommand extends Command
{
    public $signature = 'x:install';

    public $description = 'Install a Laravel package from the catalog';

    private const BACK = '← Back to main menu';

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

        $cart = new InstallationCart;

        while (true) {
            $choice = select(
                label: 'What do you want to do?',
                options: ['Browse the catalog', 'View cart', 'Finish and install', 'Quit'],
            );

            $result = match ($choice) {
                'Browse the catalog' => $this->browseCatalog($cart),
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

        $selected = multiselect(
            label: "Select packages in {$category}",
            options: $this->packageNames($packages),
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

    private function installCart(InstallationCart $cart): ?int
    {
        if ($cart->count() === 0) {
            $this->components->warn('Your cart is empty. Add packages first.');

            return null;
        }

        $this->displayRecap($cart);

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

    /** @param  list<PackageDefinition>  $packages
     *  @return list<string> */
    private function packageNames(array $packages): array
    {
        return array_map(
            fn (PackageDefinition $package) => $package->name,
            $packages,
        );
    }
}
