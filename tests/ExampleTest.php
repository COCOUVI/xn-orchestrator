<?php

use Xn\Orchestrator\OrchestratorServiceProvider;

it('registers the service provider in the container', function () {
    expect(app()->getProvider(OrchestratorServiceProvider::class))
        ->toBeInstanceOf(OrchestratorServiceProvider::class);
});

it('registers the package command', function () {
    $this->artisan('xn-orchestrator')
        ->expectsOutputToContain('All done')
        ->assertExitCode(0);
});
