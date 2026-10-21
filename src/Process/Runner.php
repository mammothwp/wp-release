<?php

declare(strict_types=1);

namespace MammothWP\WPRelease\Process;

use MammothWP\WPRelease\Exception\ExitCode;
use MammothWP\WPRelease\Exception\ReleaseException;
use MammothWP\WPRelease\Output\Reporter;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Single wrapper around symfony/process: logs every command and masks secrets.
 */
class Runner implements CommandRunner
{
    /**
     * @param Reporter $reporter Logger and secret masker.
     * @param float    $timeout  Per-command timeout in seconds.
     */
    public function __construct(private readonly Reporter $reporter, private readonly float $timeout = 1800.0)
    {
    }

    /**
     * @inheritDoc
     */
    public function run(array $command, ?string $cwd = null, array $env = []): ProcessResult
    {
        $this->reporter->command($command);
        $process = new Process($command, $cwd, $env === [] ? null : $env, null, $this->timeout);
        $process->run(function (string $type, string $buffer): void {
            $this->reporter->debug(rtrim($buffer));
        });
        return new ProcessResult($process->getExitCode() ?? 1, $process->getOutput(), $process->getErrorOutput());
    }

    /**
     * @inheritDoc
     */
    public function mustRun(array $command, int $failExit, ?string $cwd = null, array $env = []): ProcessResult
    {
        $result = $this->run($command, $cwd, $env);
        if ($result->isSuccessful()) {
            return $result;
        }
        $tail = implode("\n", array_slice(explode("\n", $result->output()), -15));
        $message = sprintf('Command failed (exit %d): %s', $result->exitCode, implode(' ', $command));
        throw new ReleaseException($this->reporter->mask($message . "\n" . $tail), $failExit);
    }

    /**
     * @inheritDoc
     */
    public function requireTool(string $binary): void
    {
        if ((new ExecutableFinder())->find($binary) !== null) {
            return;
        }
        throw new ReleaseException(sprintf('Required tool "%s" was not found on PATH.', $binary), ExitCode::MISSING_TOOL);
    }
}
