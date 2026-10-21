<?php

declare(strict_types=1);

namespace MammothWP\WPRelease\Tests\Unit;

use MammothWP\WPRelease\Output\Reporter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;

final class ReporterTest extends TestCase
{
    /**
     * Verifies: secrets are masked in logs and results.
     * @return void
     */
    public function testSecretsAreMaskedInLogsAndResults(): void
    {
        $out = new BufferedOutput();
        $err = new BufferedOutput();
        $reporter = new Reporter($out, $err, false, 'json');
        $reporter->addSecret('s3cr3t-token');
        $reporter->command(['curl', '-H', 'Authorization: Bearer s3cr3t-token']);
        $reporter->result('t', ['k' => 'value s3cr3t-token']);
        self::assertStringNotContainsString('s3cr3t-token', $out->fetch() . $err->fetch());
    }

    /**
     * Verifies: json result is valid json on stdout only.
     * @return void
     */
    public function testJsonResultIsValidJsonOnStdoutOnly(): void
    {
        $out = new BufferedOutput();
        $err = new BufferedOutput();
        $reporter = new Reporter($out, $err, false, 'json');
        $reporter->info('log line');
        $reporter->result('t', ['a' => 1, 'b' => ['x']]);
        self::assertSame(['a' => 1, 'b' => ['x']], json_decode($out->fetch(), true));
        self::assertSame("log line\n", $err->fetch());
    }

    /**
     * Verifies: debug lines only in debug mode.
     * @return void
     */
    public function testDebugLinesOnlyInDebugMode(): void
    {
        $err = new BufferedOutput();
        (new Reporter(new BufferedOutput(), $err))->debug('hidden');
        self::assertSame('', $err->fetch());
        (new Reporter(new BufferedOutput(), $err, true))->debug('shown');
        self::assertSame("shown\n", $err->fetch());
    }
}
