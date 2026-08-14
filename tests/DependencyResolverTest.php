<?php

use Xn\Orchestrator\Catalog\PackageDefinition;
use Xn\Orchestrator\Exceptions\CircularDependencyException;
use Xn\Orchestrator\Support\DependencyResolver;

function package(string $name, array $dependsOn = [], array $conflictsWith = []): PackageDefinition
{
    return new PackageDefinition(
        name: $name,
        category: 'Test',
        tags: [],
        installSteps: [],
        dependsOn: $dependsOn,
        conflictsWith: $conflictsWith,
    );
}

it('resolves a simple dependency before its dependant', function () {
    $a = package('a', dependsOn: ['b']);
    $b = package('b');

    expect((new DependencyResolver)->resolveOrder([$a, $b]))
        ->toBe([$b, $a]);
});

it('resolves a chain of dependencies', function () {
    $a = package('a', dependsOn: ['b']);
    $b = package('b', dependsOn: ['c']);
    $c = package('c');

    expect((new DependencyResolver)->resolveOrder([$a, $b, $c]))
        ->toBe([$c, $b, $a]);
});

it('preserves the given order when there are no dependencies', function () {
    $a = package('a');
    $b = package('b');

    expect((new DependencyResolver)->resolveOrder([$a, $b]))
        ->toBe([$a, $b]);
});

it('ignores dependencies that are not part of the cart', function () {
    $a = package('a', dependsOn: ['unknown']);
    $b = package('b');

    expect((new DependencyResolver)->resolveOrder([$a, $b]))
        ->toBe([$a, $b]);
});

it('throws on a circular dependency', function () {
    $a = package('a', dependsOn: ['b']);
    $b = package('b', dependsOn: ['a']);

    (new DependencyResolver)->resolveOrder([$a, $b]);
})->throws(CircularDependencyException::class, 'Circular dependency detected involving "a"');

it('detects a direct conflict between two packages', function () {
    $a = package('a', conflictsWith: ['b']);
    $b = package('b');

    expect((new DependencyResolver)->findConflicts([$a, $b]))
        ->toBe([['a', 'b']]);
});

it('detects a conflict declared on either side', function () {
    $a = package('a');
    $b = package('b', conflictsWith: ['a']);

    expect((new DependencyResolver)->findConflicts([$a, $b]))
        ->toBe([['b', 'a']]);
});

it('does not report conflicts when the cart is empty', function () {
    expect((new DependencyResolver)->findConflicts([]))->toBe([]);
});

it('finds dependencies missing from the cart', function () {
    $a = package('a', dependsOn: ['b', 'c']);
    $b = package('b');

    expect((new DependencyResolver)->findMissingDependencies([$a, $b]))
        ->toBe(['c']);
});

it('reports no missing dependencies when all are present', function () {
    $a = package('a', dependsOn: ['b']);
    $b = package('b');

    expect((new DependencyResolver)->findMissingDependencies([$a, $b]))->toBe([]);
});
