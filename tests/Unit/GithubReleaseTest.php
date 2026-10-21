<?php

declare(strict_types=1);

namespace MammothWP\WPRelease\Tests\Unit;

use MammothWP\WPRelease\Config\Config;
use MammothWP\WPRelease\Exception\ExitCode;
use MammothWP\WPRelease\Exception\ReleaseException;
use MammothWP\WPRelease\Github\GithubClient;
use MammothWP\WPRelease\Github\GithubReleaser;
use MammothWP\WPRelease\Http\HttpResponse;
use MammothWP\WPRelease\Output\Reporter;
use MammothWP\WPRelease\Tests\Support\FakeHttp;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Filesystem\Filesystem;

final class GithubReleaseTest extends TestCase
{
    private const API = 'https://api.github.com/repos/mammothwp/mmwp-fixture-plugin/releases';

    private string $root = '';

    /**
     * Set up.
     * @return void
     */
    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/mmwp-gh-' . bin2hex(random_bytes(4));
        (new Filesystem())->mirror(__DIR__ . '/../fixtures/mmwp-fixture-plugin', $this->root);
        file_put_contents($this->root . '/mmwp-fixture-plugin.zip', 'zip');
    }

    /**
     * Tear down.
     * @return void
     */
    protected function tearDown(): void
    {
        (new Filesystem())->remove($this->root);
    }

    /**
     * Verifies: existing release exits without creating.
     * @return void
     */
    public function testExistingReleaseExitsWithoutCreating(): void
    {
        $http = new FakeHttp(['GET ' . self::API . '/tags/v1.2.0' => new HttpResponse(200, '{"id":5}')]);
        $result = $this->releaser($http, 'tok')->release($this->config());
        self::assertSame('already-released', $result['status']);
        self::assertSame(['GET ' . self::API . '/tags/v1.2.0'], $http->requests);
    }

    /**
     * Verifies: creates release and uploads zip.
     * @return void
     */
    public function testCreatesReleaseAndUploadsZip(): void
    {
        $http = new FakeHttp([
            'POST ' . self::API => new HttpResponse(201, '{"id":42}'),
            'POST https://uploads.github.com/repos/mammothwp/mmwp-fixture-plugin/releases/42/assets?name=mmwp-fixture-plugin.zip' => new HttpResponse(201, '{}'),
        ]);
        $result = $this->releaser($http, 'tok')->release($this->config());
        self::assertSame('created', $result['status']);
        self::assertSame(42, $result['release_id']);
        self::assertCount(3, $http->requests);
    }

    /**
     * Verifies: dry run creates nothing and shows changelog notes.
     * @return void
     */
    public function testDryRunCreatesNothingAndShowsChangelogNotes(): void
    {
        $http = new FakeHttp();
        $result = $this->releaser($http, '')->release($this->config(true));
        self::assertSame('dry-run', $result['status']);
        self::assertSame("* Added the footer greeting.\n* Improved things.", $result['notes']);
        self::assertCount(1, $http->requests);
    }

    /**
     * Verifies: missing token fails with github exit code.
     * @return void
     */
    public function testMissingTokenFailsWithGithubExitCode(): void
    {
        $this->expectException(ReleaseException::class);
        $this->expectExceptionCode(ExitCode::GITHUB);
        $this->releaser(new FakeHttp(), '')->release($this->config());
    }

    /**
     * Verifies: api error on lookup fails.
     * @return void
     */
    public function testApiErrorOnLookupFails(): void
    {
        $http = new FakeHttp(['GET ' . self::API . '/tags/v1.2.0' => new HttpResponse(500, '{"message":"boom"}')]);
        $this->expectException(ReleaseException::class);
        $this->expectExceptionCode(ExitCode::GITHUB);
        $this->releaser($http, 'tok')->release($this->config());
    }

    /**
     * Releaser.
     *
     * @param FakeHttp $http Value.
     * @param string $token Value.
     * @return GithubReleaser
     */
    private function releaser(FakeHttp $http, string $token): GithubReleaser
    {
        return new GithubReleaser(new GithubClient($http, $token), new Reporter(new BufferedOutput(), new BufferedOutput()));
    }

    /**
     * Config.
     *
     * @param bool $dryRun Value.
     * @return Config
     */
    private function config(bool $dryRun = false): Config
    {
        return new Config($this->root, 'mmwp-fixture-plugin', dryRun: $dryRun);
    }
}
