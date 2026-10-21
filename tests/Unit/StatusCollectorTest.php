<?php

declare(strict_types=1);

namespace MammothWP\WPRelease\Tests\Unit;

use MammothWP\WPRelease\Config\Config;
use MammothWP\WPRelease\Deploy\WporgStatusFetcher;
use MammothWP\WPRelease\Github\GithubClient;
use MammothWP\WPRelease\Http\HttpResponse;
use MammothWP\WPRelease\Status\StatusCollector;
use MammothWP\WPRelease\Tests\Support\FakeHttp;
use PHPUnit\Framework\TestCase;

final class StatusCollectorTest extends TestCase
{
    private const FIXTURE = __DIR__ . '/../fixtures/mmwp-fixture-plugin';

    /**
     * Verifies: reports published state.
     * @return void
     */
    public function testReportsPublishedState(): void
    {
        $http = new FakeHttp([
            'GET https://plugins.svn.wordpress.org/mmwp-fixture-plugin/' => new HttpResponse(200),
            'GET https://plugins.svn.wordpress.org/mmwp-fixture-plugin/tags/' => new HttpResponse(200, '<li><a href="1.2.0/">1.2.0/</a></li>'),
            'GET https://api.github.com/repos/mammothwp/mmwp-fixture-plugin/releases/tags/v1.2.0' => new HttpResponse(404),
        ]);
        $status = (new StatusCollector(new WporgStatusFetcher($http), new GithubClient($http, '')))
            ->collect(new Config(self::FIXTURE, 'mmwp-fixture-plugin'));
        self::assertSame('1.2.0', $status['header_version']);
        self::assertSame('1.2.0', $status['readme_stable_tag']);
        self::assertSame('yes', $status['on_wordpress_org']);
        self::assertSame('no', $status['on_github']);
        self::assertSame('nothing (already released)', $status['deploy_wporg_would']);
        self::assertSame('create the GitHub release and upload the zip', $status['release_github_would']);
    }

    /**
     * Verifies: unreachable services report unknown.
     * @return void
     */
    public function testUnreachableServicesReportUnknown(): void
    {
        $http = new FakeHttp([
            'GET https://plugins.svn.wordpress.org/mmwp-fixture-plugin/' => new HttpResponse(0),
            'GET https://api.github.com/repos/mammothwp/mmwp-fixture-plugin/releases/tags/v1.2.0' => new HttpResponse(503),
        ]);
        $status = (new StatusCollector(new WporgStatusFetcher($http), new GithubClient($http, '')))
            ->collect(new Config(self::FIXTURE, 'mmwp-fixture-plugin'));
        self::assertSame('unknown', $status['on_wordpress_org']);
        self::assertSame('unknown', $status['on_github']);
    }
}
