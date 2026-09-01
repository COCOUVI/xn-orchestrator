<?php

use Xn\Orchestrator\Catalog\CatalogRepositoryInterface;
use Xn\Orchestrator\Catalog\PackageDefinition;
use Xn\Orchestrator\Catalog\YamlCatalog;

it('binds the catalog repository to the yaml implementation', function () {
    expect(app(CatalogRepositoryInterface::class))
        ->toBeInstanceOf(YamlCatalog::class);
});

it('returns at least six packages from the catalog', function () {
    $packages = app(CatalogRepositoryInterface::class)->getAll();

    expect($packages)->toHaveCount(25);

    foreach ($packages as $package) {
        expect($package)->toBeInstanceOf(PackageDefinition::class);
    }
});

it('always returns composer package names', function () {
    $packages = app(CatalogRepositoryInterface::class)->getAll();

    collect($packages)->each(function (PackageDefinition $package) {
        expect($package->name)->toMatch('/^[a-z0-9\-]+\/[a-z0-9\-]+$/');
    });
});

it('finds a package by its composer name', function () {
    $package = app(CatalogRepositoryInterface::class)->findByName('spatie/laravel-permission');

    expect($package)
        ->toBeInstanceOf(PackageDefinition::class)
        ->category->toBe('Authorization');
});

it('returns null when the package is not found', function () {
    expect(app(CatalogRepositoryInterface::class)->findByName('vendor/unknown'))
        ->toBeNull();
});

it('filters packages by category', function () {
    $packages = app(CatalogRepositoryInterface::class)->findByCategory('Authentication');

    expect($packages)->toHaveCount(4);

    foreach ($packages as $package) {
        expect($package)
            ->toBeInstanceOf(PackageDefinition::class)
            ->category->toBe('Authentication');
    }
});

it('returns an empty list for an unknown category', function () {
    expect(app(CatalogRepositoryInterface::class)->findByCategory('unknown'))
        ->toBe([]);
});
