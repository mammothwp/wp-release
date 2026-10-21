<?php

declare(strict_types=1);

namespace MammothWP\WPRelease\Tests\Unit;

use MammothWP\WPRelease\Check\CheckResult;
use MammothWP\WPRelease\Config\Config;
use MammothWP\WPRelease\Deploy\SvnDeployer;
use MammothWP\WPRelease\Deploy\WporgDeployer;
use MammothWP\WPRelease\Deploy\WporgStatusFetcher;
use MammothWP\WPRelease\Exception\ExitCode;
use MammothWP\WPRelease\Exception\ReleaseException;
use MammothWP\WPRelease\Http\HttpResponse;
use MammothWP\WPRelease\Output\Reporter;
use MammothWP\WPRelease\Tests\Support\FakeHttp;
use MammothWP\WPRelease\Tests\Support\FakeRunner;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use Symfony\Component\Filesystem\Filesystem;

final class DeployFlowTest extends TestCase
{
    private const SLUG = 'mmwp-fixture-plugin';
    private const BASE = 'https://plugins.svn.wordpress.org/' . self::SLUG . '/';

    private string $root = '';

    /**
     * Set up.
     * @return void
     */
    protected function setUp(): void
    {
        $this->root = sys_get_temp_dir() . '/mmwp-deploy-' . bin2hex(random_bytes(4));
        (new Filesystem())->mirror(__DIR__ . '/../fixtures/mmwp-fixture-plugin', $this->root);
        mkdir($this->root . '/dist/' . self::SLUG, 0777, true);
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
     * Verifies: already released is idempotent and never touches svn.
     * @return void
     */
    public function testAlreadyReleasedIsIdempotentAndNeverTouchesSvn(): void
    {
        [$deployer, $runner] = $this->deployer($this->http(200, '1.2.0'));
        $result = $deployer->deploy($this->config(), $this->passingCheck());
        self::assertSame('already-released', $result['status']);
        self::assertSame([], $runner->commands);
    }

    /**
     * Verifies: not approved exits zero unless configured to fail.
     * @return void
     */
    public function testNotApprovedExitsZeroUnlessConfiguredToFail(): void
    {
        [$deployer] = $this->deployer($this->http(404));
        self::assertSame('not-approved', $deployer->deploy($this->config(), $this->passingCheck())['status']);
        $this->expectException(ReleaseException::class);
        $this->expectExceptionCode(ExitCode::NOT_APPROVED);
        $deployer->deploy($this->config(failIfUnapproved: true), $this->passingCheck());
    }

    /**
     * Verifies: refuses without passing check unless forced.
     * @return void
     */
    public function testRefusesWithoutPassingCheckUnlessForced(): void
    {
        [$deployer, $runner] = $this->deployer($this->http(200, '1.1.0'));
        try {
            $deployer->deploy($this->config());
            self::fail('Expected refusal.');
        } catch (ReleaseException $e) {
            self::assertSame(ExitCode::DEPLOY_REFUSED, $e->getExitCode());
        }
        self::assertSame('dry-run', $deployer->deploy($this->config(force: true, dryRun: true))['status']);
        self::assertNotSame([], $runner->commands);
    }

    /**
     * Verifies: reads stored plugin check json.
     * @return void
     */
    public function testReadsStoredPluginCheckJson(): void
    {
        file_put_contents($this->root . '/plugin-check.json', (string) json_encode($this->passingCheck()->toArray()));
        [$deployer] = $this->deployer($this->http(200, '1.2.0'));
        self::assertSame('already-released', $deployer->deploy($this->config())['status']);
    }

    /**
     * Verifies: dry run syncs tags and stops before commit.
     * @return void
     */
    public function testDryRunSyncsTagsAndStopsBeforeCommit(): void
    {
        [$deployer, $runner] = $this->deployer($this->http(200, '1.1.0'), "?       trunk/new.php\n!       trunk/old.php\n");
        $result = $deployer->deploy($this->config(dryRun: true), $this->passingCheck());
        self::assertSame('dry-run', $result['status']);
        $lines = implode("\n", $runner->lines());
        self::assertStringContainsString('rsync -a --delete --exclude=.svn ' . $this->root . '/dist/' . self::SLUG . '/', $lines);
        self::assertStringContainsString('/.wordpress-org/ ', $lines);
        self::assertStringContainsString('svn add --force --parents -q trunk/new.php@', $lines);
        self::assertStringContainsString('svn rm --force -q trunk/old.php@', $lines);
        self::assertStringContainsString('svn cp trunk tags/1.2.0', $lines);
        self::assertStringNotContainsString('svn commit', $lines);
    }

    /**
     * Verifies: commit uses credentials and masks them.
     * @return void
     */
    public function testCommitUsesCredentialsAndMasksThem(): void
    {
        $output = new BufferedOutput();
        $reporter = new Reporter($output, $output);
        $runner = new FakeRunner();
        $env = ['WPORG_USERNAME' => 'nikos-wp', 'WPORG_PASSWORD' => 'hunter2-secret'];
        $deployer = new WporgDeployer(new WporgStatusFetcher($this->http(200, '1.1.0')), new SvnDeployer($runner, $reporter), $reporter, $env);
        self::assertSame('deployed', $deployer->deploy($this->config(), $this->passingCheck())['status']);
        $commit = implode(' ', array_values(array_filter($runner->commands, static fn (array $c): bool => ($c[1] ?? '') === 'commit'))[0]);
        self::assertStringContainsString('--password hunter2-secret', $commit);
        self::assertStringNotContainsString('hunter2-secret', $reporter->mask($commit));
        self::assertStringNotContainsString('nikos-wp', $reporter->mask($commit));
    }

    /**
     * Verifies: missing credentials fail before any svn call.
     * @return void
     */
    public function testMissingCredentialsFailBeforeAnySvnCall(): void
    {
        $runner = new FakeRunner();
        $reporter = new Reporter(new BufferedOutput(), new BufferedOutput());
        $deployer = new WporgDeployer(new WporgStatusFetcher($this->http(200, '1.1.0')), new SvnDeployer($runner, $reporter), $reporter, []);
        try {
            $deployer->deploy($this->config(), $this->passingCheck());
            self::fail('Expected config failure.');
        } catch (ReleaseException $e) {
            self::assertSame(ExitCode::CONFIG, $e->getExitCode());
        }
        self::assertSame([], $runner->commands);
    }

    /**
     * @return array{0: WporgDeployer, 1: FakeRunner}
     */
    private function deployer(FakeHttp $http, string $svnStatus = ''): array
    {
        $runner = new FakeRunner($svnStatus);
        $reporter = new Reporter(new BufferedOutput(), new BufferedOutput());
        $env = ['WPORG_USERNAME' => 'user', 'WPORG_PASSWORD' => 'pass-word'];
        return [new WporgDeployer(new WporgStatusFetcher($http), new SvnDeployer($runner, $reporter), $reporter, $env), $runner];
    }

    /**
     * Http.
     *
     * @param int $baseStatus Value.
     * @param string ...$tags Tags.
     * @return FakeHttp
     */
    private function http(int $baseStatus, string ...$tags): FakeHttp
    {
        $listing = '<ul><li><a href="../">..</a></li>';
        foreach ($tags as $tag) {
            $listing .= '<li><a href="' . $tag . '/">' . $tag . '/</a></li>';
        }
        return new FakeHttp([
            'GET ' . self::BASE => new HttpResponse($baseStatus, '<html></html>'),
            'GET ' . self::BASE . 'tags/' => new HttpResponse($baseStatus === 404 ? 404 : 200, $listing . '</ul>'),
        ]);
    }

    /**
     * Passing check.
     *
     * @return CheckResult
     */
    private function passingCheck(): CheckResult
    {
        return new CheckResult(self::SLUG, '1.2.0', false, []);
    }

    /**
     * Config.
     *
     * @param bool $dryRun Value.
     * @param bool $force Value.
     * @param bool $failIfUnapproved Value.
     * @return Config
     */
    private function config(bool $dryRun = false, bool $force = false, bool $failIfUnapproved = false): Config
    {
        return new Config($this->root, self::SLUG, dryRun: $dryRun, force: $force, failIfUnapproved: $failIfUnapproved);
    }
}
