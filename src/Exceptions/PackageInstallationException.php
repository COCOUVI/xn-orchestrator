<?php

namespace Xn\Orchestrator\Exceptions;

use RuntimeException;
use Xn\Orchestrator\Support\ProcessResult;

final class PackageInstallationException extends RuntimeException
{
    public function __construct(
        public readonly string $command,
        public readonly ProcessResult $result,
    ) {
        parent::__construct(
            sprintf(
                'Command "%s" failed with exit code %d: %s',
                $command,
                $result->exitCode,
                $result->output === '' ? 'No output' : $result->output,
            ),
        );
    }
}
