<?php

declare(strict_types=1);

namespace MammothWP\WPRelease\Command;

use MammothWP\WPRelease\Check\CheckResult;
use MammothWP\WPRelease\Config\Config;
use MammothWP\WPRelease\Output\Reporter;
use MammothWP\WPRelease\Services;
use Symfony\Component\Console\Input\InputOption;

/**
 * mmwp-release check.
 */
final class CheckCommand extends AbstractCommand
{
    /**
     * Add the check-specific options (shared with `all`).
     *
     * @param AbstractCommand $command Command to extend.
     * @return void
     */
    public static function addCheckOptions(AbstractCommand $command): void
    {
        $command
            ->addOption('strict', null, InputOption::VALUE_NONE, 'Fail on Plugin Check warnings too (MMWP_PCP_STRICT=true)')
            ->addOption('self-test', null, InputOption::VALUE_NONE, 'Also run `wp mmwp self-test <slug>` when provided')
            ->addOption('keep', null, InputOption::VALUE_NONE, 'Keep the temp WordPress site')
            ->addOption('db', null, InputOption::VALUE_REQUIRED, 'sqlite (default) or mysql (MMWP_DB_HOST/USER/PASS/NAME)');
    }

    /**
     * Result data for a check.
     *
     * @param CheckResult $result Check result.
     * @return array<string, mixed>
     */
    public static function summarise(CheckResult $result): array
    {
        $data = $result->toArray();
        unset($data['findings']);
        return $data;
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        parent::configure();
        $this->setName('check')->setDescription('Run Plugin Check against the built zip in a throwaway WordPress site');
        self::addCheckOptions($this);
    }

    /**
     * @inheritDoc
     */
    protected function perform(Config $config, Services $services, Reporter $reporter): array
    {
        return self::summarise($services->checkRunner()->run($config));
    }
}
