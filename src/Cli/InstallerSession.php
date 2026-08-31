<?php

namespace Xn\Orchestrator\Cli;

use Xn\Orchestrator\Cli\Screens\CartScreen;
use Xn\Orchestrator\Cli\Screens\CategoriesScreen;
use Xn\Orchestrator\Cli\Screens\MainMenuScreen;
use Xn\Orchestrator\Cli\Screens\PackageScreen;
use Xn\Orchestrator\Cli\Screens\ReviewScreen;
use Xn\Orchestrator\Cli\Screens\SearchScreen;
use Xn\Orchestrator\Support\DependencyResolver;
use Xn\Orchestrator\Support\ProcessRunner;

final class InstallerSession
{
    private ?string $category = null;

    public function __construct(
        private readonly CliContext $context,
        private readonly DependencyResolver $resolver,
        private readonly ProcessRunner $processRunner,
    ) {}

    public function run(): int
    {
        if ($this->context->catalog->getAll() === []) {
            $this->context->io->warn('The catalog is empty. Nothing to install.');

            return 0;
        }

        $current = Screen::Menu;

        while (true) {
            $handler = $this->handlerFor($current);

            $result = $handler->handle($this->context, $current === Screen::Packages ? $this->category : null);

            if ($result->exits()) {
                return (int) $result->exitCode;
            }

            if ($result->backToScreen !== null) {
                $current = $result->backToScreen;

                continue;
            }

            if ($result->next === Screen::Packages && $result->payload !== null) {
                $this->category = $result->payload;
            }

            $current = $result->next ?? Screen::Menu;
        }
    }

    private function handlerFor(Screen $screen): ScreenHandler
    {
        return match ($screen) {
            Screen::Categories => new CategoriesScreen,
            Screen::Packages => new PackageScreen,
            Screen::Search => new SearchScreen,
            Screen::Cart => new CartScreen,
            Screen::Review => new ReviewScreen($this->resolver, $this->processRunner),
            Screen::Menu => new MainMenuScreen,
        };
    }
}
