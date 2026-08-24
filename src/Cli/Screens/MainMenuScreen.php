<?php

namespace Xn\Orchestrator\Cli\Screens;

use Xn\Orchestrator\Cli\CliContext;
use Xn\Orchestrator\Cli\Screen;
use Xn\Orchestrator\Cli\ScreenHandler;
use Xn\Orchestrator\Cli\ScreenResult;

use function Laravel\Prompts\select;

final class MainMenuScreen implements ScreenHandler
{
    private const BROWSE = 'Browse categories';

    private const SEARCH = 'Search packages';

    private const CART = 'View cart';

    private const INSTALL = 'Finish and install';

    private const QUIT = 'Quit';

    public function handle(CliContext $context, ?string $payload = null): ScreenResult
    {
        $count = $context->cart->count();

        $context->io->newLine();
        $context->io->info('XN Orchestrator');
        $context->io->line("{$count} package".($count > 1 ? 's' : '').' selected');

        $choice = select(
            label: 'Main Menu',
            options: [self::BROWSE, self::SEARCH, self::CART, self::INSTALL, self::QUIT],
            hint: '↑/↓ Navigate   Enter Select',
        );

        return match ($choice) {
            self::BROWSE => ScreenResult::goto(Screen::Categories),
            self::SEARCH => ScreenResult::goto(Screen::Search),
            self::CART => ScreenResult::goto(Screen::Cart),
            self::INSTALL => ScreenResult::goto(Screen::Review),
            default => ScreenResult::success(),
        };
    }
}
