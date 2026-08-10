<?php

use Mockery\MockInterface;
use Xn\Orchestrator\Catalog\CatalogRepositoryInterface;
use Xn\Orchestrator\Catalog\PackageDefinition;
use Xn\Orchestrator\Exceptions\PackageInstallationException;
use Xn\Orchestrator\Support\ProcessResult;
use Xn\Orchestrator\Support\ProcessRunner;

function fakeCatalog(): CatalogRepositoryInterface
{
    $catalog = Mockery::mock(CatalogRepositoryInterface::class);

    $sanctum = new PackageDefinition(
        name: 'laravel/sanctum',
        category: 'Authentication',
        tags: ['api', 'token'],
        installSteps: ['echo installing sanctum', 'echo configuring sanctum'],
    );

    $debugger = new PackageDefinition(
        name: 'debug/inspector',
        category: 'Debugging',
        tags: ['debug'],
        installSteps: ['echo inspecting'],
    );

    $catalog->shouldReceive('getAll')->andReturn([$sanctum, $debugger]);
    $catalog->shouldReceive('findByName')->with('laravel/sanctum')->andReturn($sanctum);
    $catalog->shouldReceive('findByName')->with('debug/inspector')->andReturn($debugger);

    return $catalog;
}

function fakeRunner(?callable $behaviour = null): MockInterface
{
    $runner = Mockery::mock(ProcessRunner::class);

    $behaviour ??= fn () => new ProcessResult(success: true, output: 'step output', exitCode: 0);

    $runner->shouldReceive('runOrThrow')->andReturnUsing($behaviour);

    return $runner;
}

it('installs the selected package step by step', function () {
    $this->app->instance(CatalogRepositoryInterface::class, fakeCatalog());
    $this->app->instance(ProcessRunner::class, fakeRunner());

    $this->artisan('x:install')
        ->expectsChoice('Select a package to install', 'laravel/sanctum', ['laravel/sanctum', 'debug/inspector'])
        ->expectsConfirmation('Proceed with installation?', 'yes')
        ->expectsOutputToContain('echo installing sanctum')
        ->expectsOutputToContain('echo configuring sanctum')
        ->expectsOutputToContain('installed successfully')
        ->assertExitCode(0);
});

it('reports the failing step with the raw error message', function () {
    $this->app->instance(CatalogRepositoryInterface::class, fakeCatalog());
    $this->app->instance(ProcessRunner::class, fakeRunner(
        fn () => throw new PackageInstallationException(
            'echo installing sanctum',
            new ProcessResult(success: false, output: 'something went terribly wrong', exitCode: 1),
        ),
    ));

    $this->artisan('x:install')
        ->expectsChoice('Select a package to install', 'laravel/sanctum', ['laravel/sanctum', 'debug/inspector'])
        ->expectsConfirmation('Proceed with installation?', 'yes')
        ->expectsOutputToContain('something went terribly wrong')
        ->expectsOutputToContain('echo installing sanctum')
        ->expectsOutputToContain('echo configuring sanctum')
        ->assertExitCode(1);
});

it('stops executing the remaining steps after a failure', function () {
    $calls = 0;

    $this->app->instance(CatalogRepositoryInterface::class, fakeCatalog());
    $this->app->instance(ProcessRunner::class, fakeRunner(function () use (&$calls) {
        $calls++;

        if ($calls === 1) {
            return new ProcessResult(success: true, output: 'ok', exitCode: 0);
        }

        throw new PackageInstallationException(
            'echo configuring sanctum',
            new ProcessResult(success: false, output: 'boom', exitCode: 1),
        );
    }));

    $this->artisan('x:install')
        ->expectsChoice('Select a package to install', 'laravel/sanctum', ['laravel/sanctum', 'debug/inspector'])
        ->expectsConfirmation('Proceed with installation?', 'yes')
        ->expectsOutputToContain('boom')
        ->assertExitCode(1);

    expect($calls)->toBe(2);
});

it('cancels the installation when the user declines', function () {
    $this->app->instance(CatalogRepositoryInterface::class, fakeCatalog());
    $this->app->instance(ProcessRunner::class, fakeRunner());

    $this->artisan('x:install')
        ->expectsChoice('Select a package to install', 'laravel/sanctum', ['laravel/sanctum', 'debug/inspector'])
        ->expectsConfirmation('Proceed with installation?', 'no')
        ->expectsOutputToContain('Installation cancelled.')
        ->assertExitCode(0);
});

it('shows a warning and exits cleanly when the catalog is empty', function () {
    $catalog = Mockery::mock(CatalogRepositoryInterface::class);
    $catalog->shouldReceive('getAll')->andReturn([]);

    $this->app->instance(CatalogRepositoryInterface::class, $catalog);

    $this->artisan('x:install')
        ->expectsOutputToContain('The catalog is empty.')
        ->assertExitCode(0);
});
