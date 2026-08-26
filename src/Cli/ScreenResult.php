<?php

namespace Xn\Orchestrator\Cli;

final class ScreenResult
{
    public function __construct(
        public readonly ?Screen $next,
        public readonly mixed $payload,
        public readonly ?int $exitCode,
        public readonly ?Screen $backToScreen = null,
    ) {}

    public static function goto(Screen $screen, ?string $payload = null): self
    {
        return new self($screen, $payload, null, null);
    }

    public static function success(): self
    {
        return new self(null, null, 0, null);
    }

    public static function failure(): self
    {
        return new self(null, null, 1, null);
    }

    public static function backTo(Screen $screen): self
    {
        return new self(null, null, null, $screen);
    }

    public function exits(): bool
    {
        return $this->exitCode !== null;
    }
}