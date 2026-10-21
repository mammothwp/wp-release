<?php

declare(strict_types=1);

namespace MammothWP\WPRelease\Deploy;

use MammothWP\WPRelease\Config\Config;
use MammothWP\WPRelease\Exception\ExitCode;
use MammothWP\WPRelease\Exception\ReleaseException;
use MammothWP\WPRelease\Output\Reporter;
use MammothWP\WPRelease\Process\CommandRunner;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Syncs dist/<slug>/ and .wordpress-org/ into a sparse SVN checkout, tags the version and commits.
 */
class SvnDeployer
{
    /**
     * @param CommandRunner $runner   Process wrapper.
     * @param Reporter      $reporter Logger (masks credentials).
     * @param Filesystem    $fs       Filesystem helper.
     */
    public function __construct(
        private readonly CommandRunner $runner,
        private readonly Reporter $reporter,
        private readonly Filesystem $fs = new Filesystem()
    ) {
    }

    /**
     * Prepare the working copy and, unless dry-run, commit it.
     *
     * @param Config               $config      Run configuration.
     * @param string               $version     Version to tag.
     * @param array<string,string> $credentials username/password (may be empty in dry-run).
     * @return array{committed: bool, status: string}
     */
    public function deploy(Config $config, string $version, array $credentials): array
    {
        foreach (['svn', 'rsync'] as $tool) {
            $this->runner->requireTool($tool);
        }
        $wc = sys_get_temp_dir() . '/mmwp-svn-' . bin2hex(random_bytes(6));
        try {
            $this->checkout($config, $wc);
            $this->sync($config, $wc);
            $this->stageChanges($wc);
            $this->runner->mustRun(['svn', 'cp', 'trunk', 'tags/' . $version], ExitCode::SVN, $wc);
            $status = $this->runner->mustRun(['svn', 'status'], ExitCode::SVN, $wc)->stdout;
            if ($config->dryRun) {
                return ['committed' => false, 'status' => $status];
            }
            $this->commit($wc, $version, $credentials);
            return ['committed' => true, 'status' => $status];
        } finally {
            $this->fs->remove($wc);
        }
    }

    /**
     * Sparse checkout of trunk, assets and an empty tags directory.
     *
     * @param Config $config Config.
     * @param string $wc     Working copy path.
     * @return void
     */
    private function checkout(Config $config, string $wc): void
    {
        $url = WporgStatusFetcher::BASE . $config->slug;
        $this->runner->mustRun(['svn', 'checkout', '--depth=empty', '--non-interactive', $url, $wc], ExitCode::SVN);
        $this->runner->mustRun(['svn', 'update', '--set-depth=infinity', '--non-interactive', 'trunk'], ExitCode::SVN, $wc);
        $this->runner->mustRun(['svn', 'update', '--set-depth=empty', '--non-interactive', 'tags'], ExitCode::SVN, $wc);
        if (is_dir($config->path . '/.wordpress-org')) {
            $this->runner->mustRun(['svn', 'update', '--set-depth=infinity', '--non-interactive', 'assets'], ExitCode::SVN, $wc);
        }
    }

    /**
     * Rsync the built plugin into trunk and .wordpress-org into assets.
     *
     * @param Config $config Config.
     * @param string $wc     Working copy path.
     * @return void
     */
    private function sync(Config $config, string $wc): void
    {
        $this->rsync($config->distDir(), $wc . '/trunk');
        if (is_dir($config->path . '/.wordpress-org')) {
            $this->rsync($config->path . '/.wordpress-org', $wc . '/assets');
        }
    }

    /**
     * Mirror a directory, preserving the SVN metadata of the target.
     *
     * @param string $from Source dir.
     * @param string $to   Destination dir.
     * @return void
     */
    private function rsync(string $from, string $to): void
    {
        $this->runner->mustRun(['rsync', '-a', '--delete', '--exclude=.svn', $from . '/', $to . '/'], ExitCode::SVN);
    }

    /**
     * svn add new files and svn rm deleted ones.
     *
     * @param string $wc Working copy path.
     * @return void
     */
    private function stageChanges(string $wc): void
    {
        $changes = SvnStatusParser::parse($this->runner->mustRun(['svn', 'status'], ExitCode::SVN, $wc)->stdout);
        foreach ($changes['add'] as $path) {
            $this->runner->mustRun(['svn', 'add', '--force', '--parents', '-q', $path . '@'], ExitCode::SVN, $wc);
        }
        foreach ($changes['remove'] as $path) {
            $this->runner->mustRun(['svn', 'rm', '--force', '-q', $path . '@'], ExitCode::SVN, $wc);
        }
    }

    /**
     * Commit with credentials; they are masked from all logs.
     *
     * @param string               $wc          Working copy path.
     * @param string               $version     Version.
     * @param array<string,string> $credentials username/password.
     * @return void
     */
    private function commit(string $wc, string $version, array $credentials): void
    {
        $user = $credentials['username'] ?? '';
        $pass = $credentials['password'] ?? '';
        if ($user === '' || $pass === '') {
            throw new ReleaseException('WPORG_USERNAME and WPORG_PASSWORD are required to commit.', ExitCode::CONFIG);
        }
        $this->reporter->addSecret($user);
        $this->reporter->addSecret($pass);
        $this->runner->mustRun([
            'svn', 'commit', '-m', 'Release ' . $version, '--non-interactive', '--no-auth-cache',
            '--username', $user, '--password', $pass,
        ], ExitCode::SVN, $wc);
    }
}
