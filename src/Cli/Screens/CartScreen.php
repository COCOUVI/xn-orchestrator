<?php

namespace Xn\Orchestrator\Cli\Screens;

use Xn\Orchestrator\Catalog\PackageDefinition;
use Xn\Orchestrator\Cli\CliContext;
use Xn\Orchestrator\Cli\Screen;
use Xn\Orchestrator\Cli\ScreenHandler;
use Xn\Orchestrator\Cli\ScreenResult;

use function Laravel\Prompts\select;

final class CartScreen implements ScreenHandler
{
    private const BACK = 'Back to main menu';

    public function handle(CliContext $context, ?string $payload = null): ScreenResult
    {
        while ($context->cart->count() > 0) {
            $this->renderSummary($context);

            $choice = select(
                label: 'Select a package to remove',
                options: [...$context->cart->names(), self::BACK],
                hint: 'Up/Down Navigate   Enter Confirm   Or type the option number',
            );

            if ($choice === self::BACK) {
                return ScreenResult::goto(Screen::Menu);
            }

            $context->cart->remove($choice);
            $context->io->info("Removed {$choice} from the cart.");
        }

        $context->io->info('Your cart is empty.');

        return ScreenResult::goto(Screen::Menu);
    }

    private function renderSummary(CliContext $context): void
    {
        $grouped = collect($context->cart->all())
            ->groupBy(fn (PackageDefinition $package) => $package->category)
            ->sortKeys();

        $context->io->info('Your cart');
        $context->io->newLine();

        foreach ($grouped as $category => $packages) {
            $context->io->line((string) $category);

            foreach ($packages as $package) {
                $context->io->line("  [x] {$package->name}");
            }
        }

        $count = $context->cart->count();
        $context->io->newLine();
        $context->io->info("Total: {$count} package".($count > 1 ? 's' : ''));
        $context->io->newLine();
    }
}
