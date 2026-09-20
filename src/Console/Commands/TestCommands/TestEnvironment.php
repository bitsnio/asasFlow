<?php

declare(strict_types=1);

namespace Bitsnio\AsasFlow\Console\Commands\TestCommands;

use Symfony\Component\Process\Process;

class TestEnvironment
{
    public function __construct(
        protected PestVersionResolver $versions
    ) {
    }

    public function phpVersion(): string
    {
        return PHP_VERSION;
    }

    public function pestMajor(): int
    {
        return $this->versions->resolve();
    }

    public function composerCommand(): string
    {
        return PHP_OS_FAMILY === 'Windows'
            ? 'composer.bat'
            : 'composer';
    }

    public function runComposer(
        array $arguments,
        ?int $timeout = 300
    ): Process {
        $process = new Process([
            $this->composerCommand(),
            ...$arguments,
        ], base_path());

        $process->setTimeout($timeout);

        $process->run();

        return $process;
    }

    public function composerExecPest(
        array $arguments = [],
        ?int $timeout = 300
    ): Process {
        return $this->runComposer(
            [
                'exec',
                'pest',
                '--',
                ...$arguments,
            ],
            $timeout
        );
    }
}