<?php

declare(strict_types=1);

namespace MammothWP\WPRelease\Command;

use MammothWP\WPRelease\Config\Config;
use MammothWP\WPRelease\Output\Reporter;
use MammothWP\WPRelease\Services;

/**
 * mmwp-release release-github.
 */
final class ReleaseGithubCommand extends AbstractCommand
{
    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        parent::configure();
        $this->setName('release-github')->setDescription('Create the GitHub Release for the header version (idempotent)');
    }

    /**
     * @inheritDoc
     */
    protected function perform(Config $config, Services $services, Reporter $reporter): array
    {
        return $services->githubReleaser()->release($config);
    }
}
