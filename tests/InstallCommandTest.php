<?php

use Mockery\MockInterface;
use Xn\Orchestrator\Catalog\CatalogRepositoryInterface;
use Xn\Orchestrator\Catalog\PackageDefinition;
use Xn\Orchestrator\Exceptions\PackageInstallationException;
use Xn\Orchestrator\Support\ProcessResult;
use Xn\Orchestrator\Support\ProcessRunner;

const MAIN_MENU = ['Browse the catalog', 'View cart', 'Finish and install', 'Quit'];
const CATEGORY_MENU = ['Authentication', 'Debugging', '← Back to main menu'];
const AUTH_PACKAGES = ['laravel/sanctum'];
const DEBUG_PACKAGES = ['debug/inspector'];

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
    $catalog->shouldReceive('findByCategory')->with('Authentication')->andReturn([$sanctum]);
    $catalog->shouldReceive('findByCategory')->with('Debugging')->andReturn([$debugger]);

    return $catalog;
}

function fakeRunner(?callable $behaviour = null): MockInterface
{
    $runner = Mockery::mock(ProcessRunner::class);

    $behaviour ??= fn () => new ProcessResult(success: true, output: 'step output', exitCode: 0);

    $runner->shouldReceive('runOrThrow')->andReturnUsing($behaviour);

    return $runner;
}

function recordedRunner(array &$commands): MockInterface
{
    $commands = [];

    $runner = Mockery::mock(ProcessRunner::class);

    $runner->shouldReceive('runOrThrow')->andReturnUsing(
        function (string $command, ?string $pendingMessage = null) use (&$commands): ProcessResult {
            $commands[] = $command;

            return new ProcessResult(success: true, output: 'step output', exitCode: 0);
        }
    );

    return $runner;
}

it('installs packages selected from multiple categories', function () {
    $this->app->instance(CatalogRepositoryInterface::class, fakeCatalog());

    $commands = [];
    $this->app->instance(ProcessRunner::class, recordedRunner($commands));

    $this->artisan('x:install')
        ->expectsChoice('What do you want to do?', 'Browse the catalog', MAIN_MENU)
        ->expectsChoice('Select a category', 'Authentication', CATEGORY_MENU)
        ->expectsChoice('Select packages in Authentication', ['laravel/sanctum'], AUTH_PACKAGES)
        ->expectsChoice('Select a category', 'Debugging', CATEGORY_MENU)
        ->expectsChoice('Select packages in Debugging', ['debug/inspector'], DEBUG_PACKAGES)
        ->expectsChoice('Select a category', '← Back to main menu', CATEGORY_MENU)
        ->expectsChoice('What do you want to do?', 'Finish and install', MAIN_MENU)
        ->expectsOutputToContain('echo installing sanctum')
        ->expectsOutputToContain('echo configuring sanctum')
        ->expectsOutputToContain('echo inspecting')
        ->expectsConfirmation('Proceed with installation?', 'yes')
        ->expectsOutputToContain('installed successfully')
        ->assertExitCode(0);

    expect($commands)->toBe([
        'echo installing sanctum',
        'echo configuring sanctum',
        'echo inspecting',
    ]);
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
        ->expectsChoice('What do you want to do?', 'Browse the catalog', MAIN_MENU)
        ->expectsChoice('Select a category', 'Authentication', CATEGORY_MENU)
        ->expectsChoice('Select packages in Authentication', ['laravel/sanctum'], AUTH_PACKAGES)
        ->expectsChoice('Select a category', '← Back to main menu', CATEGORY_MENU)
        ->expectsChoice('What do you want to do?', 'Finish and install', MAIN_MENU)
        ->expectsConfirmation('Proceed with installation?', 'yes')
        ->expectsOutputToContain('something went terribly wrong')
        ->expectsOutputToContain('echo installing sanctum')
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
        ->expectsChoice('What do you want to do?', 'Browse the catalog', MAIN_MENU)
        ->expectsChoice('Select a category', 'Authentication', CATEGORY_MENU)
        ->expectsChoice('Select packages in Authentication', ['laravel/sanctum'], AUTH_PACKAGES)
        ->expectsChoice('Select a category', '← Back to main menu', CATEGORY_MENU)
        ->expectsChoice('What do you want to do?', 'Finish and install', MAIN_MENU)
        ->expectsConfirmation('Proceed with installation?', 'yes')
        ->expectsOutputToContain('boom')
        ->assertExitCode(1);

    expect($calls)->toBe(2);
});

it('cancels the installation when the user declines', function () {
    $this->app->instance(CatalogRepositoryInterface::class, fakeCatalog());
    $this->app->instance(ProcessRunner::class, fakeRunner());

    $this->artisan('x:install')
        ->expectsChoice('What do you want to do?', 'Browse the catalog', MAIN_MENU)
        ->expectsChoice('Select a category', 'Authentication', CATEGORY_MENU)
        ->expectsChoice('Select packages in Authentication', ['laravel/sanctum'], AUTH_PACKAGES)
        ->expectsChoice('Select a category', '← Back to main menu', CATEGORY_MENU)
        ->expectsChoice('What do you want to do?', 'Finish and install', MAIN_MENU)
        ->expectsConfirmation('Proceed with installation?', 'no')
        ->expectsOutputToContain('Installation cancelled.')
        ->expectsChoice('What do you want to do?', 'Quit', MAIN_MENU)
        ->assertExitCode(0);
});

it('removes a package from the cart', function () {
    $this->app->instance(CatalogRepositoryInterface::class, fakeCatalog());
    $this->app->instance(ProcessRunner::class, fakeRunner());

    $this->artisan('x:install')
        ->expectsChoice('What do you want to do?', 'Browse the catalog', MAIN_MENU)
        ->expectsChoice('Select a category', 'Authentication', CATEGORY_MENU)
        ->expectsChoice('Select packages in Authentication', ['laravel/sanctum'], AUTH_PACKAGES)
        ->expectsChoice('Select a category', '← Back to main menu', CATEGORY_MENU)
        ->expectsChoice('What do you want to do?', 'View cart', MAIN_MENU)
        ->expectsOutputToContain('Packages in the cart (1)')
        ->expectsChoice('Select a package to remove', 'laravel/sanctum', ['laravel/sanctum', '← Back to main menu'])
        ->expectsOutputToContain('Removed laravel/sanctum from the cart.')
        ->expectsChoice('What do you want to do?', 'Finish and install', MAIN_MENU)
        ->expectsOutputToContain('Your cart is empty. Add packages first.')
        ->expectsChoice('What do you want to do?', 'Quit', MAIN_MENU)
        ->assertExitCode(0);
});

it('shows a warning and exits cleanly when the catalog is empty', function () {
    $catalog = Mockery::mock(CatalogRepositoryInterface::class);
    $catalog->shouldReceive('getAll')->andReturn([]);

    $this->app->instance(CatalogRepositoryInterface::class, $catalog);

    $this->artisan('x:install')
        ->expectsOutputToContain('The catalog is empty. Nothing to install.')
        ->assertExitCode(0);
});
