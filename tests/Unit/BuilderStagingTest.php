<?php

declare(strict_types=1);

namespace MammothWP\WPRelease\Tests\Unit;

use MammothWP\WPRelease\Build\Builder;
use MammothWP\WPRelease\Config\Config;
use MammothWP\WPRelease\Exception\ExitCode;
use MammothWP\WPRelease\Exception\ReleaseException;
use MammothWP\WPRelease\Output\Reporter;
use MammothWP\WPRelease\Process\Runner;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Real Builder runs (git, rsync, zip, unzip, composer) against a throwaway copy of the fixture with CI junk.
 */
final class BuilderStagingTest extends TestCase
{
    private string $work = '';

    /**
     * Set up.
     * @return void
     */
    protected function setUp(): void
    {
        foreach (['git', 'rsync', 'zip', 'unzip', 'composer'] as $tool) {
            if ((new ExecutableFinder())->find($tool) === null) {
                self::markTestSkipped($tool . ' is not available.');
            }
        }
        $this->work = sys_get_temp_dir() . '/mmwp-bs-' . bin2hex(random_bytes(4));
        (new Filesystem())->mirror(__DIR__ . '/../fixtures/mmwp-fixture-plugin', $this->work);
        file_put_contents($this->work . '/composer.json', '{"name":"x/y","autoload":{"classmap":["inc"]}}');
        mkdir($this->work . '/inc');
        file_put_contents($this->work . '/inc/A.php', "<?php\nclass A {}\n");
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
     * Verifies: untracked CI junk stays out, tracked files ship, .distignore applies, fresh vendor is added.
     * @return void
     */
    public function testGitBuildShipsOnlyTrackedFilesPlusVendor(): void
    {
        $this->git(['init', '-q']);
        $this->git(['add', '-A']);
        $this->git(['-c', 'user.email=a@b.c', '-c', 'user.name=t', 'commit', '-qm', 'init']);
        $this->addJunk();
        $entries = $this->buildEntries();
        self::assertContains('mmwp-fixture-plugin/inc/A.php', $entries);
        self::assertContains('mmwp-fixture-plugin/vendor/autoload.php', $entries);
        self::assertContains('mmwp-fixture-plugin/readme.txt', $entries);
        $this->assertNoJunk($entries);
        self::assertNotContains('mmwp-fixture-plugin/untracked.txt', $entries);
        self::assertNotContains('mmwp-fixture-plugin/.wordpress-org/README.md', $entries);
    }

    /**
     * Verifies: the no-git fallback leaves out dot-files, phars, caches and the tools dir.
     * @return void
     */
    public function testFallbackBuildExcludesCiJunk(): void
    {
        $this->addJunk();
        $entries = $this->buildEntries();
        self::assertContains('mmwp-fixture-plugin/inc/A.php', $entries);
        self::assertContains('mmwp-fixture-plugin/vendor/autoload.php', $entries);
        $this->assertNoJunk($entries);
    }

    /**
     * Verifies: with git, a tracked dot-file is flagged by the pre-flight with its path listed.
     * @return void
     */
    public function testPreflightListsTrackedHiddenFile(): void
    {
        file_put_contents($this->work . '/.secret-config', 'x');
        file_put_contents($this->work . '/tool.phar', 'x');
        $this->git(['init', '-q']);
        $this->git(['add', '-A']);
        $this->git(['-c', 'user.email=a@b.c', '-c', 'user.name=t', 'commit', '-qm', 'init']);
        try {
            $this->buildEntries();
            self::fail('Expected a ReleaseException.');
        } catch (ReleaseException $e) {
            self::assertSame(ExitCode::VALIDATION, $e->getCode());
            self::assertStringContainsString('.secret-config', $e->getMessage());
            self::assertStringContainsString('tool.phar', $e->getMessage());
        }
    }

    /**
     * Verifies: a bare "vendor" in .distignore fails the build with exit 11 naming the missing dependency; strict warns first.
     * @return void
     */
    public function testBuildFailsWhenDistignoreStripsVendor(): void
    {
        mkdir($this->work . '/libs/lib', 0777, true);
        file_put_contents($this->work . '/libs/lib/composer.json', '{"name":"acme/lib","version":"1.0.0","autoload":{"classmap":["."]}}');
        file_put_contents($this->work . '/libs/lib/Lib.php', "<?php\nclass Lib {}\n");
        file_put_contents(
            $this->work . '/composer.json',
            '{"name":"x/y","require":{"acme/lib":"*"},"repositories":[{"type":"path","url":"libs/lib","options":{"symlink":false}}]}'
        );
        file_put_contents($this->work . '/.distignore', "vendor\n");
        try {
            $this->buildEntries();
            self::fail('Expected a ReleaseException.');
        } catch (ReleaseException $e) {
            self::assertSame(ExitCode::VALIDATION, $e->getCode());
            self::assertStringContainsString('vendor/autoload.php is missing', $e->getMessage());
            self::assertStringContainsString('"acme/lib"', $e->getMessage());
        }
        $reporter = new Reporter(new BufferedOutput(), new BufferedOutput());
        try {
            (new Builder(new Runner($reporter), $reporter))->build(new Config($this->work, 'mmwp-fixture-plugin', strict: true));
            self::fail('Expected a ReleaseException.');
        } catch (ReleaseException $e) {
            self::assertStringContainsString('Strict mode', $e->getMessage());
        }
    }

    /**
     * Create the files CI leaves in the project dir.
     * @return void
     */
    private function addJunk(): void
    {
        mkdir($this->work . '/.cache/composer/files', 0777, true);
        mkdir($this->work . '/.tools');
        file_put_contents($this->work . '/.cache/composer/files/a', 'x');
        file_put_contents($this->work . '/.tools/wp-cli.phar', 'x');
        file_put_contents($this->work . '/wp-cli.phar', 'x');
        file_put_contents($this->work . '/untracked.txt', 'x');
    }

    /**
     * Assert no hidden or phar entries.
     *
     * @param list<string> $entries Zip entries.
     * @return void
     */
    private function assertNoJunk(array $entries): void
    {
        foreach ($entries as $entry) {
            self::assertDoesNotMatchRegularExpression('#(^|/)\.[^/]#', $entry);
            self::assertStringEndsNotWith('.phar', $entry);
        }
    }

    /**
     * Build and return the zip entries.
     *
     * @return list<string>
     */
    private function buildEntries(): array
    {
        $reporter = new Reporter(new BufferedOutput(), new BufferedOutput());
        (new Builder(new Runner($reporter), $reporter))->build(new Config($this->work, 'mmwp-fixture-plugin'));
        $out = (new Process(['unzip', '-Z1', $this->work . '/mmwp-fixture-plugin.zip']));
        $out->mustRun();
        return array_values(array_filter(explode("\n", $out->getOutput())));
    }

    /**
     * Run git in the work dir.
     *
     * @param list<string> $args Arguments.
     * @return void
     */
    private function git(array $args): void
    {
        (new Process(['git', ...$args], $this->work))->mustRun();
    }
}
