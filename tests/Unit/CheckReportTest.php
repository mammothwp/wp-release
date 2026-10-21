<?php

declare(strict_types=1);

namespace MammothWP\WPRelease\Tests\Unit;

use MammothWP\WPRelease\Check\CheckGate;
use MammothWP\WPRelease\Check\CheckResult;
use MammothWP\WPRelease\Check\PluginCheckParser;
use MammothWP\WPRelease\Check\ReportSummary;
use MammothWP\WPRelease\Exception\ExitCode;
use MammothWP\WPRelease\Exception\ReleaseException;
use PHPUnit\Framework\TestCase;

final class CheckReportTest extends TestCase
{
    private const OUTPUT = <<<'TXT'
FILE: a.php
[{"line":3,"column":2,"type":"ERROR","code":"EscapeOutput","message":"found &#039;$x&#039;","docs":""},{"line":0,"column":0,"type":"WARNING","code":"trademarked_term","message":"m","docs":""}]

FILE: readme.txt
[{"line":0,"column":0,"type":"WARNING","code":"trademarked_term","message":"m2","docs":""}]
TXT;

    /**
     * Verifies: parses file blocks and decodes entities.
     * @return void
     */
    public function testParsesFileBlocksAndDecodesEntities(): void
    {
        $findings = PluginCheckParser::parse(self::OUTPUT);
        self::assertCount(3, $findings);
        self::assertSame('a.php', $findings[0]->file);
        self::assertSame("found '\$x'", $findings[0]->message);
        self::assertSame('readme.txt', $findings[2]->file);
    }

    /**
     * Verifies: parses bare json and empty.
     * @return void
     */
    public function testParsesBareJsonAndEmpty(): void
    {
        self::assertCount(1, PluginCheckParser::parse('[{"type":"error","code":"c","message":"m"}]'));
        self::assertSame('ERROR', PluginCheckParser::parse('[{"type":"error","code":"c","message":"m"}]')[0]->type);
        self::assertSame([], PluginCheckParser::parse("Success: Checks complete. No errors found.\n"));
    }

    /**
     * Verifies: summary counts and strictness.
     * @return void
     */
    public function testSummaryCountsAndStrictness(): void
    {
        $summary = new ReportSummary(PluginCheckParser::parse(self::OUTPUT));
        self::assertSame(1, $summary->errors());
        self::assertSame(2, $summary->warnings());
        self::assertSame(['WARNING trademarked_term' => 2, 'ERROR EscapeOutput' => 1], $summary->byCode());
        self::assertFalse($summary->passes(false));
        $warningsOnly = new ReportSummary([PluginCheckParser::parse(self::OUTPUT)[1]]);
        self::assertTrue($warningsOnly->passes(false));
        self::assertFalse($warningsOnly->passes(true));
        self::assertStringContainsString('1 error(s), 2 warning(s)', $summary->toText());
    }

    /**
     * Verifies: gate rules.
     * @return void
     */
    public function testGateRules(): void
    {
        $gate = new CheckGate();
        $pass = (new CheckResult('p', '1.0.0', false, []))->toArray();
        $gate->assertPasses($pass, 'p', '1.0.0', true);
        $this->assertRefused(null, 'p', '1.0.0', false);
        $this->assertRefused($pass, 'p', '1.0.1', false);
        $this->assertRefused($pass, 'other', '1.0.0', false);
        $failing = (new CheckResult('p', '1.0.0', false, PluginCheckParser::parse(self::OUTPUT)))->toArray();
        $this->assertRefused($failing, 'p', '1.0.0', false);
        $warn = (new CheckResult('p', '1.0.0', false, [PluginCheckParser::parse(self::OUTPUT)[1]]))->toArray();
        $gate->assertPasses($warn, 'p', '1.0.0', false);
        $this->assertRefused($warn, 'p', '1.0.0', true);
    }

    /**
     * @param array<string, mixed>|null $report Report.
     */
    private function assertRefused(?array $report, string $slug, string $version, bool $strict): void
    {
        try {
            (new CheckGate())->assertPasses($report, $slug, $version, $strict);
        } catch (ReleaseException $e) {
            self::assertSame(ExitCode::DEPLOY_REFUSED, $e->getExitCode());
            return;
        }
        self::fail('Gate should refuse.');
    }
}
