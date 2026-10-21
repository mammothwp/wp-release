<?php

declare(strict_types=1);

namespace MammothWP\WPRelease\Tests\Unit;

use MammothWP\WPRelease\Plugin\PluginReader;
use MammothWP\WPRelease\Plugin\PluginValidator;
use PHPUnit\Framework\TestCase;

final class PluginValidatorTest extends TestCase
{
    private const FIXTURES = __DIR__ . '/../fixtures';

    /**
     * Verifies: valid fixture passes.
     * @return void
     */
    public function testValidFixturePasses(): void
    {
        $info = (new PluginReader())->read(self::FIXTURES . '/mmwp-fixture-plugin', 'mmwp-fixture-plugin');
        self::assertSame([], (new PluginValidator())->validate($info));
    }

    /**
     * Verifies: broken fixture reports mismatch and text domain.
     * @return void
     */
    public function testBrokenFixtureReportsMismatchAndTextDomain(): void
    {
        $info = (new PluginReader())->read(self::FIXTURES . '/mmwp-fixture-broken', 'mmwp-fixture-broken');
        $problems = implode("\n", (new PluginValidator())->validate($info));
        self::assertStringContainsString('Text Domain', $problems);
        self::assertStringContainsString('Version mismatch: header Version 2.0.0 != readme Stable tag 1.9.0', $problems);
    }

    /**
     * Verifies: missing main file and readme.
     * @return void
     */
    public function testMissingMainFileAndReadme(): void
    {
        $info = (new PluginReader())->read(self::FIXTURES, 'nothing');
        $problems = (new PluginValidator())->validate($info);
        self::assertContains('Main plugin file nothing.php does not exist.', $problems);
        self::assertContains('readme.txt is missing.', $problems);
    }

    /**
     * Verifies: php minimum and license.
     * @return void
     */
    public function testPhpMinimumAndLicense(): void
    {
        $info = (new PluginReader())->read(self::FIXTURES . '/mmwp-fixture-plugin', 'mmwp-fixture-plugin');
        $problems = implode("\n", (new PluginValidator())->validate($info, '8.1'));
        self::assertStringContainsString('below the configured minimum 8.1', $problems);
    }
}
