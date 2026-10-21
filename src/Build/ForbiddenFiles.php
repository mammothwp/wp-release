<?php

declare(strict_types=1);

namespace MammothWP\WPRelease\Build;

/**
 * Detects files that must never ship in a release zip.
 *
 * Patterns in STRICT apply to every path segment, including vendor/. Patterns in OUTSIDE_VENDOR
 * (tests, phpunit*, phpcs*, phpstan*) apply only outside vendor/, because third-party packages
 * legitimately ship such files.
 */
final class ForbiddenFiles
{
    private const STRICT = ['.git', 'node_modules', '.gitlab-ci.yml', '.wordpress-org', '.env*', '*.log'];
    private const OUTSIDE_VENDOR = ['tests', 'phpunit*', 'phpcs*', 'phpstan*'];

    /**
     * @param list<string> $extra Additional patterns (fnmatch, matched against every path segment and the full path).
     */
    public function __construct(private readonly array $extra = [])
    {
    }

    /**
     * Return the paths that match a forbidden pattern.
     *
     * @param list<string> $paths Paths relative to the plugin root (no slug prefix).
     * @return list<string>
     */
    public function find(array $paths): array
    {
        $vendorShips = $this->shipsVendor($paths);
        $found = [];
        foreach ($paths as $path) {
            $path = trim($path, '/');
            if ($path === '') {
                continue;
            }
            if ($this->isForbidden($path, $vendorShips)) {
                $found[] = $path;
            }
        }
        return $found;
    }

    /**
     * Return the paths with a hidden segment (dot-file or dot-dir at any level) or a *.phar file name.
     *
     * @param list<string> $paths Paths relative to the plugin root (no slug prefix).
     * @return list<string>
     */
    public function findHiddenOrPhar(array $paths): array
    {
        $found = [];
        foreach ($paths as $path) {
            $path = trim($path, '/');
            if ($path !== '' && $this->isHiddenOrPhar($path)) {
                $found[] = $path;
            }
        }
        return $found;
    }

    /**
     * Whether a path has a dot-prefixed segment or ends in .phar.
     *
     * @param string $path Relative path.
     * @return bool
     */
    private function isHiddenOrPhar(string $path): bool
    {
        if (str_ends_with(strtolower($path), '.phar')) {
            return true;
        }
        foreach (explode('/', $path) as $segment) {
            if (str_starts_with($segment, '.')) {
                return true;
            }
        }
        return false;
    }

    /**
     * Whether the file list contains a vendor directory.
     *
     * @param list<string> $paths Paths.
     * @return bool
     */
    private function shipsVendor(array $paths): bool
    {
        foreach ($paths as $path) {
            if (str_starts_with($path, 'vendor/')) {
                return true;
            }
        }
        return false;
    }

    /**
     * Whether one path is forbidden.
     *
     * @param string $path        Path relative to the plugin root.
     * @param bool   $vendorShips Whether vendor/ ships.
     * @return bool
     */
    private function isForbidden(string $path, bool $vendorShips): bool
    {
        if ($path === 'composer.lock' && !$vendorShips) {
            return true;
        }
        $segments = explode('/', $path);
        $inVendor = $segments[0] === 'vendor';
        $patterns = $inVendor ? self::STRICT : [...self::STRICT, ...self::OUTSIDE_VENDOR];
        foreach ($segments as $segment) {
            if ($this->matchesAny($segment, $patterns)) {
                return true;
            }
        }
        return $this->matchesAny($path, $this->extra) || $this->matchesAny(basename($path), $this->extra);
    }

    /**
     * fnmatch against a list of patterns.
     *
     * @param string       $subject  Subject.
     * @param list<string> $patterns Patterns.
     * @return bool
     */
    private function matchesAny(string $subject, array $patterns): bool
    {
        foreach ($patterns as $pattern) {
            if (fnmatch($pattern, $subject)) {
                return true;
            }
        }
        return false;
    }
}
