<?php

declare(strict_types=1);

namespace MammothWP\WPRelease\Deploy;

use MammothWP\WPRelease\Exception\ExitCode;
use MammothWP\WPRelease\Exception\ReleaseException;
use MammothWP\WPRelease\Http\HttpClient;

/**
 * Looks up published tags on the wordpress.org plugin SVN repository.
 */
class WporgStatusFetcher
{
    public const BASE = 'https://plugins.svn.wordpress.org/';

    /**
     * @param HttpClient $http HTTP client.
     */
    public function __construct(private readonly HttpClient $http)
    {
    }

    /**
     * Resolve the state of a version and the list of tags.
     *
     * @param string $slug    Plugin slug.
     * @param string $version Version.
     * @return array{state: WporgState, tags: list<string>}
     */
    public function fetch(string $slug, string $version): array
    {
        $base = $this->http->request('GET', self::BASE . $slug . '/');
        if ($base->status === 404) {
            return ['state' => WporgState::NotApproved, 'tags' => []];
        }
        if ($base->status === 0 || $base->status >= 500) {
            throw new ReleaseException(sprintf('wordpress.org SVN unreachable (HTTP %d).', $base->status), ExitCode::SVN);
        }
        $tagsResponse = $this->http->request('GET', self::BASE . $slug . '/tags/');
        $tags = $tagsResponse->isSuccess() ? ReleaseDecision::parseTags($tagsResponse->body) : [];
        return ['state' => ReleaseDecision::wporg($base->status, $tags, $version), 'tags' => $tags];
    }
}
