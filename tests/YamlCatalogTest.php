<?php

use Xn\Orchestrator\Catalog\PackageDefinition;
use Xn\Orchestrator\Catalog\YamlCatalog;

function catalogFixturePath(): string
{
    return __DIR__.'/fixtures/catalog';
}

it('loads valid packages from yaml files', function () {
    $catalog = new YamlCatalog(catalogFixturePath());

    $packages = $catalog->getAll();

    expect($packages)->toHaveCount(1);

    $package = $packages[0];

    expect($package)
        ->toBeInstanceOf(PackageDefinition::class)
        ->name->toBe('vendor/package')
        ->category->toBe('Testing')
        ->tags->toBe(['test', 'demo'])
        ->installSteps->toBe(['echo installing vendor/package', 'echo finishing'])
        ->supportedLaravel->toBe(['^11.0', '^12.0', '^13.0'])
        ->supportedPhp->toBe('^8.3')
        ->dependsOn->toBe(['vendor/dependency'])
        ->conflictsWith->toBe(['vendor/other']);
});

it('skips yaml files missing required fields', function () {
    $catalog = new YamlCatalog(catalogFixturePath());

    expect(collect($catalog->getAll())->pluck('name')->all())
        ->toBe(['vendor/package']);
});

it('skips malformed yaml files', function () {
    $catalog = new YamlCatalog(catalogFixturePath());

    expect(collect($catalog->getAll())->pluck('name')->all())
        ->toBe(['vendor/package']);
});

it('finds a package loaded from yaml by name', function () {
    $catalog = new YamlCatalog(catalogFixturePath());

    expect($catalog->findByName('vendor/package'))
        ->toBeInstanceOf(PackageDefinition::class)
        ->category->toBe('Testing');
});

it('filters yaml packages by category', function () {
    $catalog = new YamlCatalog(catalogFixturePath());

    $packages = $catalog->findByCategory('Testing');

    expect($packages)->toHaveCount(1);
    expect($packages[0]->name)->toBe('vendor/package');
});

it('returns an empty catalog for a missing directory', function () {
    $catalog = new YamlCatalog(sys_get_temp_dir().'/non-existent-catalog-'.uniqid());

    expect($catalog->getAll())->toBe([]);
    expect($catalog->findByName('vendor/package'))->toBeNull();
    expect($catalog->findByCategory('Testing'))->toBe([]);
});

it('returns an empty catalog when the directory contains no yaml files', function () {
    $directory = sys_get_temp_dir().'/empty-catalog-'.uniqid();
    mkdir($directory);

    try {
        $catalog = new YamlCatalog($directory);

        expect($catalog->getAll())->toBe([]);
    } finally {
        rmdir($directory);
    }
});
