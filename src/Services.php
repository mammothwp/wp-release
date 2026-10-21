<?php

declare(strict_types=1);

namespace MammothWP\WPRelease;

use MammothWP\WPRelease\Build\Builder;
use MammothWP\WPRelease\Check\CheckRunner;
use MammothWP\WPRelease\Check\TempSite;
use MammothWP\WPRelease\Deploy\SvnDeployer;
use MammothWP\WPRelease\Deploy\WporgDeployer;
use MammothWP\WPRelease\Deploy\WporgStatusFetcher;
use MammothWP\WPRelease\Github\GithubClient;
use MammothWP\WPRelease\Github\GithubReleaser;
use MammothWP\WPRelease\Http\CurlHttpClient;
use MammothWP\WPRelease\Http\HttpClient;
use MammothWP\WPRelease\Output\Reporter;
use MammothWP\WPRelease\Process\CommandRunner;
use MammothWP\WPRelease\Process\Runner;
use MammothWP\WPRelease\Status\StatusCollector;

/**
 * Wires the object graph for one run.
 */
class Services
{
    public readonly CommandRunner $runner;

    public readonly HttpClient $http;

    /**
     * @param Reporter              $reporter Logger.
     * @param array<string, string> $env      Environment.
     */
    public function __construct(private readonly Reporter $reporter, private readonly array $env)
    {
        $this->reporter->addSecret($env['GITHUB_TOKEN'] ?? null);
        $this->reporter->addSecret($env['WPORG_PASSWORD'] ?? null);
        $this->runner = new Runner($reporter);
        $this->http = new CurlHttpClient($this->runner);
    }

    /**
     * Builder.
     *
     * @return Builder
     */
    public function builder(): Builder
    {
        return new Builder($this->runner, $this->reporter);
    }

    /**
     * Check runner.
     *
     * @return CheckRunner
     */
    public function checkRunner(): CheckRunner
    {
        return new CheckRunner(new TempSite($this->runner, $this->reporter), $this->builder(), $this->reporter);
    }

    /**
     * wordpress.org deployer.
     *
     * @return WporgDeployer
     */
    public function wporgDeployer(): WporgDeployer
    {
        return new WporgDeployer(
            new WporgStatusFetcher($this->http),
            new SvnDeployer($this->runner, $this->reporter),
            $this->reporter,
            $this->env
        );
    }

    /**
     * GitHub releaser.
     *
     * @return GithubReleaser
     */
    public function githubReleaser(): GithubReleaser
    {
        return new GithubReleaser($this->github(), $this->reporter);
    }

    /**
     * Status collector.
     *
     * @return StatusCollector
     */
    public function statusCollector(): StatusCollector
    {
        return new StatusCollector(new WporgStatusFetcher($this->http), $this->github());
    }

    /**
     * GitHub client.
     *
     * @return GithubClient
     */
    private function github(): GithubClient
    {
        return new GithubClient($this->http, $this->env['GITHUB_TOKEN'] ?? '');
    }
}
