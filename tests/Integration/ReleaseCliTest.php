<?php

declare(strict_types=1);

namespace MammothWP\WPRelease\Tests\Integration;

use MammothWP\WPRelease\Exception\ExitCode;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Runs `build` and `check` for real against the fixture plugins (needs wp-cli, network and pdo_sqlite).
 */
final class ReleaseCliTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/../fixtures';

    private static string $testedUpTo = '';

    private string $work = '';

    /**
     * Set up before class.
     * @return void
     */
    public static function setUpBeforeClass(): void
    {
        $finder = new ExecutableFinder();
        foreach (['wp', 'rsync', 'zip', 'unzip', 'curl'] as $tool) {
            if ($finder->find($tool) === null) {
                self::markTestSkipped(sprintf('Integration tests skipped: "%s" is not available (wp-cli, rsync, zip, unzip, curl required).', $tool));
            }
        }
        $pdo = new Process(['wp', '--skip-wordpress', '--quiet', 'eval', 'echo extension_loaded("pdo_sqlite") ? "yes" : "no";']);
        $pdo->run();
        if (trim($pdo->getOutput()) !== 'yes') {
            self::markTestSkipped('Integration tests skipped: the PHP used by wp-cli lacks pdo_sqlite (install php-sqlite3).');
        }
        self::$testedUpTo = self::latestWordPressMinor();
    }

    /**
     * Set up.
     * @return void
     */
    protected function setUp(): void
    {
        $this->work = sys_get_temp_dir() . '/mmwp-it-' . bin2hex(random_bytes(4));
        mkdir($this->work);
    }

    /**
     * Tear down.
     * @return void
     */
    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->work);
    }

    /**
     * Verifies: valid fixture builds and passes check.
     * @return void
     */
    public function testValidFixtureBuildsAndPassesCheck(): void
    {
        $path = $this->copyFixture('mmwp-fixture-plugin');
        $build = $this->cli(['build', '--format=json'], $path);
        self::assertSame(ExitCode::SUCCESS, $build->getExitCode(), $build->getErrorOutput());
        self::assertFileExists($path . '/mmwp-fixture-plugin.zip');
        self::assertSame(2, json_decode($build->getOutput(), true)['file_count']);

        $check = $this->cli(['check', '--format=json'], $path);
        self::assertSame(ExitCode::SUCCESS, $check->getExitCode(), $check->getErrorOutput());
        $report = json_decode((string) file_get_contents($path . '/plugin-check.json'), true);
        self::assertSame(0, $report['errors']);
        self::assertSame('1.2.0', $report['version']);
        self::assertFileExists($path . '/plugin-check.txt');
    }

    /**
     * Verifies: strict mode fails on warnings.
     * @return void
     */
    public function testStrictModeFailsOnWarnings(): void
    {
        $path = $this->copyFixture('mmwp-fixture-plugin');
        $check = $this->cli(['check', '--strict', '--format=json'], $path);
        self::assertSame(ExitCode::PLUGIN_CHECK, $check->getExitCode(), $check->getErrorOutput());
        self::assertGreaterThan(0, json_decode((string) file_get_contents($path . '/plugin-check.json'), true)['warnings']);
    }

    /**
     * Verifies: broken fixture fails validation at build.
     * @return void
     */
    public function testBrokenFixtureFailsValidationAtBuild(): void
    {
        $path = $this->copyFixture('mmwp-fixture-broken');
        $build = $this->cli(['build'], $path);
        self::assertSame(ExitCode::VALIDATION, $build->getExitCode());
        self::assertStringContainsString('Version mismatch', $build->getErrorOutput());
        self::assertStringContainsString('Text Domain', $build->getErrorOutput());
        $check = $this->cli(['check'], $path);
        self::assertSame(ExitCode::VALIDATION, $check->getExitCode());
    }

    /**
     * Verifies: plugin check error fails check and blocks deploy.
     * @return void
     */
    public function testPluginCheckErrorFailsCheckAndBlocksDeploy(): void
    {
        $path = $this->copyFixture('mmwp-fixture-pcp-error');
        self::assertSame(ExitCode::SUCCESS, $this->cli(['build'], $path)->getExitCode());
        $check = $this->cli(['check', '--format=json'], $path);
        self::assertSame(ExitCode::PLUGIN_CHECK, $check->getExitCode(), $check->getErrorOutput());
        $report = json_decode((string) file_get_contents($path . '/plugin-check.json'), true);
        self::assertGreaterThan(0, $report['errors']);
        self::assertArrayHasKey('ERROR WordPress.Security.EscapeOutput.OutputNotEscaped', $report['by_code']);
        $deploy = $this->cli(['deploy-wporg'], $path);
        self::assertSame(ExitCode::DEPLOY_REFUSED, $deploy->getExitCode(), $deploy->getErrorOutput());
    }

    /**
     * Copy a fixture to a temp dir and bump its readme "Tested up to" to the current WordPress.
     */
    private function copyFixture(string $name): string
    {
        $path = $this->work . '/' . $name;
        (new Filesystem())->mirror(self::FIXTURES . '/' . $name, $path);
        $readme = (string) file_get_contents($path . '/readme.txt');
        file_put_contents($path . '/readme.txt', (string) preg_replace('/^Tested up to:.*$/m', 'Tested up to: ' . self::$testedUpTo, $readme));
        return $path;
    }

    /**
     * Run bin/mmwp-release.
     *
     * @param list<string> $args Arguments.
     */
    private function cli(array $args, string $path): Process
    {
        $process = new Process([PHP_BINARY, __DIR__ . '/../../bin/mmwp-release', ...$args, '--path=' . $path], null, null, null, 600);
        $process->run();
        return $process;
    }

    /**
     * Latest word press minor.
     *
     * @return string
     */
    private static function latestWordPressMinor(): string
    {
        $curl = new Process(['curl', '-fsS', '--max-time', '20', 'https://api.wordpress.org/core/version-check/1.7/']);
        $curl->run();
        $current = json_decode($curl->getOutput(), true)['offers'][0]['current'] ?? null;
        if (!is_string($current) || preg_match('/^(\d+\.\d+)/', $current, $m) !== 1) {
            self::markTestSkipped('Integration tests skipped: could not determine the current WordPress version (no network?).');
        }
        return $m[1];
    }
}
