<?php

namespace Xn\Orchestrator\Cli;

interface CliIO
{
    public function info(string $message): void;

    public function warn(string $message): void;

    public function error(string $message): void;

    public function line(string $message): void;

    public function newLine(): void;

    /**
     * Prints a single-line status entry: the description, dot-padded to the
     * terminal width, followed by a colored ✓ or ✗.
     */
    public function taskLine(string $description, bool $success): void;
}
