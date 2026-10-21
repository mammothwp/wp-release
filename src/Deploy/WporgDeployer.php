<?php

declare(strict_types=1);

namespace MammothWP\WPRelease\Deploy;

use MammothWP\WPRelease\Check\CheckGate;
use MammothWP\WPRelease\Check\CheckResult;
use MammothWP\WPRelease\Config\Config;
use MammothWP\WPRelease\Exception\ExitCode;
use MammothWP\WPRelease\Exception\ReleaseException;
use MammothWP\WPRelease\Output\Reporter;
use MammothWP\WPRelease\Plugin\PluginReader;

/**
 * deploy-wporg: gate on the check result, decide idempotently, then ship via SVN.
 */
class WporgDeployer
{
    /**
     * @param WporgStatusFetcher $fetcher  Published tag lookup.
     * @param SvnDeployer        $svn      SVN worker.
     * @param Reporter           $reporter Logger.
     * @param array<string, string> $env   Environment (WPORG_USERNAME / WPORG_PASSWORD).
     * @param PluginReader       $reader   Plugin reader.
     * @param CheckGate          $gate     Check gate.
     */
    public function __construct(
        private readonly WporgStatusFetcher $fetcher,
        private readonly SvnDeployer $svn,
        private readonly Reporter $reporter,
        private readonly array $env,
        private readonly PluginReader $reader = new PluginReader(),
        private readonly CheckGate $gate = new CheckGate()
    ) {
    }

    /**
     * Run the deploy.
     *
     * @param Config           $config Run configuration.
     * @param CheckResult|null $ran    Check result produced earlier in this run.
     * @return array<string, mixed> Result data; `status` is one of deployed|dry-run|already-released|not-approved.
     */
    public function deploy(Config $config, ?CheckResult $ran = null): array
    {
        $version = $this->version($config);
        $this->assertCheckPassed($config, $version, $ran);
        $remote = $this->fetcher->fetch($config->slug, $version);
        $base = ['slug' => $config->slug, 'version' => $version];
        if ($remote['state'] === WporgState::Released) {
            $this->reporter->info(sprintf('%s %s is already released on wordpress.org.', $config->slug, $version));
            return [...$base, 'status' => 'already-released'];
        }
        if ($remote['state'] === WporgState::NotApproved) {
            return $this->notApproved($config, $base);
        }
        if (ReleaseDecision::isOlderThanLatest($remote['tags'], $version)) {
            $this->reporter->info(sprintf('Warning: %s is older than the latest published tag %s.', $version, (string) ReleaseDecision::latest($remote['tags'])));
        }
        return [...$base, ...$this->ship($config, $version)];
    }

    /**
     * Header version of the plugin.
     *
     * @param Config $config Config.
     * @return string
     */
    private function version(Config $config): string
    {
        $version = $this->reader->read($config->path, $config->slug)->version();
        if ($version === null) {
            throw new ReleaseException('Plugin header Version not found.', ExitCode::VALIDATION);
        }
        return $version;
    }

    /**
     * Refuse unless a passing check exists (this run or plugin-check.json).
     *
     * @param Config           $config  Config.
     * @param string           $version Version.
     * @param CheckResult|null $ran     Result from this run.
     * @return void
     */
    private function assertCheckPassed(Config $config, string $version, ?CheckResult $ran): void
    {
        if ($config->force) {
            $this->reporter->info('--force: skipping the passing-check gate.');
            return;
        }
        $this->gate->assertPasses($ran?->toArray() ?? $this->readReport($config), $config->slug, $version, $config->strict);
    }

    /**
     * Decoded plugin-check.json or null.
     *
     * @param Config $config Config.
     * @return array<string, mixed>|null
     */
    private function readReport(Config $config): ?array
    {
        if (!is_file($config->checkJsonPath())) {
            return null;
        }
        $data = json_decode((string) file_get_contents($config->checkJsonPath()), true);
        return is_array($data) ? $data : null;
    }

    /**
     * Handle a plugin that has no SVN repository yet.
     *
     * @param Config               $config Config.
     * @param array<string, mixed> $base   Base result.
     * @return array<string, mixed>
     */
    private function notApproved(Config $config, array $base): array
    {
        $message = sprintf(
            'https://plugins.svn.wordpress.org/%s/ does not exist: the plugin is not approved yet. Submit the zip at '
            . 'https://wordpress.org/plugins/developers/add/ and re-run once the review is approved.',
            $config->slug
        );
        if ($config->failIfUnapproved) {
            throw new ReleaseException($message, ExitCode::NOT_APPROVED);
        }
        $this->reporter->info($message);
        return [...$base, 'status' => 'not-approved'];
    }

    /**
     * Sync, tag and (unless dry-run) commit.
     *
     * @param Config $config  Config.
     * @param string $version Version.
     * @return array<string, mixed>
     */
    private function ship(Config $config, string $version): array
    {
        if (!is_dir($config->distDir())) {
            throw new ReleaseException('dist/' . $config->slug . ' not found. Run `mmwp-release build` first.', ExitCode::BUILD);
        }
        $credentials = ['username' => $this->env['WPORG_USERNAME'] ?? '', 'password' => $this->env['WPORG_PASSWORD'] ?? ''];
        $this->reporter->addSecret($credentials['username']);
        $this->reporter->addSecret($credentials['password']);
        if (!$config->dryRun && in_array('', $credentials, true)) {
            throw new ReleaseException('WPORG_USERNAME and WPORG_PASSWORD are required to deploy.', ExitCode::CONFIG);
        }
        $outcome = $this->svn->deploy($config, $version, $credentials);
        $this->reporter->info("svn status:\n" . $outcome['status']);
        return ['status' => $outcome['committed'] ? 'deployed' : 'dry-run'];
    }
}
