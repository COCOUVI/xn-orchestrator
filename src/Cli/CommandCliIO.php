<?php

namespace Xn\Orchestrator\Cli;

use Illuminate\Console\OutputStyle;
use Illuminate\Console\View\Components\Factory;

final class CommandCliIO implements CliIO
{
    private readonly Factory $components;

    public function __construct(private readonly OutputStyle $output)
    {
        $this->components = new Factory($output);
    }

    public function info(string $message): void
    {
        $this->components->info($message);
    }

    public function warn(string $message): void
    {
        $this->components->warn($message);
    }

    public function error(string $message): void
    {
        $this->components->error($message);
    }

    public function line(string $message): void
    {
        $this->output->writeln($message);
    }

    public function newLine(): void
    {
        $this->output->newLine(1);
    }
}
