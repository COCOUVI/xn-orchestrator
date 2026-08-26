<?php

namespace Xn\Orchestrator\Cli\Screens;

use Xn\Orchestrator\Catalog\PackageDefinition;
use Xn\Orchestrator\Cli\CliContext;
use Xn\Orchestrator\Cli\Screen;
use Xn\Orchestrator\Cli\ScreenHandler;
use Xn\Orchestrator\Cli\ScreenResult;
use Xn\Orchestrator\Cli\Support\EscapablePrompts;

final class CartScreen implements ScreenHandler
{
    public function handle(CliContext $context, ?string $payload = null): ScreenResult
    {
        while ($context->cart->count() > 0) {
            $this->renderSummary($context);

            $choice = EscapablePrompts::select(
                label: 'Select a package to remove',
                options: $context->cart->names(),
                hint: '↑/↓ Navigate   Enter Select   Esc Back',
            );

            if ($choice === null) {
                return ScreenResult::backTo(Screen::Menu);
            }

            $context->cart->remove($choice);
            $context->io->info("Removed {$choice} from the cart.");
        }

        $context->io->info('Your cart is empty.');

        return ScreenResult::backTo(Screen::Menu);
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
