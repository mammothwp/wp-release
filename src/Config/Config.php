<?php

declare(strict_types=1);

namespace MammothWP\WPRelease\Config;

/**
 * Fully resolved configuration for one run.
 */
final class Config
{
    /**
     * @param string       $path             Plugin repository root.
     * @param string       $slug             Plugin slug.
     * @param bool         $dryRun           Do not publish anything.
     * @param bool         $debug            Verbose output.
     * @param bool         $force            Bypass the passing-check gate.
     * @param string       $format           table|json.
     * @param bool         $strict           Treat Plugin Check warnings as failures.
     * @param string|null  $phpMin           Minimum acceptable "Requires PHP" header.
     * @param string       $wpVersion        WordPress version for the check site (or "latest").
     * @param list<string> $excludes         Extra forbidden-file patterns.
     * @param string       $db               sqlite|mysql.
     * @param array<string, string> $dbEnv   host/user/pass/name for MySQL.
     * @param bool         $selfTest         Run wp mmwp self-test during check.
     * @param bool         $keep             Keep the temp site.
     * @param bool         $failIfUnapproved Exit 32 when the plugin is not on wordpress.org yet.
     * @param string       $githubOwner      GitHub organisation/user.
     * @param string       $githubRepo       GitHub repository name.
     */
    public function __construct(
        public readonly string $path,
        public readonly string $slug,
        public readonly bool $dryRun = false,
        public readonly bool $debug = false,
        public readonly bool $force = false,
        public readonly string $format = 'table',
        public readonly bool $strict = false,
        public readonly ?string $phpMin = null,
        public readonly string $wpVersion = 'latest',
        public readonly array $excludes = [],
        public readonly string $db = 'sqlite',
        public readonly array $dbEnv = [],
        public readonly bool $selfTest = false,
        public readonly bool $keep = false,
        public readonly bool $failIfUnapproved = false,
        public readonly string $githubOwner = 'mammothwp',
        public readonly string $githubRepo = ''
    ) {
    }

    /**
     * Absolute path of the built zip.
     *
     * @return string
     */
    public function zipPath(): string
    {
        return $this->path . '/' . $this->slug . '.zip';
    }

    /**
     * Absolute path of the dist directory holding the built plugin.
     *
     * @return string
     */
    public function distDir(): string
    {
        return $this->path . '/dist/' . $this->slug;
    }

    /**
     * Absolute path of the Plugin Check JSON report.
     *
     * @return string
     */
    public function checkJsonPath(): string
    {
        return $this->path . '/plugin-check.json';
    }

    /**
     * GitHub repository name (defaults to the slug).
     *
     * @return string
     */
    public function repoName(): string
    {
        return $this->githubRepo !== '' ? $this->githubRepo : $this->slug;
    }
}
