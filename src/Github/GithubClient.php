<?php

declare(strict_types=1);

namespace MammothWP\WPRelease\Github;

use MammothWP\WPRelease\Exception\ExitCode;
use MammothWP\WPRelease\Exception\ReleaseException;
use MammothWP\WPRelease\Http\HttpClient;
use MammothWP\WPRelease\Http\HttpResponse;

/**
 * Minimal GitHub Releases API client.
 */
class GithubClient
{
    private const API = 'https://api.github.com';
    private const UPLOADS = 'https://uploads.github.com';

    /**
     * @param HttpClient $http  HTTP client.
     * @param string     $token GitHub token (may be empty for read-only lookups).
     */
    public function __construct(private readonly HttpClient $http, private readonly string $token)
    {
    }

    /**
     * Whether a release exists for the tag.
     *
     * @param string $owner Owner.
     * @param string $repo  Repository.
     * @param string $tag   Tag name such as v1.2.0.
     * @return bool
     */
    public function releaseExists(string $owner, string $repo, string $tag): bool
    {
        $response = $this->http->request('GET', sprintf('%s/repos/%s/%s/releases/tags/%s', self::API, $owner, $repo, rawurlencode($tag)), $this->headers());
        if ($response->status === 404) {
            return false;
        }
        $this->assertOk($response, 'looking up release ' . $tag);
        return true;
    }

    /**
     * Create a release and return its id.
     *
     * @param string $owner Owner.
     * @param string $repo  Repository.
     * @param string $tag   Tag name.
     * @param string $notes Release notes.
     * @return int
     */
    public function createRelease(string $owner, string $repo, string $tag, string $notes): int
    {
        $this->requireToken();
        $payload = (string) json_encode(['tag_name' => $tag, 'name' => $tag, 'body' => $notes, 'draft' => false, 'prerelease' => false]);
        $response = $this->http->request('POST', sprintf('%s/repos/%s/%s/releases', self::API, $owner, $repo), $this->headers(), $payload);
        $this->assertOk($response, 'creating release ' . $tag);
        return (int) ($response->json()['id'] ?? 0);
    }

    /**
     * Upload a zip asset to a release.
     *
     * @param string $owner     Owner.
     * @param string $repo      Repository.
     * @param int    $releaseId Release id.
     * @param string $file      Local file.
     * @return void
     */
    public function uploadAsset(string $owner, string $repo, int $releaseId, string $file): void
    {
        $this->requireToken();
        $url = sprintf('%s/repos/%s/%s/releases/%d/assets?name=%s', self::UPLOADS, $owner, $repo, $releaseId, rawurlencode(basename($file)));
        $headers = [...$this->headers(), 'Content-Type' => 'application/zip'];
        $this->assertOk($this->http->request('POST', $url, $headers, null, $file), 'uploading ' . basename($file));
    }

    /**
     * Request headers.
     *
     * @return array<string, string>
     */
    private function headers(): array
    {
        $headers = ['Accept' => 'application/vnd.github+json', 'X-GitHub-Api-Version' => '2022-11-28'];
        return $this->token === '' ? $headers : [...$headers, 'Authorization' => 'Bearer ' . $this->token];
    }

    /**
     * Throw GITHUB unless the token is set.
     *
     * @return void
     */
    private function requireToken(): void
    {
        if ($this->token === '') {
            throw new ReleaseException('GITHUB_TOKEN is required to create a GitHub release.', ExitCode::GITHUB);
        }
    }

    /**
     * Throw GITHUB on a non-2xx response.
     *
     * @param HttpResponse $response Response.
     * @param string       $action   What was attempted.
     * @return void
     */
    private function assertOk(HttpResponse $response, string $action): void
    {
        if ($response->isSuccess()) {
            return;
        }
        $detail = (string) ($response->json()['message'] ?? '');
        throw new ReleaseException(sprintf('GitHub API error (HTTP %d) while %s. %s', $response->status, $action, $detail), ExitCode::GITHUB);
    }
}
