<?php

declare(strict_types=1);

namespace MammothWP\WPRelease;

use MammothWP\WPRelease\Command\AllCommand;
use MammothWP\WPRelease\Command\BuildCommand;
use MammothWP\WPRelease\Command\CheckCommand;
use MammothWP\WPRelease\Command\DeployWporgCommand;
use MammothWP\WPRelease\Command\ReleaseGithubCommand;
use MammothWP\WPRelease\Command\StatusCommand;
use Symfony\Component\Console\Application as ConsoleApplication;

/**
 * mmwp-release console application.
 */
final class Application extends ConsoleApplication
{
    /**
     * Register all commands.
     */
    public function __construct()
    {
        parent::__construct('mmwp-release', 'dev-main');
        $this->addCommands([
            new BuildCommand(),
            new CheckCommand(),
            new DeployWporgCommand(),
            new ReleaseGithubCommand(),
            new StatusCommand(),
            new AllCommand(),
        ]);
    }
}
