<?php

declare(strict_types=1);

namespace MammothWP\WPRelease\Command;

use MammothWP\WPRelease\Config\Config;
use MammothWP\WPRelease\Output\Reporter;
use MammothWP\WPRelease\Services;

/**
 * mmwp-release build.
 */
final class BuildCommand extends AbstractCommand
{
    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        parent::configure();
        $this->setName('build')->setDescription('Build dist/<slug>/ and <slug>.zip and validate them');
    }

    /**
     * @inheritDoc
     */
    protected function perform(Config $config, Services $services, Reporter $reporter): array
    {
        return ['slug' => $config->slug, ...$services->builder()->build($config)->toArray()];
    }
}
