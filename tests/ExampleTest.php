<?php

use Xn\Orchestrator\OrchestratorServiceProvider;

it('registers the service provider in the container', function () {
    expect(app()->getProvider(OrchestratorServiceProvider::class))
        ->toBeInstanceOf(OrchestratorServiceProvider::class);
});

it('registers the install command', function () {
    $this->artisan('list')
        ->expectsOutputToContain('x:install')
        ->assertExitCode(0);
});
