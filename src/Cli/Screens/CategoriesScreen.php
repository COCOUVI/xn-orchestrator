<?php

namespace Xn\Orchestrator\Cli\Screens;

use Xn\Orchestrator\Catalog\PackageDefinition;
use Xn\Orchestrator\Cli\CliContext;
use Xn\Orchestrator\Cli\Screen;
use Xn\Orchestrator\Cli\ScreenHandler;
use Xn\Orchestrator\Cli\ScreenResult;

use function Laravel\Prompts\select;

final class CategoriesScreen implements ScreenHandler
{
    private const BACK = 'Back to main menu';

    public function handle(CliContext $context, ?string $payload = null): ScreenResult
    {
        $categories = collect($context->catalog->getAll())
            ->map(fn (PackageDefinition $package) => $package->category)
            ->unique()
            ->sort()
            ->values()
            ->all();

        $category = select(
            label: 'Select a category',
            options: [...$categories, self::BACK],
            hint: '↑/↓ Navigate   Enter Select',
        );

        if ($category === self::BACK) {
            return ScreenResult::goto(Screen::Menu);
        }

        return ScreenResult::goto(Screen::Packages, $category);
    }
}
