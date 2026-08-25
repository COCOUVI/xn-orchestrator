<?php

namespace Xn\Orchestrator\Cli\Screens;

use Xn\Orchestrator\Catalog\PackageDefinition;
use Xn\Orchestrator\Cli\CliContext;
use Xn\Orchestrator\Cli\Screen;
use Xn\Orchestrator\Cli\ScreenHandler;
use Xn\Orchestrator\Cli\ScreenResult;

use function Laravel\Prompts\multiselect;

final class PackageScreen implements ScreenHandler
{
    public function handle(CliContext $context, ?string $payload = null): ScreenResult
    {
        $category = $payload ?? '';

        $packages = $context->catalog->findByCategory($category);

        if ($packages === []) {
            return ScreenResult::goto(Screen::Categories);
        }

        $options = $this->options($context, $packages);

        if ($options === []) {
            $context->io->info("No compatible packages in {$category}.");

            return ScreenResult::goto(Screen::Categories);
        }

        $context->io->line($category);
        $context->io->newLine();

        $selected = multiselect(
            label: 'Select packages',
            options: $options,
            default: $this->previouslySelected($context, $packages),
            hint: '↑/↓ Navigate   Space Toggle   Enter Confirm',
        );

        $this->syncCart($context, $packages, $selected);

        $count = $context->cart->count();
        $context->io->info("{$count} package".($count > 1 ? 's' : '').' selected');

        return ScreenResult::goto(Screen::Categories);
    }

    /**
     * @param  list<PackageDefinition>  $packages
     * @return array<string, string>
     */
    private function options(CliContext $context, array $packages): array
    {
        $options = [];

        foreach ($packages as $package) {
            if ($context->hideIncompatible && ! $context->compatibility->isCompatible($package)) {
                continue;
            }

            $options[$package->name] = $context->compatibility->isCompatible($package)
                ? $package->name
                : $package->name.' (incompatible)';
        }

        return $options;
    }

    /**
     * @param  list<PackageDefinition>  $packages
     * @return list<string>
     */
    private function previouslySelected(CliContext $context, array $packages): array
    {
        return collect($packages)
            ->pluck('name')
            ->filter(fn (string $name) => $context->cart->has($name))
            ->values()
            ->all();
    }

    /**
     * @param  list<PackageDefinition>  $packages
     * @param  list<string>  $selected
     */
    private function syncCart(CliContext $context, array $packages, array $selected): void
    {
        foreach ($packages as $package) {
            if (in_array($package->name, $selected, true)) {
                $context->cart->add($package);
            } else {
                $context->cart->remove($package->name);
            }
        }
    }
}
