<?php

namespace Xn\Orchestrator\Cli;

final class ScreenResult
{
    private function __construct(
        public readonly ?Screen $next,
        public readonly ?string $payload,
        public readonly ?int $exitCode,
    ) {}

    public static function goto(Screen $screen, ?string $payload = null): self
    {
        return new self($screen, $payload, null);
    }

    public static function success(): self
    {
        return new self(null, null, 0);
    }

    public static function failure(): self
    {
        return new self(null, null, 1);
    }

    public function exits(): bool
    {
        return $this->exitCode !== null;
    }
}
