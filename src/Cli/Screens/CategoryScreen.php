<?php

namespace Xn\Orchestrator\Cli\Screens;

use Xn\Orchestrator\Catalog\PackageDefinition;
use Xn\Orchestrator\Cli\CliContext;
use Xn\Orchestrator\Cli\Screen;
use Xn\Orchestrator\Cli\ScreenHandler;
use Xn\Orchestrator\Cli\ScreenResult;

use function Laravel\Prompts\select;

final class CategoryScreen implements ScreenHandler
{
    private const SEARCH = 'Search packages';

    private const CART = 'View cart';

    private const INSTALL = 'Finish and install';

    private const QUIT = 'Quit';

    public function handle(CliContext $context, ?string $payload = null): ScreenResult
    {
        $cartCount = $context->cart->count();

        $context->io->newLine();
        $context->io->info($cartCount === 0
            ? 'No packages selected yet'
            : "{$cartCount} package".($cartCount > 1 ? 's' : '').' selected');
        $context->io->line('↑↓ Navigate   Enter Confirm   Or type the option number');

        $choice = select(
            label: 'Select packages to install',
            options: $this->options($context),
        );

        return match ($choice) {
            self::SEARCH => ScreenResult::goto(Screen::Search),
            self::CART => ScreenResult::goto(Screen::Cart),
            self::INSTALL => ScreenResult::goto(Screen::Review),
            self::QUIT => ScreenResult::success(),
            default => ScreenResult::goto(Screen::Packages, $choice),
        };
    }

    /**
     * @return list<string>
     */
    private function options(CliContext $context): array
    {
        $categories = collect($context->catalog->getAll())
            ->map(fn (PackageDefinition $package) => $package->category)
            ->unique()
            ->values()
            ->all();

        return [
            ...$categories,
            self::SEARCH,
            self::CART,
            self::INSTALL,
            self::QUIT,
        ];
    }
}
