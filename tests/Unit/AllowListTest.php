<?php

declare(strict_types=1);

namespace MammothWP\WPRelease\Tests\Unit;

use MammothWP\WPRelease\Check\AllowList;
use MammothWP\WPRelease\Check\CheckGate;
use MammothWP\WPRelease\Check\CheckResult;
use MammothWP\WPRelease\Check\Finding;
use MammothWP\WPRelease\Check\PluginCheckParser;
use MammothWP\WPRelease\Config\ConfigLoader;
use MammothWP\WPRelease\Exception\ExitCode;
use MammothWP\WPRelease\Exception\ReleaseException;
use PHPUnit\Framework\TestCase;

/**
 * Plugin Check strict-mode allow-list.
 */
final class AllowListTest extends TestCase
{
    /** Recommended MammothWP rule (keep in sync with README). */
    private const RULE = '(?i)^trademarked_term: (?=.*(?<![a-z0-9])mammothwp)(?=.*restricted term \W?wp\W)';

    /**
     * @return list<Finding> The three MammothWP trademark warnings of the fixture.
     */
    private function fixture(): array
    {
        return PluginCheckParser::parse((string) file_get_contents(__DIR__ . '/../fixtures/plugin-check/mammothwp-trademark.txt'));
    }

    /**
     * @param list<Finding> $findings Findings.
     * @param list<string>  $rules    Allow rules.
     * @return array<string, mixed>
     */
    private function report(array $findings, array $rules): array
    {
        return (new CheckResult('p', '1.0.0', true, $findings, new AllowList($rules)))->toArray();
    }

    /**
     * @param array<string, mixed> $report Report.
     * @return bool
     */
    private function strictPasses(array $report): bool
    {
        try {
            (new CheckGate())->assertPasses($report, 'p', '1.0.0', true);
            return true;
        } catch (ReleaseException $e) {
            self::assertSame(ExitCode::DEPLOY_REFUSED, $e->getExitCode());
            return false;
        }
    }

    /**
     * Verifies: MammothWP trademark warnings are allowed and strict passes.
     * @return void
     */
    public function testMammothTrademarkWarningsAllowed(): void
    {
        $findings = $this->fixture();
        self::assertCount(3, $findings);
        $result = new CheckResult('p', '1.0.0', true, $findings, new AllowList([self::RULE]));
        self::assertTrue($result->passed());
        $report = $result->toArray();
        self::assertSame(0, $report['errors']);
        self::assertSame(0, $report['warnings']);
        self::assertSame(3, $report['allowed']);
        self::assertTrue($this->strictPasses($report));
        $text = $result->summary()->toText();
        self::assertStringContainsString('0 error(s), 0 warning(s), 3 allowed', $text);
        self::assertStringContainsString('ALLOWED: trademarked_term: ', $text);
        self::assertStringContainsString('(rule: ' . self::RULE . ')', $text);
    }

    /**
     * Verifies: another, non-allowed warning still fails strict.
     * @return void
     */
    public function testOtherWarningStillFails(): void
    {
        $other = new Finding('a.php', 1, 1, 'WARNING', 'NonPrefixedVariable', 'x');
        $result = new CheckResult('p', '1.0.0', true, [...$this->fixture(), $other], new AllowList([self::RULE]));
        self::assertFalse($result->passed());
        self::assertSame(1, $result->toArray()['warnings']);
        self::assertFalse($this->strictPasses($result->toArray()));
    }

    /**
     * Verifies: wordpress / woocommerce / non-Mammoth names are not matched.
     * @return void
     */
    public function testRegexIsNarrow(): void
    {
        $msg = static fn (string $name, string $term): string => sprintf(
            'The plugin name includes a restricted term. Your chosen plugin name - "%s" - contains the restricted term "%s" which cannot be used.',
            $name,
            $term
        );
        $cases = [
            ['MammothWP Logs', 'wordpress'],
            ['MammothWP Logs', 'woocommerce'],
            ['Acme WP Logs', 'wp'],
            ['Mammoth Logs for WordPress', 'wordpress'],
        ];
        foreach ($cases as [$name, $term]) {
            $report = $this->report([new Finding('a.php', 0, 0, 'WARNING', 'trademarked_term', $msg($name, $term))], [self::RULE]);
            self::assertFalse($this->strictPasses($report), "$name / $term must not be allowed");
        }
        $ok = $this->report([new Finding('a.php', 0, 0, 'WARNING', 'trademarked_term', $msg('MammothWP Logs', 'wp'))], [self::RULE]);
        self::assertTrue($this->strictPasses($ok));
    }

    /**
     * Verifies: an ERROR is never allowed, even when the regex matches.
     * @return void
     */
    public function testErrorsNeverAllowed(): void
    {
        $error = new Finding('a.php', 0, 0, 'ERROR', 'trademarked_term', 'MammothWP restricted term "wp"');
        $result = new CheckResult('p', '1.0.0', true, [$error], new AllowList([self::RULE, '.*']));
        self::assertFalse($result->passed());
        self::assertSame(1, $result->toArray()['errors']);
        self::assertSame(0, $result->toArray()['allowed']);
        $report = $result->toArray();
        self::assertFalse($this->strictPasses($report));
    }

    /**
     * Verifies: nothing configured means nothing allowed.
     * @return void
     */
    public function testNoAllowConfiguredFails(): void
    {
        $report = $this->report($this->fixture(), []);
        self::assertSame(3, $report['warnings']);
        self::assertSame(0, $report['allowed']);
        self::assertFalse($this->strictPasses($report));
    }

    /**
     * Verifies: env splitting and config merge/validation.
     * @return void
     */
    public function testEnvSplittingAndConfig(): void
    {
        self::assertSame(['a', 'b', 'c'], AllowList::splitEnv("a||b\n c \n"));
        self::assertSame([], AllowList::splitEnv(null));
        $dir = sys_get_temp_dir() . '/mmwp-allow-' . bin2hex(random_bytes(4));
        mkdir($dir);
        file_put_contents($dir . '/mmwp-release.json', '{"slug":"p","allow":["f1"]}');
        $loader = new ConfigLoader();
        $config = $loader->load(['path' => $dir, 'allow' => ['cli']], ['MMWP_PCP_ALLOW' => 'e1||e2']);
        self::assertSame(['f1', 'e1', 'e2', 'cli'], $config->allow);
        $this->expectException(ReleaseException::class);
        try {
            $loader->load(['path' => $dir, 'allow' => ['(unclosed']], []);
        } finally {
            unlink($dir . '/mmwp-release.json');
            rmdir($dir);
        }
    }
}
