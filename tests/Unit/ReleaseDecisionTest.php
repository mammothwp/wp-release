<?php

declare(strict_types=1);

namespace MammothWP\WPRelease\Tests\Unit;

use MammothWP\WPRelease\Deploy\ReleaseDecision;
use MammothWP\WPRelease\Deploy\SvnStatusParser;
use MammothWP\WPRelease\Deploy\WporgState;
use PHPUnit\Framework\TestCase;

final class ReleaseDecisionTest extends TestCase
{
    private const LISTING = '<html><body><ul><li><a href="../">..</a></li><li><a href="1.0.0/">1.0.0/</a></li>'
        . '<li><a href="1.10.0/">1.10.0/</a></li><li><a href="1.2.0/">1.2.0/</a></li></ul></body></html>';

    /**
     * Verifies: parse tags skips parent link.
     * @return void
     */
    public function testParseTagsSkipsParentLink(): void
    {
        self::assertSame(['1.0.0', '1.10.0', '1.2.0'], ReleaseDecision::parseTags(self::LISTING));
        self::assertSame([], ReleaseDecision::parseTags('<ul></ul>'));
    }

    /**
     * Verifies: wporg states.
     * @return void
     */
    public function testWporgStates(): void
    {
        $tags = ReleaseDecision::parseTags(self::LISTING);
        self::assertSame(WporgState::Released, ReleaseDecision::wporg(200, $tags, '1.2.0'));
        self::assertSame(WporgState::Pending, ReleaseDecision::wporg(200, $tags, '1.3.0'));
        self::assertSame(WporgState::NotApproved, ReleaseDecision::wporg(404, [], '1.0.0'));
    }

    /**
     * Verifies: latest uses version compare.
     * @return void
     */
    public function testLatestUsesVersionCompare(): void
    {
        self::assertSame('1.10.0', ReleaseDecision::latest(['1.0.0', '1.10.0', '1.2.0']));
        self::assertNull(ReleaseDecision::latest([]));
        self::assertTrue(ReleaseDecision::isOlderThanLatest(['1.10.0'], '1.9.0'));
        self::assertFalse(ReleaseDecision::isOlderThanLatest(['1.10.0'], '1.11.0'));
        self::assertFalse(ReleaseDecision::isOlderThanLatest([], '1.0.0'));
    }

    /**
     * Verifies: svn status parser.
     * @return void
     */
    public function testSvnStatusParser(): void
    {
        $parsed = SvnStatusParser::parse("?       trunk/new.php\n!       trunk/old.php\nM       trunk/readme.txt\n?       assets/icon-256x256.png\n");
        self::assertSame(['trunk/new.php', 'assets/icon-256x256.png'], $parsed['add']);
        self::assertSame(['trunk/old.php'], $parsed['remove']);
    }
}
