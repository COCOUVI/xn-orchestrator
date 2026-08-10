<?php

namespace Xn\Orchestrator\Support;

use Symfony\Component\Process\Process;
use Xn\Orchestrator\Exceptions\PackageInstallationException;

use function Laravel\Prompts\spin;

class ProcessRunner
{
    public function __construct(
        private int $timeout = 120,
        private ?string $workingDirectory = null,
    ) {}

    public function run(string $command, ?string $pendingMessage = null): ProcessResult
    {
        $process = Process::fromShellCommandline(
            $command,
            $this->workingDirectory ?? base_path(),
            null,
            null,
            $this->timeout,
        );

        $output = '';

        $run = function () use ($process, &$output): void {
            $process->run(function (string $type, string $buffer) use (&$output): void {
                $output .= $buffer;
            });
        };

        if ($pendingMessage !== null) {
            spin(
                callback: fn () => $run(),
                message: $pendingMessage,
            );
        } else {
            $run();
        }

        return new ProcessResult(
            success: $process->isSuccessful(),
            output: $output,
            exitCode: $process->getExitCode() ?? 1,
        );
    }

    public function runOrThrow(string $command, ?string $pendingMessage = null): ProcessResult
    {
        $result = $this->run($command, $pendingMessage);

        if (! $result->success) {
            throw new PackageInstallationException($command, $result);
        }

        return $result;
    }

    public function timeout(): int
    {
        return $this->timeout;
    }

    public function workingDirectory(): ?string
    {
        return $this->workingDirectory;
    }
}
