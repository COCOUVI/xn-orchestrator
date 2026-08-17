<?php

namespace Xn\Orchestrator\Catalog;

use Illuminate\Support\Facades\Log;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;

final class YamlCatalog implements CatalogRepositoryInterface
{
    /** @var list<PackageDefinition>|null */
    private ?array $packages = null;

    public function __construct(private readonly string $directory) {}

    /** @return list<PackageDefinition> */
    public function getAll(): array
    {
        if ($this->packages === null) {
            $this->packages = $this->load();
        }

        return $this->packages;
    }

    public function findByName(string $name): ?PackageDefinition
    {
        foreach ($this->getAll() as $package) {
            if ($package->name === $name) {
                return $package;
            }
        }

        return null;
    }

    /** @return list<PackageDefinition> */
    public function findByCategory(string $category): array
    {
        return array_values(array_filter(
            $this->getAll(),
            fn (PackageDefinition $package) => $package->category === $category,
        ));
    }

    /** @return list<PackageDefinition> */
    private function load(): array
    {
        $packages = [];

        foreach (glob($this->directory.'/*.yaml') ?: [] as $file) {
            $package = $this->loadFromFile($file);

            if ($package !== null) {
                $packages[] = $package;
            }
        }

        return $packages;
    }

    private function loadFromFile(string $file): ?PackageDefinition
    {
        try {
            $data = Yaml::parseFile($file);
        } catch (ParseException $exception) {
            Log::warning("Skipping malformed catalog file [{$file}]: {$exception->getMessage()}");

            return null;
        }

        if (! is_array($data)) {
            Log::warning("Skipping catalog file [{$file}]: expected a map of package data.");

            return null;
        }

        $name = $data['name'] ?? null;
        $category = $data['category'] ?? null;
        $install = $data['install'] ?? null;

        if (! is_string($name) || $name === '' || ! is_string($category) || $category === '') {
            Log::warning("Skipping catalog file [{$file}]: missing or invalid 'name' or 'category'.");

            return null;
        }

        $installSteps = $this->stringList($install);

        if ($installSteps === []) {
            Log::warning("Skipping catalog file [{$file}]: missing or invalid 'install' steps.");

            return null;
        }

        $supported = is_array($data['supported'] ?? null) ? $data['supported'] : [];

        return new PackageDefinition(
            name: $name,
            category: $category,
            tags: $this->stringList($data['tags'] ?? []),
            installSteps: $installSteps,
            supportedLaravel: $this->stringList($supported['laravel'] ?? []),
            supportedPhp: is_string($supported['php'] ?? null) ? $supported['php'] : '',
            dependsOn: $this->stringList($data['depends_on'] ?? []),
            conflictsWith: $this->stringList($data['conflicts_with'] ?? []),
        );
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, 'is_string'));
    }
}
