<?php

declare(strict_types=1);

namespace MammothWP\WPRelease\Tests\Unit;

use MammothWP\WPRelease\Build\ForbiddenFiles;
use PHPUnit\Framework\TestCase;

final class ForbiddenFilesTest extends TestCase
{
    /**
     * Verifies: flags every forbidden kind.
     * @return void
     */
    public function testFlagsEveryForbiddenKind(): void
    {
        $paths = [
            'plugin.php', '.git/config', 'node_modules/x/index.js', 'tests/FooTest.php', 'phpunit.xml.dist',
            'phpcs.xml', 'phpstan.neon', '.gitlab-ci.yml', '.wordpress-org/icon.png', '.env', '.env.local',
            'debug.log', 'src/deep/error.log', 'composer.lock',
        ];
        $found = (new ForbiddenFiles())->find($paths);
        self::assertSame(array_values(array_diff($paths, ['plugin.php'])), $found);
    }

    /**
     * Verifies: composer lock allowed only when vendor ships.
     * @return void
     */
    public function testComposerLockAllowedOnlyWhenVendorShips(): void
    {
        $finder = new ForbiddenFiles();
        self::assertSame([], $finder->find(['plugin.php', 'composer.lock', 'vendor/autoload.php']));
        self::assertSame(['composer.lock'], $finder->find(['plugin.php', 'composer.lock']));
    }

    /**
     * Verifies: vendor may ship tests but not secrets.
     * @return void
     */
    public function testVendorMayShipTestsButNotSecrets(): void
    {
        $found = (new ForbiddenFiles())->find(['vendor/a/b/tests/T.php', 'vendor/a/phpunit.xml', 'vendor/a/.env', 'vendor/a/x.php']);
        self::assertSame(['vendor/a/.env'], $found);
    }

    /**
     * Verifies: extra patterns.
     * @return void
     */
    public function testExtraPatterns(): void
    {
        $found = (new ForbiddenFiles(['*.psd', 'docs/*']))->find(['art/logo.psd', 'docs/a.md', 'ok.php']);
        self::assertSame(['art/logo.psd', 'docs/a.md'], $found);
    }

    /**
     * Verifies: similar names are not flagged.
     * @return void
     */
    public function testSimilarNamesAreNotFlagged(): void
    {
        self::assertSame([], (new ForbiddenFiles())->find(['.gitignore', 'src/Tests.php', 'latest/a.php', 'includes/class-env.php']));
    }
}
