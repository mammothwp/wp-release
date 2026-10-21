<?php

declare(strict_types=1);

namespace MammothWP\WPRelease\Plugin;

/**
 * Extracts one version's notes from the "== Changelog ==" section of a readme.txt.
 */
final class ChangelogExtractor
{
    /**
     * Return the changelog entry body for a version, or null when absent.
     *
     * @param string $readme  readme.txt contents.
     * @param string $version Version such as 1.2.0.
     * @return string|null
     */
    public static function extract(string $readme, string $version): ?string
    {
        $inChangelog = false;
        $capturing = false;
        $body = [];
        foreach (preg_split('/\R/', $readme) ?: [] as $line) {
            if (preg_match('/^==\s*(.+?)\s*==\s*$/', $line, $section) === 1 && !str_starts_with($line, '===')) {
                $inChangelog = strtolower($section[1]) === 'changelog';
                $capturing = false;
                continue;
            }
            if (!$inChangelog) {
                continue;
            }
            if (preg_match('/^=\s*([^=].*?)\s*=\s*$/', $line, $entry) === 1) {
                $capturing = self::headingMatches($entry[1], $version);
                continue;
            }
            if ($capturing) {
                $body[] = $line;
            }
        }
        $text = trim(implode("\n", $body));
        return $text === '' ? null : $text;
    }

    /**
     * Whether a changelog heading such as "v1.2.0 - 2026-01-01" refers to the version.
     *
     * @param string $heading Heading text without the surrounding "=".
     * @param string $version Version.
     * @return bool
     */
    private static function headingMatches(string $heading, string $version): bool
    {
        $token = preg_split('/[\s\-–—(]+/', (string) preg_replace('/^(version\s+|v)/i', '', $heading)) ?: [];
        return ($token[0] ?? '') === $version;
    }
}
