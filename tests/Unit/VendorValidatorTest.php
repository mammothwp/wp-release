<?php

declare(strict_types=1);

namespace MammothWP\WPRelease\Tests\Unit;

use MammothWP\WPRelease\Build\VendorValidator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

/**
 * Runs the real .distignore through rsync on fixture plugins and validates the resulting file list.
 */
final class VendorValidatorTest extends TestCase
{
    private string $out = '';
    private string $src = '';

    /**
     * Set up.
     * @return void
     */
    protected function setUp(): void
    {
        $this->out = sys_get_temp_dir() . '/mmwp-vv-' . bin2hex(random_bytes(4));
        $this->src = $this->out . '-src';
    }

    /**
     * Tear down.
     * @return void
     */
    protected function tearDown(): void
    {
        (new Filesystem())->remove([$this->out, $this->src]);
    }

    /**
     * Verifies: a plugin whose vendor/ survives .distignore has no problems.
     * @return void
     */
    public function testGoodPluginPasses(): void
    {
        self::assertSame([], $this->problems('good'));
    }

    /**
     * Verifies: a bare "vendor" in .distignore is reported as missing autoload, composer files and every package.
     * @return void
     */
    public function testDistignoreStrippingVendorFails(): void
    {
        $problems = implode("\n", $this->problems('strips-vendor'));
        self::assertStringContainsString('vendor/autoload.php is missing', $problems);
        self::assertStringContainsString('vendor/composer/autoload_real.php is missing', $problems);
        self::assertStringContainsString('"acme/lib"', $problems);
        self::assertStringContainsString('"acme/other"', $problems);
    }

    /**
     * Verifies: one missing package is reported by name and nothing else.
     * @return void
     */
    public function testMissingSinglePackageFails(): void
    {
        $problems = $this->problems('missing-package');
        self::assertCount(1, $problems);
        self::assertStringContainsString('"acme/other"', $problems[0]);
    }

    /**
     * Verifies: nothing is required when composer.json only has platform requirements.
     * @return void
     */
    public function testPlatformOnlyRequiresSkipValidation(): void
    {
        $validator = new VendorValidator();
        $composer = ['require' => ['php' => '>=8.1', 'ext-json' => '*']];
        self::assertFalse($validator->hasRuntimeRequires($composer));
        self::assertSame([], $validator->problems($composer, null, ['plugin.php']));
    }

    /**
     * Verifies: without a lock the composer.json require list is the fallback package list.
     * @return void
     */
    public function testFallsBackToComposerRequireWithoutLock(): void
    {
        $problems = (new VendorValidator())->problems(['require' => ['acme/lib' => '^1']], null, ['vendor/autoload.php']);
        self::assertStringContainsString('"acme/lib"', implode("\n", $problems));
    }

    /**
     * Verifies: unanchored vendor-internal names warn, anchored and unrelated ones do not.
     * @return void
     */
    public function testDistignoreWarnings(): void
    {
        $text = "# comment\nvendor\ntests/\nREADME.md\n/docs\nbin\nnode_modules\nsrc/tests\n!composer.lock\ncomposer.lock\n";
        $warnings = (new VendorValidator())->distignoreWarnings($text);
        self::assertCount(5, $warnings);
        foreach (['"vendor"', '"tests/"', '"README.md"', '"bin"', '"composer.lock"'] as $entry) {
            self::assertStringContainsString($entry, implode("\n", $warnings));
        }
    }

    /**
     * Verifies: the good fixture's anchored .distignore raises no warning; the vendor-stripping one does.
     * @return void
     */
    public function testFixtureDistignoreWarnings(): void
    {
        $validator = new VendorValidator();
        self::assertSame([], $validator->distignoreWarnings((string) file_get_contents($this->fixture('good') . '/.distignore')));
        self::assertCount(1, $validator->distignoreWarnings((string) file_get_contents($this->fixture('strips-vendor') . '/.distignore')));
    }

    /**
     * Absolute fixture path.
     *
     * @param string $name Fixture name.
     * @return string
     */
    private function fixture(string $name): string
    {
        return __DIR__ . '/../fixtures/vendor-plugins/' . $name;
    }

    /**
     * Copy a fixture into a temp dir and stub the vendor/ tree its composer.lock describes.
     *
     * The "missing-package" fixture intentionally lacks the acme/other directory.
     *
     * @param string $name Fixture name.
     * @return string Staged plugin directory.
     */
    private function stage(string $name): string
    {
        $fs = new Filesystem();
        $dir = $this->src . '/' . $name;
        $fs->mirror($this->fixture($name), $dir);
        $lock = json_decode((string) file_get_contents($dir . '/composer.lock'), true);
        $fs->dumpFile($dir . '/vendor/autoload.php', "<?php\n");
        $fs->dumpFile($dir . '/vendor/composer/autoload_real.php', "<?php\n");
        $fs->dumpFile($dir . '/vendor/composer/autoload_classmap.php', "<?php\n");
        foreach ($lock['packages'] ?? [] as $package) {
            if ($name === 'missing-package' && $package['name'] === 'acme/other') {
                continue;
            }
            $fs->dumpFile($dir . '/vendor/' . $package['name'] . '/composer.json', '{}');
        }
        return $dir;
    }

    /**
     * Sync a fixture with its .distignore and validate the resulting tree.
     *
     * @param string $name Fixture name.
     * @return list<string>
     */
    private function problems(string $name): array
    {
        if ((new ExecutableFinder())->find('rsync') === null) {
            self::markTestSkipped('rsync is not available.');
        }
        $src = $this->stage($name);
        (new Process(['rsync', '-a', '--exclude-from=' . $src . '/.distignore', $src . '/', $this->out . '/']))->mustRun();
        $paths = [];
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($this->out, \FilesystemIterator::SKIP_DOTS));
        foreach ($it as $file) {
            $paths[] = ltrim(substr((string) $file, strlen($this->out)), '/');
        }
        $read = static fn (string $f): array => json_decode((string) file_get_contents($src . '/' . $f), true);
        return (new VendorValidator())->problems($read('composer.json'), $read('composer.lock'), $paths);
    }
}
