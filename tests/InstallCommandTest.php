<?php

use Mockery\MockInterface;
use Xn\Orchestrator\Catalog\CatalogRepositoryInterface;
use Xn\Orchestrator\Catalog\PackageDefinition;
use Xn\Orchestrator\Exceptions\PackageInstallationException;
use Xn\Orchestrator\Support\ProcessResult;
use Xn\Orchestrator\Support\ProcessRunner;

const MAIN_MENU = ['Browse the catalog', 'Search packages', 'View cart', 'Finish and install', 'Quit'];
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

it('searches for a package by keyword and adds it to the cart', function () {
    $catalog = Mockery::mock(CatalogRepositoryInterface::class);

    $sanctum = new PackageDefinition(
        name: 'laravel/sanctum',
        category: 'Authentication',
        tags: ['api', 'token'],
        installSteps: ['echo installing sanctum'],
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

    $this->app->instance(CatalogRepositoryInterface::class, $catalog);

    $commands = [];
    $this->app->instance(ProcessRunner::class, recordedRunner($commands));

    $this->artisan('x:install')
        ->expectsChoice('What do you want to do?', 'Search packages', MAIN_MENU)
        ->expectsSearch('Search for a package', 'laravel/sanctum', 'sanct', ['laravel/sanctum'])
        ->expectsOutputToContain('Added laravel/sanctum to the cart.')
        ->expectsChoice('What do you want to do?', 'Finish and install', MAIN_MENU)
        ->expectsConfirmation('Proceed with installation?', 'yes')
        ->expectsOutputToContain('echo installing sanctum')
        ->assertExitCode(0);

    expect($commands)->toBe(['echo installing sanctum']);
});

it('blocks installation when the cart contains conflicting packages', function () {
    $catalog = Mockery::mock(CatalogRepositoryInterface::class);

    $breeze = new PackageDefinition(
        name: 'laravel/breeze',
        category: 'Authentication',
        tags: ['auth'],
        installSteps: ['echo installing breeze'],
        conflictsWith: ['laravel/jetstream'],
    );

    $jetstream = new PackageDefinition(
        name: 'laravel/jetstream',
        category: 'Authentication',
        tags: ['auth', 'teams'],
        installSteps: ['echo installing jetstream'],
        conflictsWith: ['laravel/breeze'],
    );

    $catalog->shouldReceive('getAll')->andReturn([$breeze, $jetstream]);
    $catalog->shouldReceive('findByCategory')->with('Authentication')->andReturn([$breeze, $jetstream]);

    $this->app->instance(CatalogRepositoryInterface::class, $catalog);

    $this->app->instance(ProcessRunner::class, fakeRunner());

    $this->artisan('x:install')
        ->expectsChoice('What do you want to do?', 'Browse the catalog', MAIN_MENU)
        ->expectsChoice('Select a category', 'Authentication', ['Authentication', '← Back to main menu'])
        ->expectsChoice('Select packages in Authentication', ['laravel/breeze', 'laravel/jetstream'], ['laravel/breeze', 'laravel/jetstream'])
        ->expectsChoice('Select a category', '← Back to main menu', ['Authentication', '← Back to main menu'])
        ->expectsChoice('What do you want to do?', 'Finish and install', MAIN_MENU)
        ->expectsOutputToContain('laravel/breeze conflicts with laravel/jetstream')
        ->expectsOutputToContain('Remove one of the conflicting packages from the cart and try again.')
        ->expectsChoice('What do you want to do?', 'Quit', MAIN_MENU)
        ->assertExitCode(0);
});

it('installs a missing dependency first and asks before adding it', function () {
    $catalog = Mockery::mock(CatalogRepositoryInterface::class);

    $filament = new PackageDefinition(
        name: 'filament/filament',
        category: 'Admin Panels',
        tags: ['filament'],
        installSteps: ['echo installing filament'],
        dependsOn: ['spatie/laravel-permission'],
    );

    $permission = new PackageDefinition(
        name: 'spatie/laravel-permission',
        category: 'Authorization',
        tags: ['permissions'],
        installSteps: ['echo installing permission'],
    );

    $catalog->shouldReceive('getAll')->andReturn([$filament, $permission]);
    $catalog->shouldReceive('findByCategory')->with('Admin Panels')->andReturn([$filament]);
    $catalog->shouldReceive('findByCategory')->with('Authorization')->andReturn([$permission]);
    $catalog->shouldReceive('findByName')->with('spatie/laravel-permission')->andReturn($permission);

    $this->app->instance(CatalogRepositoryInterface::class, $catalog);

    $commands = [];
    $this->app->instance(ProcessRunner::class, recordedRunner($commands));

    $this->artisan('x:install')
        ->expectsChoice('What do you want to do?', 'Browse the catalog', MAIN_MENU)
        ->expectsChoice('Select a category', 'Admin Panels', ['Admin Panels', 'Authorization', '← Back to main menu'])
        ->expectsChoice('Select packages in Admin Panels', ['filament/filament'], ['filament/filament'])
        ->expectsChoice('Select a category', '← Back to main menu', ['Admin Panels', 'Authorization', '← Back to main menu'])
        ->expectsChoice('What do you want to do?', 'Finish and install', MAIN_MENU)
        ->expectsConfirmation('spatie/laravel-permission is required. Add it to the cart?', 'yes')
        ->expectsConfirmation('Proceed with installation?', 'yes')
        ->assertExitCode(0);

    expect($commands)->toBe([
        'echo installing permission',
        'echo installing filament',
    ]);
});

it('warns about an unavailable dependency and continues', function () {
    $catalog = Mockery::mock(CatalogRepositoryInterface::class);

    $package = new PackageDefinition(
        name: 'some/package',
        category: 'Test',
        tags: [],
        installSteps: ['echo installing some/package'],
        dependsOn: ['missing/dependency'],
    );

    $catalog->shouldReceive('getAll')->andReturn([$package]);
    $catalog->shouldReceive('findByCategory')->with('Test')->andReturn([$package]);
    $catalog->shouldReceive('findByName')->with('missing/dependency')->andReturn(null);

    $this->app->instance(CatalogRepositoryInterface::class, $catalog);
    $this->app->instance(ProcessRunner::class, fakeRunner());

    $this->artisan('x:install')
        ->expectsChoice('What do you want to do?', 'Browse the catalog', MAIN_MENU)
        ->expectsChoice('Select a category', 'Test', ['Test', '← Back to main menu'])
        ->expectsChoice('Select packages in Test', ['some/package'], ['some/package'])
        ->expectsChoice('Select a category', '← Back to main menu', ['Test', '← Back to main menu'])
        ->expectsChoice('What do you want to do?', 'Finish and install', MAIN_MENU)
        ->expectsOutputToContain('Dependency missing/dependency is not available in the catalog.')
        ->expectsConfirmation('Proceed with installation?', 'yes')
        ->assertExitCode(0);
});

it('removes a package whose dependency was declined', function () {
    $catalog = Mockery::mock(CatalogRepositoryInterface::class);

    $filament = new PackageDefinition(
        name: 'filament/filament',
        category: 'Admin Panels',
        tags: ['filament'],
        installSteps: ['echo installing filament'],
        dependsOn: ['spatie/laravel-permission'],
    );

    $permission = new PackageDefinition(
        name: 'spatie/laravel-permission',
        category: 'Authorization',
        tags: ['permissions'],
        installSteps: ['echo installing permission'],
    );

    $catalog->shouldReceive('getAll')->andReturn([$filament, $permission]);
    $catalog->shouldReceive('findByCategory')->with('Admin Panels')->andReturn([$filament]);
    $catalog->shouldReceive('findByCategory')->with('Authorization')->andReturn([$permission]);
    $catalog->shouldReceive('findByName')->with('spatie/laravel-permission')->andReturn($permission);

    $this->app->instance(CatalogRepositoryInterface::class, $catalog);
    $this->app->instance(ProcessRunner::class, fakeRunner());

    $this->artisan('x:install')
        ->expectsChoice('What do you want to do?', 'Browse the catalog', MAIN_MENU)
        ->expectsChoice('Select a category', 'Admin Panels', ['Admin Panels', 'Authorization', '← Back to main menu'])
        ->expectsChoice('Select packages in Admin Panels', ['filament/filament'], ['filament/filament'])
        ->expectsChoice('Select a category', '← Back to main menu', ['Admin Panels', 'Authorization', '← Back to main menu'])
        ->expectsChoice('What do you want to do?', 'Finish and install', MAIN_MENU)
        ->expectsConfirmation('spatie/laravel-permission is required. Add it to the cart?', 'no')
        ->expectsOutputToContain('Removed filament/filament from the cart because its dependency spatie/laravel-permission was declined.')
        ->expectsOutputToContain('Your cart is empty after resolving dependencies.')
        ->expectsChoice('What do you want to do?', 'Quit', MAIN_MENU)
        ->assertExitCode(0);
});
