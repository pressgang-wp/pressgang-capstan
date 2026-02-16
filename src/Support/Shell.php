<?php

declare(strict_types=1);

namespace PressGang\Capstan\Support;

use Symfony\Component\Process\Process;

class Shell
{
    /**
     * Run a shell command and return the exit code.
     */
    public function run(string $command): int
    {
        $process = Process::fromShellCommandline($command);
        $process->run();

        return $process->getExitCode();
    }
}
