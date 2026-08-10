<?php

use Xn\Orchestrator\Cart\InstallationCart;
use Xn\Orchestrator\Catalog\PackageDefinition;

function cartPackage(string $name): PackageDefinition
{
    return new PackageDefinition(
        name: $name,
        category: 'Test',
        tags: [],
        installSteps: [],
    );
}

it('adds packages and keeps insertion order', function () {
    $cart = new InstallationCart;

    $cart->add(cartPackage('laravel/sanctum'));
    $cart->add(cartPackage('debug/inspector'));

    expect($cart->all())->toHaveCount(2)
        ->and($cart->names())->toBe(['laravel/sanctum', 'debug/inspector']);
});

it('ignores duplicate additions', function () {
    $cart = new InstallationCart;

    $cart->add(cartPackage('laravel/sanctum'));
    $cart->add(cartPackage('laravel/sanctum'));

    expect($cart->all())->toHaveCount(1)
        ->and($cart->count())->toBe(1);
});

it('removes a package by name', function () {
    $cart = new InstallationCart;

    $cart->add(cartPackage('laravel/sanctum'));
    $cart->add(cartPackage('debug/inspector'));
    $cart->remove('laravel/sanctum');

    expect($cart->all())->toHaveCount(1)
        ->and($cart->names())->toBe(['debug/inspector']);
});

it('returns an empty list when removing from an empty cart', function () {
    $cart = new InstallationCart;

    $cart->remove('laravel/sanctum');

    expect($cart->all())->toBe([]);
});
