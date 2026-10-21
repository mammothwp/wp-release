<?php

declare(strict_types=1);

namespace MammothWP\WPRelease\Tests\Unit;

use MammothWP\WPRelease\Build\StageFiles;
use MammothWP\WPRelease\Process\CommandRunner;
use MammothWP\WPRelease\Process\ProcessResult;
use PHPUnit\Framework\TestCase;

final class StageFilesTest extends TestCase
{
    /**
     * Verifies: build output, VCS-excluded dirs and the slug zip are never staged.
     * @return void
     */
    public function testSelectDropsBuildOutputAndZip(): void
    {
        $tracked = ['plugin.php', 'dist/x.php', 'node_modules/a.js', 'plugin.zip', 'src/dist/keep.php', '.distignore'];
        self::assertSame(['plugin.php', 'src/dist/keep.php', '.distignore'], (new StageFiles())->select($tracked, 'plugin', false));
    }

    /**
     * Verifies: vendor/ is dropped only when composer rebuilds it.
     * @return void
     */
    public function testSelectDropsVendorOnlyWithFreshInstall(): void
    {
        $finder = new StageFiles();
        self::assertSame(['a.php'], $finder->select(['a.php', 'vendor/x.php'], 'p', true));
        self::assertSame(['a.php', 'vendor/x.php'], $finder->select(['a.php', 'vendor/x.php'], 'p', false));
    }

    /**
     * Verifies: tracked-but-deleted files are skipped when a root is given.
     * @return void
     */
    public function testSelectSkipsMissingFiles(): void
    {
        $root = sys_get_temp_dir() . '/mmwp-sf-' . bin2hex(random_bytes(4));
        mkdir($root);
        touch($root . '/here.php');
        self::assertSame(['here.php'], (new StageFiles())->select(['here.php', 'gone.php'], 'p', false, $root));
        unlink($root . '/here.php');
        rmdir($root);
    }

    /**
     * Verifies: the no-git fallback excludes dot-files, phars, caches and the tools dir.
     * @return void
     */
    public function testFallbackExcludes(): void
    {
        $excludes = (new StageFiles())->fallbackExcludes('p', true, 'ci-tools');
        foreach (['.*', '*.phar', '.cache/', '/vendor', '/ci-tools/', '/p.zip', '/node_modules', '/dist'] as $expected) {
            self::assertContains($expected, $excludes);
        }
        self::assertNotContains('/vendor', (new StageFiles())->fallbackExcludes('p', false));
    }

    /**
     * Verifies: tools dir resolution.
     * @return void
     */
    public function testRelativeToolsDir(): void
    {
        $finder = new StageFiles();
        self::assertSame('.tools', $finder->relativeToolsDir('/proj/.tools', '/proj'));
        self::assertSame('tools/bin', $finder->relativeToolsDir('./tools/bin/', '/proj'));
        self::assertNull($finder->relativeToolsDir('/opt/tools', '/proj'));
        self::assertNull($finder->relativeToolsDir(null, '/proj'));
        self::assertNull($finder->relativeToolsDir('', '/proj'));
    }

    /**
     * Verifies: not a git work tree gives null.
     * @return void
     */
    public function testTrackedIsNullWithoutGit(): void
    {
        $runner = new class implements CommandRunner {
            /** @inheritDoc */
            public function run(array $command, ?string $cwd = null, array $env = []): ProcessResult
            {
                return new ProcessResult(128, '', 'not a git repository');
            }

            /** @inheritDoc */
            public function mustRun(array $command, int $failExit, ?string $cwd = null, array $env = []): ProcessResult
            {
                return $this->run($command, $cwd, $env);
            }

            /** @inheritDoc */
            public function requireTool(string $binary): void
            {
            }
        };
        self::assertNull((new StageFiles())->tracked($runner, '/nowhere'));
    }
}
