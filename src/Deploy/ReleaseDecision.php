<?php

declare(strict_types=1);

namespace MammothWP\WPRelease\Deploy;

/**
 * Pure idempotency decisions for wordpress.org and GitHub.
 */
final class ReleaseDecision
{
    /**
     * Parse an SVN directory listing (HTML from plugins.svn.wordpress.org) into tag names.
     *
     * @param string $html Listing HTML.
     * @return list<string>
     */
    public static function parseTags(string $html): array
    {
        preg_match_all('/<li><a href="(?!\.\.?\/)([^"?\/][^"]*)\/">/', $html, $matches);
        return array_map('rawurldecode', $matches[1]);
    }

    /**
     * Decide the wordpress.org state.
     *
     * @param int          $baseStatus HTTP status of /<slug>/ (404 = repository does not exist).
     * @param list<string> $tags       Existing tags.
     * @param string       $version    Version to release.
     * @return WporgState
     */
    public static function wporg(int $baseStatus, array $tags, string $version): WporgState
    {
        if ($baseStatus === 404) {
            return WporgState::NotApproved;
        }
        return in_array($version, $tags, true) ? WporgState::Released : WporgState::Pending;
    }

    /**
     * Highest version in a tag list, or null.
     *
     * @param list<string> $tags Tags.
     * @return string|null
     */
    public static function latest(array $tags): ?string
    {
        $latest = null;
        foreach ($tags as $tag) {
            if ($latest === null || version_compare($tag, $latest, '>')) {
                $latest = $tag;
            }
        }
        return $latest;
    }

    /**
     * Whether the version is lower than the highest published tag (suspicious rollback).
     *
     * @param list<string> $tags    Tags.
     * @param string       $version Version to release.
     * @return bool
     */
    public static function isOlderThanLatest(array $tags, string $version): bool
    {
        $latest = self::latest($tags);
        return $latest !== null && version_compare($version, $latest, '<');
    }
}
