<?php

use Xn\Orchestrator\Exceptions\PackageInstallationException;
use Xn\Orchestrator\Support\ProcessResult;
use Xn\Orchestrator\Support\ProcessRunner;

it('runs a successful command and captures the output', function () {
    $result = (new ProcessRunner)->run('echo hello-from-orchestrator');

    expect($result)
        ->toBeInstanceOf(ProcessResult::class)
        ->success->toBeTrue()
        ->exitCode->toBe(0)
        ->output->toContain('hello-from-orchestrator');
});

it('reports failure without throwing from run()', function () {
    $result = (new ProcessRunner)->run('exit 3');

    expect($result->success)->toBeFalse()
        ->and($result->exitCode)->toBe(3);
});

it('throws a PackageInstallationException with raw output from runOrThrow()', function () {
    try {
        (new ProcessRunner)->runOrThrow('echo expected-error-output && exit 1');

        $this->fail('runOrThrow() should have thrown.');
    } catch (PackageInstallationException $exception) {
        expect($exception->command)->toBe('echo expected-error-output && exit 1')
            ->and($exception->result->exitCode)->toBe(1)
            ->and($exception->result->output)->toContain('expected-error-output');
    }
});

it('uses the configured working directory', function () {
    $tmpDir = sys_get_temp_dir();

    $result = (new ProcessRunner(workingDirectory: $tmpDir))->run('php -r "echo getcwd();"');

    expect(trim($result->output))->toBe($tmpDir);
});

it('has a configurable timeout defaulting to 120 seconds', function () {
    expect((new ProcessRunner)->timeout())->toBe(120)
        ->and((new ProcessRunner(timeout: 30))->timeout())->toBe(30);
});
