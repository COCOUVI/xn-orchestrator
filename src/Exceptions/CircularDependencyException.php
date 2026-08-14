<?php

namespace Xn\Orchestrator\Exceptions;

use RuntimeException;

final class CircularDependencyException extends RuntimeException
{
    public function __construct(string $packageName)
    {
        parent::__construct(sprintf(
            'Circular dependency detected involving "%s". The catalog data is invalid.',
            $packageName,
        ));
    }
}
