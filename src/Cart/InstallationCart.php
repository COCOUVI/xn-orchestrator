<?php

namespace Xn\Orchestrator\Cart;

use Xn\Orchestrator\Catalog\PackageDefinition;

final class InstallationCart
{
    /** @var list<PackageDefinition> */
    private array $items = [];

    public function add(PackageDefinition $package): void
    {
        if ($this->has($package->name)) {
            return;
        }

        $this->items[] = $package;
    }

    public function remove(string $name): void
    {
        $this->items = array_values(array_filter(
            $this->items,
            fn (PackageDefinition $package) => $package->name !== $name,
        ));
    }

    public function has(string $name): bool
    {
        foreach ($this->items as $package) {
            if ($package->name === $name) {
                return true;
            }
        }

        return false;
    }

    /** @return list<PackageDefinition> */
    public function all(): array
    {
        return $this->items;
    }

    /** @return list<string> */
    public function names(): array
    {
        return array_map(
            fn (PackageDefinition $package) => $package->name,
            $this->items,
        );
    }

    public function count(): int
    {
        return count($this->items);
    }
}
