<?php

namespace Xn\Orchestrator\Support;

use Xn\Orchestrator\Catalog\PackageDefinition;
use Xn\Orchestrator\Exceptions\CircularDependencyException;

final class DependencyResolver
{
    /**
     * Resolve the installation order of the cart via topological sort.
     *
     * @param  list<PackageDefinition>  $cart
     * @return list<PackageDefinition>
     *
     * @throws CircularDependencyException
     */
    public function resolveOrder(array $cart): array
    {
        $visited = [];
        $visiting = [];
        $sorted = [];
        $byName = collect($cart)->keyBy('name');

        $visit = function (PackageDefinition $pkg) use (&$visit, &$visited, &$visiting, &$sorted, $byName) {
            if (isset($visited[$pkg->name])) {
                return;
            }

            if (isset($visiting[$pkg->name])) {
                throw new CircularDependencyException($pkg->name);
            }

            $visiting[$pkg->name] = true;

            foreach ($pkg->dependsOn as $depName) {
                if ($dep = $byName->get($depName)) {
                    $visit($dep);
                }
            }

            unset($visiting[$pkg->name]);
            $visited[$pkg->name] = true;
            $sorted[] = $pkg;
        };

        foreach ($cart as $pkg) {
            $visit($pkg);
        }

        return $sorted;
    }

    /**
     * Return conflicting package name pairs present in the cart.
     *
     * @param  list<PackageDefinition>  $cart
     * @return list<array{string, string}>
     */
    public function findConflicts(array $cart): array
    {
        $conflicts = [];

        foreach ($cart as $index => $pkg) {
            foreach (array_slice($cart, $index + 1) as $other) {
                if (in_array($other->name, $pkg->conflictsWith, true)) {
                    $conflicts[] = [$pkg->name, $other->name];
                } elseif (in_array($pkg->name, $other->conflictsWith, true)) {
                    $conflicts[] = [$other->name, $pkg->name];
                }
            }
        }

        return $conflicts;
    }

    /**
     * Return the names of dependencies required by the cart but absent from it.
     *
     * @param  list<PackageDefinition>  $cart
     * @return list<string>
     */
    public function findMissingDependencies(array $cart): array
    {
        $names = collect($cart)->pluck('name')->all();

        return collect($cart)
            ->flatMap(fn (PackageDefinition $pkg) => $pkg->dependsOn)
            ->reject(fn (string $depName) => in_array($depName, $names, true))
            ->unique()
            ->values()
            ->all();
    }
}
