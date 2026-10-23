<?php

declare(strict_types=1);

namespace MammothWP\WPRelease\Check;

use MammothWP\WPRelease\Build\Builder;
use MammothWP\WPRelease\Config\Config;
use MammothWP\WPRelease\Exception\ExitCode;
use MammothWP\WPRelease\Exception\ReleaseException;
use MammothWP\WPRelease\Output\Reporter;
use MammothWP\WPRelease\Plugin\PluginReader;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Runs Plugin Check (and optionally the Mammoth self-test) against the built zip in a temp site.
 */
class CheckRunner
{
    /**
     * @param TempSite     $site     Temp site.
     * @param Builder      $builder  Builder (used when the zip is missing).
     * @param Reporter     $reporter Logger.
     * @param PluginReader $reader   Plugin reader.
     * @param Filesystem   $fs       Filesystem helper.
     */
    public function __construct(
        private readonly TempSite $site,
        private readonly Builder $builder,
        private readonly Reporter $reporter,
        private readonly PluginReader $reader = new PluginReader(),
        private readonly Filesystem $fs = new Filesystem()
    ) {
    }

    /**
     * Run the check; returns the result when it passes, throws otherwise.
     *
     * @param Config $config Run configuration.
     * @return CheckResult
     */
    public function run(Config $config): CheckResult
    {
        $this->fs->remove([$config->checkJsonPath(), $config->path . '/plugin-check.txt']);
        if ($this->zipIsStale($config)) {
            $this->reporter->info('Zip missing or older than the sources, running build first.');
            $this->builder->build($config);
        }
        $version = (string) $this->reader->read($config->path, $config->slug)->version();
        try {
            $this->site->create($config);
            $result = $this->checkInSite($config, $version);
            $this->runSelfTest($config);
        } finally {
            $this->cleanup($config);
        }
        return $result;
    }

    /**
     * Whether the zip is missing or older than the main file, readme.txt or composer.json.
     *
     * @param Config $config Config.
     * @return bool
     */
    private function zipIsStale(Config $config): bool
    {
        if (!is_file($config->zipPath())) {
            return true;
        }
        $zipTime = (int) filemtime($config->zipPath());
        foreach ([$config->slug . '.php', 'readme.txt', 'composer.json'] as $source) {
            $path = $config->path . '/' . $source;
            if (is_file($path) && (int) filemtime($path) > $zipTime) {
                return true;
            }
        }
        return false;
    }

    /**
     * Install, activate, run Plugin Check, write reports, fail on blocking findings.
     *
     * @param Config $config  Config.
     * @param string $version Plugin version.
     * @return CheckResult
     */
    private function checkInSite(Config $config, string $version): CheckResult
    {
        $this->site->wp(['plugin', 'install', 'plugin-check', '--activate'], ExitCode::SITE_SETUP);
        $this->site->unzipPlugin($config->zipPath());
        $activation = $this->site->wpTry(['plugin', 'activate', $config->slug]);
        if (!$activation->isSuccessful()) {
            throw new ReleaseException("Plugin activation failed:\n" . $this->reporter->mask($activation->output()), ExitCode::SITE_SETUP);
        }
        $output = $this->site->wpTry(['plugin', 'check', $config->slug, '--format=json']);
        $findings = PluginCheckParser::parse($output->stdout);
        if (!$output->isSuccessful() && $findings === []) {
            throw new ReleaseException("wp plugin check failed to run:\n" . $this->reporter->mask($output->output()), ExitCode::SITE_SETUP);
        }
        $this->debugTable($config);
        $result = new CheckResult($config->slug, $version, $config->strict, $findings, new AllowList($config->allow));
        $this->writeReports($config, $result);
        $this->assertPassed($result);
        return $result;
    }

    /**
     * In debug mode also print the table format.
     *
     * @param Config $config Config.
     * @return void
     */
    private function debugTable(Config $config): void
    {
        if (!$this->reporter->isDebug()) {
            return;
        }
        $this->reporter->info($this->site->wpTry(['plugin', 'check', $config->slug, '--format=table'])->stdout);
    }

    /**
     * Persist plugin-check.json and plugin-check.txt in the plugin root.
     *
     * @param Config      $config Config.
     * @param CheckResult $result Result.
     * @return void
     */
    private function writeReports(Config $config, CheckResult $result): void
    {
        $json = (string) json_encode($result->toArray(), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        $this->fs->dumpFile($config->checkJsonPath(), $json . "\n");
        $this->fs->dumpFile($config->path . '/plugin-check.txt', $result->summary()->toText());
    }

    /**
     * Throw PLUGIN_CHECK with counts when the result fails.
     *
     * @param CheckResult $result Result.
     * @return void
     */
    private function assertPassed(CheckResult $result): void
    {
        $summary = $result->summary();
        $this->reporter->info($summary->countsLine());
        foreach ($summary->allowed() as $a) {
            $this->reporter->info(ReportSummary::allowedLine($a['finding'], $a['rule']));
        }
        foreach ($summary->byCode() as $key => $count) {
            $this->reporter->info(sprintf('  %4d  %s', $count, $key));
        }
        if ($result->passed()) {
            return;
        }
        throw new ReleaseException(
            sprintf('Plugin Check failed (%d error(s), %d warning(s)%s). See plugin-check.txt.', $summary->errors(), $summary->warnings(), $result->strict ? ', strict' : ''),
            ExitCode::PLUGIN_CHECK
        );
    }

    /**
     * Run `wp mmwp self-test <slug>` when requested and available.
     *
     * @param Config $config Config.
     * @return void
     */
    private function runSelfTest(Config $config): void
    {
        if (!$config->selfTest) {
            return;
        }
        if (!$this->site->wpTry(['cli', 'has-command', 'mmwp self-test'])->isSuccessful()) {
            $this->reporter->info('Self-test skipped: `wp mmwp self-test` is not provided by the plugin (mammothwp/wp-self-test).');
            return;
        }
        $result = $this->site->wpTry(['mmwp', 'self-test', $config->slug]);
        $this->reporter->info($this->reporter->mask($result->output()));
        if (!$result->isSuccessful()) {
            throw new ReleaseException('Self-test failed.', ExitCode::SELF_TEST);
        }
    }

    /**
     * Remove the temp site unless --keep.
     *
     * @param Config $config Config.
     * @return void
     */
    private function cleanup(Config $config): void
    {
        if ($config->keep) {
            $this->reporter->info('Keeping temp site at ' . $this->site->dir());
            return;
        }
        $this->site->destroy($config);
    }
}
