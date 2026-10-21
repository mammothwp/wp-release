<?php

declare(strict_types=1);

namespace MammothWP\WPRelease\Process;

/**
 * Outcome of an external command.
 */
final class ProcessResult
{
    /**
     * @param int    $exitCode Process exit code.
     * @param string $stdout   Captured standard output.
     * @param string $stderr   Captured standard error.
     */
    public function __construct(
        public readonly int $exitCode,
        public readonly string $stdout,
        public readonly string $stderr
    ) {
    }

    /**
     * Whether the command exited with 0.
     *
     * @return bool
     */
    public function isSuccessful(): bool
    {
        return $this->exitCode === 0;
    }

    /**
     * Combined stdout and stderr.
     *
     * @return string
     */
    public function output(): string
    {
        return trim($this->stdout . "\n" . $this->stderr);
    }
}
