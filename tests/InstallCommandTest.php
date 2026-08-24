<?php

use Mockery\MockInterface;
use Symfony\Component\Console\Tester\CommandTester;
use Xn\Orchestrator\Catalog\CatalogRepositoryInterface;
use Xn\Orchestrator\Catalog\PackageDefinition;
use Xn\Orchestrator\Commands\InstallCommand;
use Xn\Orchestrator\Exceptions\PackageInstallationException;
use Xn\Orchestrator\Support\CompatibilityChecker;
use Xn\Orchestrator\Support\DependencyResolver;
use Xn\Orchestrator\Support\ProcessResult;
use Xn\Orchestrator\Support\ProcessRunner;

function mainMenu(): array
{
    return ['Browse categories', 'Search packages', 'View cart', 'Finish and install', 'Quit'];
}

function categoriesMenu(array $categories): array
{
    return [...$categories, 'Back to main menu'];
}

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

function AUTH_PACKAGES(): array
{
    return ['laravel/sanctum' => 'laravel/sanctum'];
}

function DEBUG_PACKAGES(): array
{
    return ['debug/inspector' => 'debug/inspector'];
}

it('installs packages selected from multiple categories keeping the cart across screens', function () {
    $this->app->instance(CatalogRepositoryInterface::class, fakeCatalog());

    $commands = [];
    $this->app->instance(ProcessRunner::class, recordedRunner($commands));

    $this->artisan('x:install')
        ->expectsChoice('Main Menu', 'Browse categories', mainMenu())
        ->expectsChoice('Select a category', 'Authentication', categoriesMenu(['Authentication', 'Debugging']))
        ->expectsChoice('Select packages in Authentication', ['laravel/sanctum'], AUTH_PACKAGES())
        ->expectsOutputToContain('1 package selected')
        ->expectsChoice('Select a category', 'Debugging', categoriesMenu(['Authentication', 'Debugging']))
        ->expectsChoice('Select packages in Debugging', ['debug/inspector'], DEBUG_PACKAGES())
        ->expectsOutputToContain('2 packages selected')
        ->expectsChoice('Select a category', 'Back to main menu', categoriesMenu(['Authentication', 'Debugging']))
        ->expectsChoice('Main Menu', 'Finish and install', mainMenu())
        ->expectsConfirmation('Proceed with installation?', 'yes')
        ->expectsOutputToContain('echo installing sanctum')
        ->expectsOutputToContain('echo configuring sanctum')
        ->expectsOutputToContain('echo inspecting')
        ->expectsOutputToContain('Installed: laravel/sanctum, debug/inspector')
        ->assertExitCode(0);

    expect($commands)->toBe([
        'echo installing sanctum',
        'echo configuring sanctum',
        'echo inspecting',
    ]);
});

