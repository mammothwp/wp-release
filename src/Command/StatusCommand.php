<?php

declare(strict_types=1);

namespace MammothWP\WPRelease\Command;

use MammothWP\WPRelease\Config\Config;
use MammothWP\WPRelease\Output\Reporter;
use MammothWP\WPRelease\Services;

/**
 * mmwp-release status.
 */
final class StatusCommand extends AbstractCommand
{
    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        parent::configure();
        $this->setName('status')->setDescription('Show versions, published state and what the deploy commands would do');
    }

    /**
     * @inheritDoc
     */
    protected function perform(Config $config, Services $services, Reporter $reporter): array
    {
        return $services->statusCollector()->collect($config);
    }
}
