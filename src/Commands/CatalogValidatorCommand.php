<?php

namespace Xn\Orchestrator\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Yaml\Yaml;

class CatalogValidatorCommand extends Command
{
    public $signature = 'x:catalog:validate {path?}';

    public $description = 'Validate catalog YAML files';

    public function handle(): int
    {
        $path = $this->argument('path') ?? null;

        $catalogPath = $path ?? config('xn-orchestrator.catalog_path') ?? base_path('resources/catalog');

        if (! is_dir($catalogPath)) {
            $this->components->error("Catalog directory [{$catalogPath}] not found.");

            return self::FAILURE;
        }

        $yamlFiles = glob("{$catalogPath}/*.yaml");

        if ($yamlFiles === []) {
            $this->components->info("No YAML catalog files found in [{$catalogPath}].");

            return self::SUCCESS;
        }

        $validFiles = [];
        $invalidFiles = [];

        foreach ($yamlFiles as $file) {
            $result = $this->validateFile($file);

            if ($result === true) {
                $validFiles[] = $file;
            } else {
                $invalidFiles[$file] = $result;
            }
        }

        $this->displayResults($validFiles, $invalidFiles);

        return $invalidFiles === [] ? self::SUCCESS : self::FAILURE;
    }

    private function validateFile(string $file): mixed
    {
        $data = @Yaml::parseFile($file);

        if ($data === false) {
            return 'Malformed YAML file';
        }

        if (! is_array($data)) {
            return 'Expected a map of package data';
        }

        $requiredFields = ['name', 'category', 'install', 'supported'];

        foreach ($requiredFields as $field) {
            if (! array_key_exists($field, $data)) {
                return "Missing required field: '{$field}'";
            }
        }

        if (! is_string($data['name'] ?? null) || $data['name'] === '') {
            return 'Invalid or missing "name" field';
        }

        if (! is_string($data['category'] ?? null) || $data['category'] === '') {
            return 'Invalid or missing "category" field';
        }

        $install = $data['install'] ?? null;

        if (! is_array($install) || count($install) === 0) {
            return 'Invalid or empty "install" steps';
        }

        $supported = $data['supported'] ?? [];

        if (! isset($supported['laravel']) || ! is_array($supported['laravel'])) {
            return 'Missing or invalid "supported.laravel"';
        }

        if (! isset($supported['php']) || ! is_string($supported['php'])) {
            return 'Missing or invalid "supported.php"';
        }

        return true;
    }

    private function displayResults(array $validFiles, array $invalidFiles): void
    {
        $this->newLine();
        $this->components->info('Validation results:');

        $validFilesCount = count($validFiles);
        $invalidFilesCount = count($invalidFiles);

        $this->components->info("  Valid ({$validFilesCount}):");

        foreach ($validFiles as $file) {
            $this->components->info("    - {$file}");
        }

        if ($invalidFilesCount > 0) {
            $this->components->info("  Invalid ({$invalidFilesCount}):");

            foreach ($invalidFiles as $file => $errors) {
                $this->components->error("    - {$file}:");

                foreach (is_array($errors) ? $errors : [$errors] as $error) {
                    $this->components->error("      * {$error}");
                }
            }
        }

        $this->newLine();
    }
}
