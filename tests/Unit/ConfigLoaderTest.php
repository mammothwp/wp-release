<?php

declare(strict_types=1);

namespace MammothWP\WPRelease\Tests\Unit;

use MammothWP\WPRelease\Config\ConfigLoader;
use MammothWP\WPRelease\Exception\ExitCode;
use MammothWP\WPRelease\Exception\ReleaseException;
use PHPUnit\Framework\TestCase;

final class ConfigLoaderTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/../fixtures/mmwp-fixture-plugin';

    /**
     * Verifies: detects slug from plugin header.
     * @return void
     */
    public function testDetectsSlugFromPluginHeader(): void
    {
        $config = (new ConfigLoader())->load(['path' => self::FIXTURE], []);
        self::assertSame('mmwp-fixture-plugin', $config->slug);
        self::assertSame('mmwp-fixture-plugin', $config->repoName());
        self::assertFalse($config->strict);
        self::assertSame('sqlite', $config->db);
    }

    /**
     * Verifies: precedence flag over env.
     * @return void
     */
    public function testPrecedenceFlagOverEnv(): void
    {
        $env = ['MMWP_SLUG' => 'from-env', 'MMWP_PCP_STRICT' => 'true', 'WP_VERSION' => '6.7', 'MMWP_DB' => 'mysql'];
        $config = (new ConfigLoader())->load(['path' => self::FIXTURE], $env);
        self::assertSame('from-env', $config->slug);
        self::assertTrue($config->strict);
        self::assertSame('6.7', $config->wpVersion);
        self::assertSame('mysql', $config->db);
        $config = (new ConfigLoader())->load(['path' => self::FIXTURE, 'slug' => 'from-flag'], $env);
        self::assertSame('from-flag', $config->slug);
    }

    /**
     * Verifies: reads config file.
     * @return void
     */
    public function testReadsConfigFile(): void
    {
        $dir = sys_get_temp_dir() . '/mmwp-cfg-' . bin2hex(random_bytes(4));
        mkdir($dir);
        file_put_contents($dir . '/mmwp-release.json', '{"slug":"x-y","php_min":"8.0","strict":true,"excludes":["*.psd"]}');
        $config = (new ConfigLoader())->load(['path' => $dir], []);
        unlink($dir . '/mmwp-release.json');
        rmdir($dir);
        self::assertSame('x-y', $config->slug);
        self::assertSame('8.0', $config->phpMin);
        self::assertTrue($config->strict);
        self::assertSame(['*.psd'], $config->excludes);
    }

    /**
     * Verifies: fails with config exit code.
     * @return void
     */
    public function testFailsWithConfigExitCode(): void
    {
        foreach ([['path' => '/nonexistent-dir'], ['path' => self::FIXTURE . '/../', 'slug' => 'Bad Slug'], ['path' => self::FIXTURE, 'format' => 'xml']] as $options) {
            try {
                (new ConfigLoader())->load($options, []);
                self::fail('Expected failure.');
            } catch (ReleaseException $e) {
                self::assertSame(ExitCode::CONFIG, $e->getExitCode());
            }
        }
    }

    /**
     * Verifies: bool parsing.
     * @return void
     */
    public function testBoolParsing(): void
    {
        self::assertTrue(ConfigLoader::bool('TRUE'));
        self::assertTrue(ConfigLoader::bool('1'));
        self::assertFalse(ConfigLoader::bool('no'));
        self::assertTrue(ConfigLoader::bool(null, true));
    }
}