it('keeps selections when reopening a category and does not duplicate installs', function () {
    $this->app->instance(CatalogRepositoryInterface::class, fakeCatalog());
    $this->app->instance(ProcessRunner::class, fakeRunner());

    $this->artisan('x:install')
        ->expectsChoice('Main Menu', 'Browse categories', mainMenu())
        ->expectsChoice('Select a category', 'Authentication', categoriesMenu(['Authentication', 'Debugging']))
        ->expectsChoice('Select packages in Authentication', ['laravel/sanctum'], AUTH_PACKAGES())
        ->expectsChoice('Select a category', 'Authentication', categoriesMenu(['Authentication', 'Debugging']))
        ->expectsChoice('Select packages in Authentication', ['laravel/sanctum'], AUTH_PACKAGES())
        ->expectsOutputToContain('1 package selected')
        ->expectsChoice('Select a category', 'Back to main menu', categoriesMenu(['Authentication', 'Debugging']))
        ->expectsChoice('Main Menu', 'Finish and install', mainMenu())
        ->expectsConfirmation('Proceed with installation?', 'yes')
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
        ->expectsChoice('Main Menu', 'Browse categories', mainMenu())
        ->expectsChoice('Select a category', 'Authentication', categoriesMenu(['Authentication', 'Debugging']))
        ->expectsChoice('Select packages in Authentication', ['laravel/sanctum'], AUTH_PACKAGES())
        ->expectsChoice('Select a category', 'Back to main menu', categoriesMenu(['Authentication', 'Debugging']))
        ->expectsChoice('Main Menu', 'Finish and install', mainMenu())
        ->expectsConfirmation('Proceed with installation?', 'yes')
        ->expectsOutputToContain('something went terribly wrong')
        ->expectsOutputToContain('Rolling back...')
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
        ->expectsChoice('Main Menu', 'Browse categories', mainMenu())
        ->expectsChoice('Select a category', 'Authentication', categoriesMenu(['Authentication', 'Debugging']))
        ->expectsChoice('Select packages in Authentication', ['laravel/sanctum'], AUTH_PACKAGES())
        ->expectsChoice('Select a category', 'Back to main menu', categoriesMenu(['Authentication', 'Debugging']))
        ->expectsChoice('Main Menu', 'Finish and install', mainMenu())
        ->expectsConfirmation('Proceed with installation?', 'yes')
        ->expectsOutputToContain('boom')
        ->assertExitCode(1);

    expect($calls)->toBe(2);
});

it('cancels the installation when the user declines and returns to the menu', function () {
    $this->app->instance(CatalogRepositoryInterface::class, fakeCatalog());
    $this->app->instance(ProcessRunner::class, fakeRunner());

    $this->artisan('x:install')
        ->expectsChoice('Main Menu', 'Browse categories', mainMenu())
        ->expectsChoice('Select a category', 'Authentication', categoriesMenu(['Authentication', 'Debugging']))
        ->expectsChoice('Select packages in Authentication', ['laravel/sanctum'], AUTH_PACKAGES())
        ->expectsChoice('Select a category', 'Back to main menu', categoriesMenu(['Authentication', 'Debugging']))
        ->expectsChoice('Main Menu', 'Finish and install', mainMenu())
        ->expectsConfirmation('Proceed with installation?', 'no')
        ->expectsOutputToContain('Installation cancelled.')
        ->expectsChoice('Main Menu', 'Quit', mainMenu())
        ->assertExitCode(0);
});

