<?php

declare(strict_types=1);

namespace MammothWP\WPRelease\Deploy;

/**
 * Parses `svn status` output into paths to add and remove.
 */
final class SvnStatusParser
{
    /**
     * Split status lines into unversioned ("?") and missing ("!") paths.
     *
     * @param string $status Output of `svn status`.
     * @return array{add: list<string>, remove: list<string>}
     */
    public static function parse(string $status): array
    {
        $result = ['add' => [], 'remove' => []];
        foreach (preg_split('/\R/', $status) ?: [] as $line) {
            if (preg_match('/^([?!])\s+(.+)$/', $line, $match) !== 1) {
                continue;
            }
            $result[$match[1] === '?' ? 'add' : 'remove'][] = $match[2];
        }
        return $result;
    }
}
