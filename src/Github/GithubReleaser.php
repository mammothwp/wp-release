<?php

declare(strict_types=1);

namespace MammothWP\WPRelease\Github;

use MammothWP\WPRelease\Config\Config;
use MammothWP\WPRelease\Exception\ExitCode;
use MammothWP\WPRelease\Exception\ReleaseException;
use MammothWP\WPRelease\Output\Reporter;
use MammothWP\WPRelease\Plugin\ChangelogExtractor;
use MammothWP\WPRelease\Plugin\PluginReader;

/**
 * release-github: idempotently publish the built zip as a GitHub Release.
 */
class GithubReleaser
{
    /**
     * @param GithubClient $github   GitHub client.
     * @param Reporter     $reporter Logger.
     * @param PluginReader $reader   Plugin reader.
     */
    public function __construct(
        private readonly GithubClient $github,
        private readonly Reporter $reporter,
        private readonly PluginReader $reader = new PluginReader()
    ) {
    }

    /**
     * Create the release for the header version unless it exists.
     *
     * @param Config $config Run configuration.
     * @return array<string, mixed> Result data; `status` is created|dry-run|already-released.
     */
    public function release(Config $config): array
    {
        $info = $this->reader->read($config->path, $config->slug);
        $version = $info->version();
        if ($version === null) {
            throw new ReleaseException('Plugin header Version not found.', ExitCode::VALIDATION);
        }
        $tag = 'v' . $version;
        $base = ['slug' => $config->slug, 'version' => $version, 'tag' => $tag];
        if ($this->github->releaseExists($config->githubOwner, $config->repoName(), $tag)) {
            $this->reporter->info(sprintf('GitHub release %s already exists.', $tag));
            return [...$base, 'status' => 'already-released'];
        }
        $notes = ChangelogExtractor::extract($info->readme->raw ?? '', $version) ?? 'Release ' . $version;
        if ($config->dryRun) {
            $this->reporter->info(sprintf("Dry run: would create GitHub release %s with notes:\n%s", $tag, $notes));
            return [...$base, 'status' => 'dry-run', 'notes' => $notes];
        }
        if (!is_file($config->zipPath())) {
            throw new ReleaseException($config->slug . '.zip not found. Run `mmwp-release build` first.', ExitCode::BUILD);
        }
        $id = $this->github->createRelease($config->githubOwner, $config->repoName(), $tag, $notes);
        $this->github->uploadAsset($config->githubOwner, $config->repoName(), $id, $config->zipPath());
        return [...$base, 'status' => 'created', 'release_id' => $id];
    }
}
