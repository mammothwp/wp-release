<?php

declare(strict_types=1);

namespace MammothWP\WPRelease\Status;

use MammothWP\WPRelease\Check\CheckGate;
use MammothWP\WPRelease\Config\Config;
use MammothWP\WPRelease\Deploy\WporgState;
use MammothWP\WPRelease\Deploy\WporgStatusFetcher;
use MammothWP\WPRelease\Github\GithubClient;
use MammothWP\WPRelease\Plugin\PluginReader;
use Throwable;

/**
 * Collects the read-only release status shown by `mmwp-release status`.
 */
class StatusCollector
{
    /**
     * @param WporgStatusFetcher $fetcher Published tag lookup.
     * @param GithubClient       $github  GitHub client.
     * @param PluginReader       $reader  Plugin reader.
     * @param CheckGate          $gate    Check gate.
     */
    public function __construct(
        private readonly WporgStatusFetcher $fetcher,
        private readonly GithubClient $github,
        private readonly PluginReader $reader = new PluginReader(),
        private readonly CheckGate $gate = new CheckGate()
    ) {
    }

    /**
     * Gather status.
     *
     * @param Config $config Run configuration.
     * @return array<string, mixed>
     */
    public function collect(Config $config): array
    {
        $info = $this->reader->read($config->path, $config->slug);
        $version = $info->version() ?? '';
        $wporg = $this->wporgState($config, $version);
        $github = $this->githubState($config, $version);
        return [
            'slug' => $config->slug,
            'header_version' => $version !== '' ? $version : null,
            'readme_stable_tag' => $info->readme?->stableTag(),
            'on_wordpress_org' => $wporg,
            'on_github' => $github,
            'deploy_wporg_would' => $this->planWporg($config, $version, $wporg),
            'release_github_would' => $this->planGithub($github),
        ];
    }

    /**
     * wordpress.org state label.
     *
     * @param Config $config  Config.
     * @param string $version Version.
     * @return string yes|no|not-approved|unknown
     */
    private function wporgState(Config $config, string $version): string
    {
        if ($version === '') {
            return 'unknown';
        }
        try {
            $state = $this->fetcher->fetch($config->slug, $version)['state'];
        } catch (Throwable) {
            return 'unknown';
        }
        return match ($state) {
            WporgState::Released => 'yes',
            WporgState::NotApproved => 'not-approved',
            WporgState::Pending => 'no',
        };
    }

    /**
     * GitHub state label.
     *
     * @param Config $config  Config.
     * @param string $version Version.
     * @return string yes|no|unknown
     */
    private function githubState(Config $config, string $version): string
    {
        if ($version === '') {
            return 'unknown';
        }
        try {
            return $this->github->releaseExists($config->githubOwner, $config->repoName(), 'v' . $version) ? 'yes' : 'no';
        } catch (Throwable) {
            return 'unknown';
        }
    }

    /**
     * What deploy-wporg would do.
     *
     * @param Config $config  Config.
     * @param string $version Version.
     * @param string $wporg   wordpress.org state label.
     * @return string
     */
    private function planWporg(Config $config, string $version, string $wporg): string
    {
        if ($wporg === 'yes') {
            return 'nothing (already released)';
        }
        if ($wporg === 'not-approved') {
            return 'nothing (plugin not yet approved)';
        }
        if ($wporg === 'unknown') {
            return 'unknown (wordpress.org unreachable or version missing)';
        }
        $reason = $config->force ? null : $this->gate->refusalReason($this->readReport($config), $config->slug, $version, $config->strict);
        return $reason === null ? 'deploy ' . $version . ' to trunk and tags/' . $version : 'refuse (' . $reason . ')';
    }

    /**
     * What release-github would do.
     *
     * @param string $github GitHub state label.
     * @return string
     */
    private function planGithub(string $github): string
    {
        return match ($github) {
            'yes' => 'nothing (already released)',
            'no' => 'create the GitHub release and upload the zip',
            default => 'unknown (GitHub unreachable or version missing)',
        };
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
}
