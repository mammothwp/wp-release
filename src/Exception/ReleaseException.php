<?php

declare(strict_types=1);

namespace MammothWP\WPRelease\Exception;

use RuntimeException;

/**
 * A failure that maps to a specific process exit code.
 */
class ReleaseException extends RuntimeException
{
    /**
     * @param string $message  Human readable failure.
     * @param int    $exitCode One of the ExitCode constants.
     */
    public function __construct(string $message, private readonly int $exitCode = ExitCode::GENERAL)
    {
        parent::__construct($message, $exitCode);
    }

    /**
     * Exit code the CLI should terminate with.
     *
     * @return int
     */
    public function getExitCode(): int
    {
        return $this->exitCode;
    }
}
