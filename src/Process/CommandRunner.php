<?php

declare(strict_types=1);

namespace MammothWP\WPRelease\Process;

/**
 * Runs external commands (composer, wp, zip, rsync, svn, curl).
 */
interface CommandRunner
{
    /**
     * Run a command and return its result without throwing on non-zero exit.
     *
     * @param list<string>          $command Command argv.
     * @param string|null           $cwd     Working directory.
     * @param array<string, string> $env     Extra environment variables.
     * @return ProcessResult
     */
    public function run(array $command, ?string $cwd = null, array $env = []): ProcessResult;

    /**
     * Run a command and throw a ReleaseException with $failExit when it fails.
     *
     * @param list<string>          $command  Command argv.
     * @param int                   $failExit Exit code used on failure.
     * @param string|null           $cwd      Working directory.
     * @param array<string, string> $env      Extra environment variables.
     * @return ProcessResult
     */
    public function mustRun(array $command, int $failExit, ?string $cwd = null, array $env = []): ProcessResult;

    /**
     * Throw a MISSING_TOOL exception unless the binary is on PATH.
     *
     * @param string $binary Binary name.
     * @return void
     */
    public function requireTool(string $binary): void;
}
