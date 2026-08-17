<?php

use Xn\Orchestrator\Catalog\PackageDefinition;
use Xn\Orchestrator\Support\CompatibilityChecker;

function compatiblePackage(array $overrides = []): PackageDefinition
{
    return new PackageDefinition(
        name: 'vendor/package',
        category: 'Test',
        tags: [],
        installSteps: ['echo install'],
        supportedLaravel: $overrides['supportedLaravel'] ?? [],
        supportedPhp: $overrides['supportedPhp'] ?? '',
        dependsOn: $overrides['dependsOn'] ?? [],
        conflictsWith: $overrides['conflictsWith'] ?? [],
    );
}

it('considers a package compatible when it declares no constraints', function () {
    $checker = new CompatibilityChecker('12.5.0', '8.3.0');

    expect($checker->isCompatible(compatiblePackage()))->toBeTrue();
});

it('accepts a package whose laravel constraint is satisfied', function () {
    $checker = new CompatibilityChecker('12.5.0', '8.3.0');

    expect($checker->isCompatible(compatiblePackage(['supportedLaravel' => ['^12.0', '^13.0']])))
        ->toBeTrue();
});

it('rejects a package whose laravel constraint is not satisfied', function () {
    $checker = new CompatibilityChecker('12.5.0', '8.3.0');

    expect($checker->isCompatible(compatiblePackage(['supportedLaravel' => ['^13.0']])))
        ->toBeFalse();
});

it('accepts a package whose php constraint is satisfied', function () {
    $checker = new CompatibilityChecker('12.5.0', '8.3.0');

    expect($checker->isCompatible(compatiblePackage(['supportedPhp' => '^8.3'])))
        ->toBeTrue();
});

it('rejects a package whose php constraint is not satisfied', function () {
    $checker = new CompatibilityChecker('12.5.0', '8.3.0');

    expect($checker->isCompatible(compatiblePackage(['supportedPhp' => '^9.0'])))
        ->toBeFalse();
});

it('requires both laravel and php constraints to be satisfied', function () {
    $checker = new CompatibilityChecker('12.5.0', '8.3.0');

    expect($checker->isCompatible(compatiblePackage(['supportedLaravel' => ['^12.0'], 'supportedPhp' => '^8.3'])))
        ->toBeTrue();

    expect($checker->isCompatible(compatiblePackage(['supportedLaravel' => ['^12.0'], 'supportedPhp' => '^9.0'])))
        ->toBeFalse();

    expect($checker->isCompatible(compatiblePackage(['supportedLaravel' => ['^11.0'], 'supportedPhp' => '^8.3'])))
        ->toBeFalse();
});

it('treats malformed constraints as incompatible', function () {
    $checker = new CompatibilityChecker('12.5.0', '8.3.0');

    expect($checker->isCompatible(compatiblePackage(['supportedPhp' => 'not-a-constraint'])))
        ->toBeFalse();
});
