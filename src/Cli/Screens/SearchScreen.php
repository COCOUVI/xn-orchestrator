<?php

namespace Xn\Orchestrator\Cli\Screens;

use Xn\Orchestrator\Catalog\PackageDefinition;
use Xn\Orchestrator\Cli\CliContext;
use Xn\Orchestrator\Cli\Screen;
use Xn\Orchestrator\Cli\ScreenHandler;
use Xn\Orchestrator\Cli\ScreenResult;

use function Laravel\Prompts\search;

final class SearchScreen implements ScreenHandler
{
    public function handle(CliContext $context, ?string $payload = null): ScreenResult
    {
        $name = search(
            label: 'Search for a package',
            options: fn (string $value) => $this->matches($context, $value),
            hint: "Type to filter   ↑/↓ Navigate   Enter Select",
        );

        $package = $context->catalog->findByName($name);

        if ($package === null) {
            $context->io->error("Package '{$name}' was not found in the catalog.");

            return ScreenResult::goto(Screen::Menu);
        }

        $context->cart->add($package);
        $context->io->info("Added {$package->name} to the cart.");

        $count = $context->cart->count();
        $context->io->info("{$count} package".($count > 1 ? 's' : '').' selected');

        return ScreenResult::goto(Screen::Menu);
    }

    /**
     * @return list<string>
     */
    private function matches(CliContext $context, string $value): array
    {
        $needle = strtolower(trim($value));

        return collect($context->catalog->getAll())
            ->filter(
                fn (PackageDefinition $package) => $needle === ''
                    || str_contains(strtolower($package->name), $needle)
                    || str_contains(strtolower($package->category), $needle)
                    || collect($package->tags)->contains(
                        fn (string $tag) => str_contains(strtolower($tag), $needle),
                    )
            )
            ->map(fn (PackageDefinition $package) => $package->name)
            ->values()
            ->all();
    }
}