it('removes a package from the grouped cart view', function () {
    $this->app->instance(CatalogRepositoryInterface::class, fakeCatalog());
    $this->app->instance(ProcessRunner::class, fakeRunner());

    $this->artisan('x:install')
        ->expectsChoice('Main Menu', 'Browse categories', mainMenu())
        ->expectsChoice('Select a category', 'Authentication', categoriesMenu(['Authentication', 'Debugging']))
        ->expectsChoice('Select packages in Authentication', ['laravel/sanctum'], AUTH_PACKAGES())
        ->expectsChoice('Select a category', 'Back to main menu', categoriesMenu(['Authentication', 'Debugging']))
        ->expectsChoice('Main Menu', 'View cart', mainMenu())
        ->expectsOutputToContain('Your cart')
        ->expectsOutputToContain('Total: 1 package')
        ->expectsChoice('Select a package to remove', 'laravel/sanctum', ['laravel/sanctum', 'Back to main menu'])
        ->expectsOutputToContain('Removed laravel/sanctum from the cart.')
        ->expectsChoice('Main Menu', 'Quit', mainMenu())
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

it('searches by name or tags and adds the package to the cart', function () {
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
        ->expectsChoice('Main Menu', 'Search packages', mainMenu())
        ->expectsSearch('Search for a package', 'laravel/sanctum', 'token', ['laravel/sanctum'])
        ->expectsOutputToContain('Added laravel/sanctum to the cart.')
        ->expectsChoice('Main Menu', 'Finish and install', mainMenu())
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
        ->expectsChoice('Main Menu', 'Browse categories', mainMenu())
        ->expectsChoice('Select a category', 'Authentication', categoriesMenu(['Authentication']))
        ->expectsChoice('Select packages in Authentication', ['laravel/breeze', 'laravel/jetstream'], [
            'laravel/breeze' => 'laravel/breeze',
            'laravel/jetstream' => 'laravel/jetstream',
        ])
        ->expectsChoice('Select a category', 'Back to main menu', categoriesMenu(['Authentication']))
        ->expectsChoice('Main Menu', 'Finish and install', mainMenu())
        ->expectsOutputToContain('laravel/breeze conflicts with laravel/jetstream')
        ->expectsOutputToContain('Remove one of the conflicting packages from the cart and try again.')
        ->expectsChoice('Main Menu', 'Quit', mainMenu())
        ->assertExitCode(0);
});

function dependencyCatalog(): CatalogRepositoryInterface
{
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

    return $catalog;
}

it('installs a missing dependency first and asks before adding it', function () {
    $this->app->instance(CatalogRepositoryInterface::class, dependencyCatalog());

    $commands = [];
    $this->app->instance(ProcessRunner::class, recordedRunner($commands));

    $this->artisan('x:install')
        ->expectsChoice('Main Menu', 'Browse categories', mainMenu())
        ->expectsChoice('Select a category', 'Admin Panels', categoriesMenu(['Admin Panels', 'Authorization']))
        ->expectsChoice('Select packages in Admin Panels', ['filament/filament'], ['filament/filament' => 'filament/filament'])
        ->expectsChoice('Select a category', 'Back to main menu', categoriesMenu(['Admin Panels', 'Authorization']))
        ->expectsChoice('Main Menu', 'Finish and install', mainMenu())
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
        ->expectsChoice('Main Menu', 'Browse categories', mainMenu())
        ->expectsChoice('Select a category', 'Test', categoriesMenu(['Test']))
        ->expectsChoice('Select packages in Test', ['some/package'], ['some/package' => 'some/package'])
        ->expectsChoice('Select a category', 'Back to main menu', categoriesMenu(['Test']))
        ->expectsChoice('Main Menu', 'Finish and install', mainMenu())
        ->expectsOutputToContain('Dependency missing/dependency is not available in the catalog.')
        ->expectsConfirmation('Proceed with installation?', 'yes')
        ->assertExitCode(0);
});

it('removes a package whose dependency was declined', function () {
    $this->app->instance(CatalogRepositoryInterface::class, dependencyCatalog());
    $this->app->instance(ProcessRunner::class, fakeRunner());

    $this->artisan('x:install')
        ->expectsChoice('Main Menu', 'Browse categories', mainMenu())
        ->expectsChoice('Select a category', 'Admin Panels', categoriesMenu(['Admin Panels', 'Authorization']))
        ->expectsChoice('Select packages in Admin Panels', ['filament/filament'], ['filament/filament' => 'filament/filament'])
        ->expectsChoice('Select a category', 'Back to main menu', categoriesMenu(['Admin Panels', 'Authorization']))
        ->expectsChoice('Main Menu', 'Finish and install', mainMenu())
        ->expectsConfirmation('spatie/laravel-permission is required. Add it to the cart?', 'no')
        ->expectsOutputToContain('Removed filament/filament from the cart because its dependency spatie/laravel-permission was declined.')
        ->expectsOutputToContain('Your cart is empty after resolving dependencies.')
        ->expectsChoice('Main Menu', 'Quit', mainMenu())
        ->assertExitCode(0);
});

function incompatibleCatalog(): CatalogRepositoryInterface
{
    $catalog = Mockery::mock(CatalogRepositoryInterface::class);

    $compatible = new PackageDefinition(
        name: 'acme/compatible',
        category: 'Test',
        tags: [],
        installSteps: ['echo installing compatible'],
    );

    $incompatible = new PackageDefinition(
        name: 'acme/incompatible',
        category: 'Test',
        tags: [],
        installSteps: ['echo installing incompatible'],
        supportedLaravel: ['^99.0'],
        supportedPhp: '^99.0',
    );

    $catalog->shouldReceive('getAll')->andReturn([$compatible, $incompatible]);
    $catalog->shouldReceive('findByCategory')->with('Test')->andReturn([$compatible, $incompatible]);

    return $catalog;
}

it('tags incompatible packages in the category menu', function () {
    $this->app->instance(CatalogRepositoryInterface::class, incompatibleCatalog());
    $this->app->instance(ProcessRunner::class, fakeRunner());

    $this->artisan('x:install')
        ->expectsChoice('Main Menu', 'Browse categories', mainMenu())
        ->expectsChoice('Select a category', 'Test', categoriesMenu(['Test']))
        ->expectsChoice('Select packages in Test', ['acme/compatible'], [
            'acme/compatible' => 'acme/compatible',
            'acme/incompatible' => 'acme/incompatible (incompatible)',
        ])
        ->expectsChoice('Select a category', 'Back to main menu', categoriesMenu(['Test']))
        ->expectsChoice('Main Menu', 'Finish and install', mainMenu())
        ->expectsConfirmation('Proceed with installation?', 'yes')
        ->expectsOutputToContain('echo installing compatible')
        ->assertExitCode(0);
});

it('requires an extra confirmation when installing incompatible packages', function () {
    $this->app->instance(CatalogRepositoryInterface::class, incompatibleCatalog());
    $this->app->instance(ProcessRunner::class, fakeRunner());

    $this->artisan('x:install')
        ->expectsChoice('Main Menu', 'Browse categories', mainMenu())
        ->expectsChoice('Select a category', 'Test', categoriesMenu(['Test']))
        ->expectsChoice('Select packages in Test', ['acme/incompatible'], [
            'acme/compatible' => 'acme/compatible',
            'acme/incompatible' => 'acme/incompatible (incompatible)',
        ])
        ->expectsChoice('Select a category', 'Back to main menu', categoriesMenu(['Test']))
        ->expectsChoice('Main Menu', 'Finish and install', mainMenu())
        ->expectsOutputToContain('The following packages are not compatible with your Laravel or PHP version:')
        ->expectsConfirmation('Install incompatible packages anyway?', 'yes')
        ->expectsConfirmation('Proceed with installation?', 'yes')
        ->expectsOutputToContain('echo installing incompatible')
        ->assertExitCode(0);
});

it('cancels the installation when incompatible packages are declined', function () {
    $this->app->instance(CatalogRepositoryInterface::class, incompatibleCatalog());
    $this->app->instance(ProcessRunner::class, fakeRunner());

    $this->artisan('x:install')
        ->expectsChoice('Main Menu', 'Browse categories', mainMenu())
        ->expectsChoice('Select a category', 'Test', categoriesMenu(['Test']))
        ->expectsChoice('Select packages in Test', ['acme/incompatible'], [
            'acme/compatible' => 'acme/compatible',
            'acme/incompatible' => 'acme/incompatible (incompatible)',
        ])
        ->expectsChoice('Select a category', 'Back to main menu', categoriesMenu(['Test']))
        ->expectsChoice('Main Menu', 'Finish and install', mainMenu())
        ->expectsConfirmation('Install incompatible packages anyway?', 'no')
        ->expectsOutputToContain('Installation cancelled.')
        ->expectsChoice('Main Menu', 'Quit', mainMenu())
        ->assertExitCode(0);
});

it('hides incompatible packages from the menu when configured', function () {
    config(['xn-orchestrator.compatibility.hide_incompatible' => true]);

    $this->app->instance(CatalogRepositoryInterface::class, incompatibleCatalog());
    $this->app->instance(ProcessRunner::class, fakeRunner());

    $this->artisan('x:install')
        ->expectsChoice('Main Menu', 'Browse categories', mainMenu())
        ->expectsChoice('Select a category', 'Test', categoriesMenu(['Test']))
        ->expectsChoice('Select packages in Test', ['acme/compatible'], [
            'acme/compatible' => 'acme/compatible',
        ])
        ->expectsChoice('Select a category', 'Back to main menu', categoriesMenu(['Test']))
        ->expectsChoice('Main Menu', 'Finish and install', mainMenu())
        ->expectsConfirmation('Proceed with installation?', 'yes')
        ->expectsOutputToContain('echo installing compatible')
        ->assertExitCode(0);
});

it('reports when no compatible packages exist in a category', function () {
    $catalog = Mockery::mock(CatalogRepositoryInterface::class);

    $incompatible = new PackageDefinition(
        name: 'acme/incompatible',
        category: 'Test',
        tags: [],
        installSteps: ['echo installing incompatible'],
        supportedPhp: '^99.0',
    );

    $catalog->shouldReceive('getAll')->andReturn([$incompatible]);
    $catalog->shouldReceive('findByCategory')->with('Test')->andReturn([$incompatible]);

    $this->app->instance(CatalogRepositoryInterface::class, $catalog);
    $this->app->instance(ProcessRunner::class, fakeRunner());

    config(['xn-orchestrator.compatibility.hide_incompatible' => true]);

    $this->artisan('x:install')
        ->expectsChoice('Main Menu', 'Browse categories', mainMenu())
        ->expectsChoice('Select a category', 'Test', categoriesMenu(['Test']))
        ->expectsOutputToContain('No compatible packages in Test.')
        ->expectsChoice('Select a category', 'Back to main menu', categoriesMenu(['Test']))
        ->expectsChoice('Main Menu', 'Quit', mainMenu())
        ->assertExitCode(0);
});

it('previews every step without running commands in dry-run mode', function () {
    $this->app->instance(CatalogRepositoryInterface::class, fakeCatalog());

    $commands = [];
    $this->app->instance(ProcessRunner::class, recordedRunner($commands));

    $this->artisan('x:install', ['--dry-run' => true])
        ->expectsChoice('Main Menu', 'Browse categories', mainMenu())
        ->expectsChoice('Select a category', 'Authentication', categoriesMenu(['Authentication', 'Debugging']))
        ->expectsChoice('Select packages in Authentication', ['laravel/sanctum'], AUTH_PACKAGES())
        ->expectsChoice('Select a category', 'Back to main menu', categoriesMenu(['Authentication', 'Debugging']))
        ->expectsChoice('Main Menu', 'Finish and install', mainMenu())
        ->expectsConfirmation('Proceed with installation?', 'yes')
        ->expectsOutputToContain('[DRY RUN] echo installing sanctum')
        ->expectsOutputToContain('[DRY RUN] echo configuring sanctum')
        ->expectsOutputToContain('[laravel/sanctum] would be installed successfully (dry-run).')
        ->assertExitCode(0);

    expect($commands)->toBe([]);
});

it('warns when finishing with an empty cart', function () {
    $catalog = Mockery::mock(CatalogRepositoryInterface::class);

    $pkg = new PackageDefinition(
        name: 'a/b',
        category: 'Cat',
        tags: [],
        installSteps: ['echo hi'],
    );

    $catalog->shouldReceive('getAll')->andReturn([$pkg]);
    $catalog->shouldReceive('findByCategory')->with('Cat')->andReturn([$pkg]);

    $this->app->instance(CatalogRepositoryInterface::class, $catalog);
    $this->app->instance(ProcessRunner::class, fakeRunner());

    $command = new InstallCommand(
        $catalog,
        fakeRunner(),
        $this->app->make(DependencyResolver::class),
        $this->app->make(CompatibilityChecker::class),
    );
    $command->setLaravel($this->app);

    $tester = new CommandTester($command);
    $tester->setInputs(['View cart', 'Finish and install', 'Quit']);
    $code = $tester->execute([]);

    expect($code)->toBe(0)
        ->and(str_contains($tester->getDisplay(), 'Your cart is empty. Add packages first.'))->toBeTrue();
});
