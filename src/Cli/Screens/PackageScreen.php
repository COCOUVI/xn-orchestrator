<?php

namespace Xn\Orchestrator\Cli\Screens;

use Xn\Orchestrator\Catalog\PackageDefinition;
use Xn\Orchestrator\Cli\CliContext;
use Xn\Orchestrator\Cli\Screen;
use Xn\Orchestrator\Cli\ScreenHandler;
use Xn\Orchestrator\Cli\ScreenResult;
use Xn\Orchestrator\Cli\Support\EscapablePrompts;

final class PackageScreen implements ScreenHandler
{
    public function handle(CliContext $context, ?string $payload = null): ScreenResult
    {
        $category = $payload ?? '';

        $packages = $context->catalog->findByCategory($category);

        if ($packages === []) {
            return ScreenResult::goto(Screen::Categories);
        }

        $options = [];

        foreach ($packages as $package) {
            if ($context->hideIncompatible && ! $context->compatibility->isCompatible($package)) {
                continue;
            }

            $options[$package->name] = $context->compatibility->isCompatible($package)
                ? $package->name
                : $package->name.' (incompatible)';
        }

        if ($options === []) {
            $context->io->info("No compatible packages in {$category}.");

            return ScreenResult::goto(Screen::Categories);
        }

        $selected = EscapablePrompts::multiselect(
            label: "Select packages in {$category}",
            options: $options,
            default: [],
            hint: '↑/↓ Navigate   Enter Select   Esc Back',
        );

        if ($selected === null) {
            return ScreenResult::backTo(Screen::Categories);
        }

        $this->syncCart($context, $packages, $selected);

        $count = $context->cart->count();
        $context->io->info("{$count} package".($count > 1 ? 's' : '').' selected');

        return ScreenResult::goto(Screen::Categories);
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
            }
        }
    }
}
