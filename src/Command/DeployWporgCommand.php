<?php

declare(strict_types=1);

namespace MammothWP\WPRelease\Command;

use MammothWP\WPRelease\Config\Config;
use MammothWP\WPRelease\Output\Reporter;
use MammothWP\WPRelease\Services;
use Symfony\Component\Console\Input\InputOption;

/**
 * mmwp-release deploy-wporg.
 */
final class DeployWporgCommand extends AbstractCommand
{
    /**
     * Add deploy-specific options (shared with `all`).
     *
     * @param AbstractCommand $command Command to extend.
     * @return void
     */
    public static function addDeployOptions(AbstractCommand $command): void
    {
        $command
            ->addOption('fail-if-unapproved', null, InputOption::VALUE_NONE, 'Exit 32 when the plugin has no wordpress.org SVN repository yet')
            ->addOption('strict', null, InputOption::VALUE_NONE, 'Also require 0 warnings in plugin-check.json')
            ->addOption('allow', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Allow Plugin Check WARNINGs matching this regex when re-checking; repeatable (MMWP_PCP_ALLOW)');
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        parent::configure();
        $this->setName('deploy-wporg')->setDescription('Publish the built plugin to wordpress.org SVN (idempotent)');
        self::addDeployOptions($this);
    }

    /**
     * @inheritDoc
     */
    protected function perform(Config $config, Services $services, Reporter $reporter): array
    {
        return $services->wporgDeployer()->deploy($config);
    }
}
