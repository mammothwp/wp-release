<?php

declare(strict_types=1);

namespace MammothWP\WPRelease\Check;

use MammothWP\WPRelease\Config\Config;
use MammothWP\WPRelease\Exception\ExitCode;
use MammothWP\WPRelease\Exception\ReleaseException;
use MammothWP\WPRelease\Output\Reporter;
use MammothWP\WPRelease\Process\CommandRunner;
use MammothWP\WPRelease\Process\ProcessResult;
use Symfony\Component\Filesystem\Filesystem;

/**
 * A throwaway WordPress install (SQLite drop-in or MySQL) driven through wp-cli.
 */
class TempSite
{
    private const SQLITE_ZIP = 'https://downloads.wordpress.org/plugin/sqlite-database-integration.latest-stable.zip';

    private string $dir = '';

    private string $dbName = '';

    /**
     * @param CommandRunner $runner   Process wrapper.
     * @param Reporter      $reporter Logger.
     * @param Filesystem    $fs       Filesystem helper.
     */
    public function __construct(
        private readonly CommandRunner $runner,
        private readonly Reporter $reporter,
        private readonly Filesystem $fs = new Filesystem()
    ) {
    }

    /**
     * Site directory (empty before create()).
     *
     * @return string
     */
    public function dir(): string
    {
        return $this->dir;
    }

    /**
     * Download WordPress, wire up the database and run the install.
     *
     * @param Config $config Run configuration.
     * @return void
     */
    public function create(Config $config): void
    {
        $this->dir = sys_get_temp_dir() . '/mmwp-site-' . bin2hex(random_bytes(6));
        $this->fs->mkdir($this->dir);
        $this->reporter->info('Temp WordPress site: ' . $this->dir);
        $download = ['core', 'download', '--quiet'];
        if ($config->wpVersion !== 'latest') {
            $download[] = '--version=' . $config->wpVersion;
        }
        $this->wp($download, ExitCode::SITE_SETUP);
        if ($config->db === 'mysql') {
            $this->configureMysql($config);
        }
        if ($config->db === 'sqlite') {
            $this->configureSqlite();
        }
        $this->install();
    }

    /**
     * Run a wp-cli command against the site.
     *
     * @param list<string> $args     Arguments after `wp`.
     * @param int          $failExit Exit code on failure.
     * @return ProcessResult
     */
    public function wp(array $args, int $failExit): ProcessResult
    {
        return $this->runner->mustRun($this->wpCommand($args), $failExit, null, $this->wpEnv());
    }

    /**
     * Run a wp-cli command without throwing.
     *
     * @param list<string> $args Arguments after `wp`.
     * @return ProcessResult
     */
    public function wpTry(array $args): ProcessResult
    {
        return $this->runner->run($this->wpCommand($args), null, $this->wpEnv());
    }

    /**
     * Unzip a built plugin zip into the site's plugins directory.
     *
     * @param string $zip Zip path.
     * @return void
     */
    public function unzipPlugin(string $zip): void
    {
        $this->runner->mustRun(['unzip', '-q', '-o', $zip, '-d', $this->dir . '/wp-content/plugins'], ExitCode::SITE_SETUP);
    }

    /**
     * Drop the MySQL database (if any) and remove the site directory.
     *
     * @param Config $config Run configuration.
     * @return void
     */
    public function destroy(Config $config): void
    {
        if ($this->dir === '') {
            return;
        }
        if ($config->db === 'mysql' && $this->dbName !== '') {
            $this->wpTry(['db', 'drop', '--yes']);
        }
        $this->fs->remove($this->dir);
        $this->dir = '';
    }

    /**
     * Base wp command with path and root handling.
     *
     * @param list<string> $args Arguments.
     * @return list<string>
     */
    private function wpCommand(array $args): array
    {
        $command = ['wp', '--path=' . $this->dir];
        if (function_exists('posix_geteuid') && posix_geteuid() === 0) {
            $command[] = '--allow-root';
        }
        return [...$command, ...$args];
    }

    /**
     * Environment for wp-cli processes.
     *
     * @return array<string, string>
     */
    private function wpEnv(): array
    {
        return ['WP_CLI_DISABLE_AUTO_CHECK_UPDATE' => 'true'];
    }

    /**
     * Configure the SQLite database integration drop-in.
     *
     * @return void
     */
    private function configureSqlite(): void
    {
        $this->runner->requireTool('curl');
        $plugins = $this->dir . '/wp-content/plugins';
        $zip = $this->dir . '/sqlite.zip';
        $this->runner->mustRun(['curl', '-fsSL', '-o', $zip, self::SQLITE_ZIP], ExitCode::SITE_SETUP);
        $this->runner->mustRun(['unzip', '-q', $zip, '-d', $plugins], ExitCode::SITE_SETUP);
        $this->fs->remove($zip);
        $copy = $plugins . '/sqlite-database-integration/db.copy';
        $dropIn = str_replace(
            ['{SQLITE_IMPLEMENTATION_FOLDER_PATH}', '{SQLITE_PLUGIN}'],
            [$plugins . '/sqlite-database-integration', 'sqlite-database-integration/load.php'],
            (string) file_get_contents($copy)
        );
        $this->fs->dumpFile($this->dir . '/wp-content/db.php', $dropIn);
        $this->wp(['config', 'create', '--dbname=wp', '--dbuser=wp', '--dbpass=wp', '--skip-check', '--quiet'], ExitCode::SITE_SETUP);
    }

    /**
     * Configure and create a MySQL database.
     *
     * @param Config $config Run configuration.
     * @return void
     */
    private function configureMysql(Config $config): void
    {
        $env = $config->dbEnv;
        $this->dbName = ($env['name'] ?? '') !== '' ? $env['name'] : 'mmwp_check_' . bin2hex(random_bytes(4));
        $this->wp([
            'config', 'create', '--skip-check', '--quiet',
            '--dbname=' . $this->dbName, '--dbuser=' . ($env['user'] ?? 'root'),
            '--dbpass=' . ($env['pass'] ?? ''), '--dbhost=' . ($env['host'] ?? 'localhost'),
        ], ExitCode::SITE_SETUP);
        $this->wp(['db', 'create'], ExitCode::SITE_SETUP);
    }

    /**
     * Run `wp core install`, explaining a missing PDO SQLite driver.
     *
     * @return void
     */
    private function install(): void
    {
        $result = $this->wpTry([
            'core', 'install', '--url=http://localhost', '--title=mmwp-check', '--admin_user=admin',
            '--admin_password=' . bin2hex(random_bytes(8)), '--admin_email=check@example.com', '--skip-email',
        ]);
        if ($result->isSuccessful()) {
            return;
        }
        $hint = str_contains($result->output(), 'PDO Driver for SQLite is missing')
            ? ' PHP lacks pdo_sqlite: install php-sqlite3 or use --db=mysql.'
            : '';
        throw new ReleaseException('wp core install failed.' . $hint . "\n" . $this->reporter->mask($result->output()), ExitCode::SITE_SETUP);
    }
}
