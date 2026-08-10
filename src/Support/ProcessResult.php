<?php

namespace Xn\Orchestrator\Support;

final class ProcessResult
{
    public function __construct(
        public readonly bool $success,
        public readonly string $output,
        public readonly int $exitCode,
    ) {}
}
