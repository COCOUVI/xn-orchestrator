<?php

namespace Xn\Orchestrator\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Xn\Orchestrator\OrchestratorServiceProvider;

class TestCase extends Orchestra
{
    protected function getPackageProviders($app)
    {
        return [
            OrchestratorServiceProvider::class,
        ];
    }
}
