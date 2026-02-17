<?php

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

    /**
     * Run a shell command with live output streaming and return the exit code.
     *
     * Output is forwarded to stdout/stderr in real time. The process has no
     * timeout, making this suitable for long-running commands like downloads
     * or Composer installs.
     *
     * @param string $command Shell command to execute.
     * @param string|null $cwd Working directory for the process. Defaults to the current directory.
     * @return int Exit code (0 = success).
     */
    public function stream(string $command, ?string $cwd = null): int
    {
        $process = Process::fromShellCommandline($command, $cwd);
        $process->setTimeout(null);
        $process->run(function (string $type, string $buffer): void {
            if ($type === Process::ERR) {
                fwrite(STDERR, $buffer);
            } else {
                fwrite(STDOUT, $buffer);
            }
        });

        return $process->getExitCode();
    }
}
