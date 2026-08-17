<?php

namespace Xn\Orchestrator\Support;

use Composer\Semver\Semver;
use Xn\Orchestrator\Catalog\PackageDefinition;

final class CompatibilityChecker
{
    public function __construct(
        private readonly string $laravelVersion,
        private readonly string $phpVersion,
    ) {}

    public function isCompatible(PackageDefinition $package): bool
    {
        return $this->supportsLaravel($package) && $this->supportsPhp($package);
    }

    private function supportsLaravel(PackageDefinition $package): bool
    {
        if ($package->supportedLaravel === []) {
            return true;
        }

        foreach ($package->supportedLaravel as $constraint) {
            if ($this->satisfies($this->laravelVersion, $constraint)) {
                return true;
            }
        }

        return false;
    }

    private function supportsPhp(PackageDefinition $package): bool
    {
        if ($package->supportedPhp === '') {
            return true;
        }

        return $this->satisfies($this->phpVersion, $package->supportedPhp);
    }

    private function satisfies(string $version, string $constraint): bool
    {
        try {
            return Semver::satisfies($version, $constraint);
        } catch (\Throwable) {
            return false;
        }
    }
}
