<?php

namespace Xn\Orchestrator\Cli;

use Illuminate\Console\OutputStyle;
use Illuminate\Console\View\Components\Factory;
use Symfony\Component\Console\Terminal;

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

    public function taskLine(string $description, bool $success): void
    {
        $width = min((new Terminal)->getWidth(), 150);
        $dots = max($width - mb_strlen($description) - 6, 0);

        $this->output->write("  {$description} ");
        $this->output->write(str_repeat('<fg=gray>.</>', $dots));
        $this->output->writeln($success
            ? ' <fg=green;options=bold>✓</>'
            : ' <fg=red;options=bold>✗</>');
    }
}
