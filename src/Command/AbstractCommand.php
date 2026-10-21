<?php

declare(strict_types=1);

namespace MammothWP\WPRelease\Command;

use MammothWP\WPRelease\Config\Config;
use MammothWP\WPRelease\Config\ConfigLoader;
use MammothWP\WPRelease\Exception\ExitCode;
use MammothWP\WPRelease\Exception\ReleaseException;
use MammothWP\WPRelease\Output\Reporter;
use MammothWP\WPRelease\Services;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\ConsoleOutputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

/**
 * Base for all commands: global flags, config loading, exit-code mapping.
 */
abstract class AbstractCommand extends Command
{
    /**
     * Command body. Return result data; throw ReleaseException to fail.
     *
     * @param Config   $config   Resolved configuration.
     * @param Services $services Object graph.
     * @param Reporter $reporter Logger.
     * @return array<string, mixed>
     */
    abstract protected function perform(Config $config, Services $services, Reporter $reporter): array;

    /**
     * Title for table output.
     *
     * @return string
     */
    protected function title(): string
    {
        return (string) $this->getName();
    }

    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $this
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Do not publish anything (deploy-wporg stops before svn commit)')
            ->addOption('debug-mode', null, InputOption::VALUE_NONE, 'Verbose output (command output, table format)')
            ->addOption('force', null, InputOption::VALUE_NONE, 'Skip the passing-check gate')
            ->addOption('path', null, InputOption::VALUE_REQUIRED, 'Plugin root (default: current directory)')
            ->addOption('format', null, InputOption::VALUE_REQUIRED, 'Output format: table|json', 'table')
            ->addOption('slug', null, InputOption::VALUE_REQUIRED, 'Plugin slug (default: MMWP_SLUG or main plugin file)');
    }

    /**
     * @inheritDoc
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $err = $output instanceof ConsoleOutputInterface ? $output->getErrorOutput() : $output;
        $reporter = new Reporter($output, $err, (bool) $input->getOption('debug-mode'), (string) $input->getOption('format'));
        try {
            $env = $this->environment();
            $config = (new ConfigLoader())->load($this->options($input), $env);
            $reporter = new Reporter($output, $err, $config->debug, $config->format);
            $data = $this->perform($config, new Services($reporter, $env), $reporter);
            $reporter->result($this->title(), $data);
            return ExitCode::SUCCESS;
        } catch (ReleaseException $e) {
            $reporter->info('Error: ' . $e->getMessage());
            return $e->getExitCode();
        } catch (Throwable $e) {
            $reporter->info('Unexpected error: ' . $e->getMessage());
            return ExitCode::GENERAL;
        }
    }

    /**
     * Option values keyed by name.
     *
     * @param InputInterface $input Input.
     * @return array<string, mixed>
     */
    private function options(InputInterface $input): array
    {
        return $input->getOptions();
    }

    /**
     * Process environment as string map.
     *
     * @return array<string, string>
     */
    private function environment(): array
    {
        return array_filter(getenv(), 'is_string');
    }
}
