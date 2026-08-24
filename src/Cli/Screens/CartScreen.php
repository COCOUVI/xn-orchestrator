<?php

namespace Xn\Orchestrator\Cli\Screens;

use Xn\Orchestrator\Cli\CliContext;
use Xn\Orchestrator\Cli\Screen;
use Xn\Orchestrator\Cli\ScreenHandler;
use Xn\Orchestrator\Cli\ScreenResult;

use function Laravel\Prompts\select;

final class CartScreen implements ScreenHandler
{
    private const BACK = '← Back';

    public function handle(CliContext $context, ?string $payload = null): ScreenResult
    {
        while ($context->cart->count() > 0) {
            $count = $context->cart->count();
            $context->io->info("Packages in the cart ({$count})");

            $choice = select(
                label: 'Select a package to remove',
                options: [...$context->cart->names(), self::BACK],
                hint: '↑↓ Navigate   Enter Confirm',
            );

            if ($choice === self::BACK) {
                return ScreenResult::goto(Screen::Categories);
            }

            $context->cart->remove($choice);
            $context->io->info("Removed {$choice} from the cart.");
        }

        $context->io->info('Your cart is empty.');

        return ScreenResult::goto(Screen::Categories);
    }
}
