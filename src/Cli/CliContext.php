<?php

namespace Xn\Orchestrator\Cli;

use Xn\Orchestrator\Cart\InstallationCart;
use Xn\Orchestrator\Catalog\CatalogRepositoryInterface;
use Xn\Orchestrator\Support\CompatibilityChecker;

final class CliContext
{
    public function __construct(
        public readonly CatalogRepositoryInterface $catalog,
        public readonly InstallationCart $cart,
        public readonly CompatibilityChecker $compatibility,
        public readonly CliIO $io,
        public readonly bool $hideIncompatible,
        public readonly bool $dryRun,
    ) {}
}
