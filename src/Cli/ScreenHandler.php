<?php

namespace Xn\Orchestrator\Cli;

interface ScreenHandler
{
    public function handle(CliContext $context, ?string $payload = null): ScreenResult;
}
