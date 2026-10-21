<?php

declare(strict_types=1);

namespace MammothWP\WPRelease\Command;

use MammothWP\WPRelease\Config\Config;
use MammothWP\WPRelease\Output\Reporter;
use MammothWP\WPRelease\Services;
use Symfony\Component\Console\Input\InputOption;

/**
 * mmwp-release all: build, check, deploy-wporg, release-github; stops at the first failure.
 */
final class AllCommand extends AbstractCommand
{
    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        parent::configure();
        $this->setName('all')->setDescription('build → check → deploy-wporg → release-github, stopping at the first failure');
        CheckCommand::addCheckOptions($this);
        $this->addOption('fail-if-unapproved', null, InputOption::VALUE_NONE, 'Exit 32 when the plugin has no wordpress.org SVN repository yet');
    }

    /**
     * @inheritDoc
     */
    protected function perform(Config $config, Services $services, Reporter $reporter): array
    {
        $build = $services->builder()->build($config);
        $check = $services->checkRunner()->run($config);
        $wporg = $services->wporgDeployer()->deploy($config, $check);
        $github = $services->githubReleaser()->release($config);
        return [
            'slug' => $config->slug,
            'version' => $build->version,
            'build' => $build->toArray(),
            'check' => CheckCommand::summarise($check),
            'deploy_wporg' => $wporg['status'],
            'release_github' => $github['status'],
        ];
    }
}
