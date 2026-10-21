<?php

declare(strict_types=1);

namespace MammothWP\WPRelease\Tests\Unit;

use MammothWP\WPRelease\Plugin\ChangelogExtractor;
use MammothWP\WPRelease\Plugin\HeaderParser;
use MammothWP\WPRelease\Plugin\PluginReader;
use MammothWP\WPRelease\Plugin\ReadmeParser;
use PHPUnit\Framework\TestCase;

final class PluginParsingTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/../fixtures/mmwp-fixture-plugin';

    /**
     * Verifies: header parser reads known headers.
     * @return void
     */
    public function testHeaderParserReadsKnownHeaders(): void
    {
        $headers = HeaderParser::parse((string) file_get_contents(self::FIXTURE . '/mmwp-fixture-plugin.php'));
        self::assertSame('1.2.0', $headers['version']);
        self::assertSame('mmwp-fixture-plugin', $headers['text domain']);
        self::assertSame('7.4', $headers['requires php']);
        self::assertSame('GPL-2.0-or-later', $headers['license']);
    }

    /**
     * Verifies: header parser ignores missing and strips comment end.
     * @return void
     */
    public function testHeaderParserIgnoresMissingAndStripsCommentEnd(): void
    {
        $headers = HeaderParser::parse("<?php\n/* Plugin Name: X\n Version: 3.0 */");
        self::assertSame('3.0', $headers['version']);
        self::assertArrayNotHasKey('text domain', $headers);
    }

    /**
     * Verifies: readme parser stops at first section.
     * @return void
     */
    public function testReadmeParserStopsAtFirstSection(): void
    {
        $readme = ReadmeParser::parse((string) file_get_contents(self::FIXTURE . '/readme.txt'));
        self::assertSame('1.2.0', $readme->stableTag());
        self::assertNotNull($readme->testedUpTo());
        $body = ReadmeParser::parse("=== X ===\nStable tag: 1\n\n== Description ==\nTested up to: 9.9\n");
        self::assertNull($body->testedUpTo());
    }

    /**
     * Verifies: changelog extraction picks matching section.
     * @return void
     */
    public function testChangelogExtractionPicksMatchingSection(): void
    {
        $readme = (string) file_get_contents(self::FIXTURE . '/readme.txt');
        self::assertSame("* Added the footer greeting.\n* Improved things.", ChangelogExtractor::extract($readme, '1.2.0'));
        self::assertSame('* Initial fixture release.', ChangelogExtractor::extract($readme, '1.1.0'));
        self::assertNull(ChangelogExtractor::extract($readme, '9.9.9'));
    }

    /**
     * Verifies: changelog heading variants.
     * @return void
     */
    public function testChangelogHeadingVariants(): void
    {
        $readme = "== Changelog ==\n\n= v2.0.0 - 2026-01-01 =\n* A\n\n= 1.0.0 =\n* B\n\n== Upgrade Notice ==\n= 1.0.0 =\nNo.\n";
        self::assertSame('* A', ChangelogExtractor::extract($readme, '2.0.0'));
        self::assertSame('* B', ChangelogExtractor::extract($readme, '1.0.0'));
        self::assertNull(ChangelogExtractor::extract("== Description ==\n= 1.0.0 =\nx", '1.0.0'));
    }

    /**
     * Verifies: reader detects slug and reads plugin.
     * @return void
     */
    public function testReaderDetectsSlugAndReadsPlugin(): void
    {
        $reader = new PluginReader();
        self::assertSame(['mmwp-fixture-plugin'], $reader->detectSlugs(self::FIXTURE));
        $info = $reader->read(self::FIXTURE, 'mmwp-fixture-plugin');
        self::assertTrue($info->mainFileExists);
        self::assertSame('1.2.0', $info->version());
        self::assertFalse($reader->read(self::FIXTURE, 'nope')->mainFileExists);
    }
}
