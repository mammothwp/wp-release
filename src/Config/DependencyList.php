<?php

declare(strict_types=1);

namespace MammothWP\WPRelease\Config;

/**
 * Parses a list of wordpress.org plugin slugs (MMWP_BROWSER_DEPS, --deps, `Requires Plugins:`).
 */
final class DependencyList
{
    /**
     * Split on spaces and commas, drop empties and duplicates, keep order.
     *
     * @param string|null $raw Raw value.
     * @return list<string>
     */
    public static function parse(?string $raw): array
    {
        if ($raw === null) {
            return [];
        }
        $parts = preg_split('/[\s,]+/', trim($raw), -1, PREG_SPLIT_NO_EMPTY);
        return array_values(array_unique($parts === false ? [] : $parts));
    }
}
