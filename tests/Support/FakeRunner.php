<?php

declare(strict_types=1);

namespace MammothWP\WPRelease\Tests\Support;

use MammothWP\WPRelease\Process\CommandRunner;
use MammothWP\WPRelease\Process\ProcessResult;

/**
 * Records commands and returns canned output (svn status gets a scripted listing).
 */
final class FakeRunner implements CommandRunner
{
    /** @var list<list<string>> */
    public array $commands = [];

    /**
     * @param string $svnStatus Output returned for `svn status`.
     */
    public function __construct(private readonly string $svnStatus = '')
    {
    }

    /**
     * @inheritDoc
     */
    public function run(array $command, ?string $cwd = null, array $env = []): ProcessResult
    {
        $this->commands[] = $command;
        $isStatus = $command[0] === 'svn' && ($command[1] ?? '') === 'status';
        return new ProcessResult(0, $isStatus ? $this->svnStatus : '', '');
    }

    /**
     * @inheritDoc
     */
    public function mustRun(array $command, int $failExit, ?string $cwd = null, array $env = []): ProcessResult
    {
        return $this->run($command, $cwd, $env);
    }

    /**
     * @inheritDoc
     */
    public function requireTool(string $binary): void
    {
    }

    /**
     * Commands joined as strings, for assertions.
     *
     * @return list<string>
     */
    public function lines(): array
    {
        return array_map(static fn (array $c): string => implode(' ', $c), $this->commands);
    }
}
