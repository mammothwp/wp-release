<?php

declare(strict_types=1);

namespace MammothWP\WPRelease\Build;

/**
 * Checks that the built plugin ships the runtime Composer dependencies it declares.
 *
 * Pure logic: callers pass the decoded composer.json / composer.lock, the zip paths (relative to the plugin root)
 * and the .distignore contents, so it can be tested without a build.
 */
final class VendorValidator
{
    /** Composer autoload files that every Composer 2 install generates. */
    private const AUTOLOAD_FILES = ['vendor/composer/autoload_real.php', 'vendor/composer/autoload_classmap.php'];

    /** Names that also exist inside vendor packages; unanchored in .distignore they strip those files too. */
    private const VENDOR_INTERNAL_NAMES = ['tests', 'docs', 'bin', 'README.md', 'composer.lock', 'vendor'];

    /**
     * Whether composer.json requires packages other than php, ext-* and lib-*.
     *
     * @param array<string, mixed> $composer Decoded composer.json.
     * @return bool
     */
    public function hasRuntimeRequires(array $composer): bool
    {
        return $this->runtimeRequires($composer) !== [];
    }

    /**
     * Problems that make the shipped vendor/ unusable; empty when fine or when nothing is required at runtime.
     *
     * @param array<string, mixed>      $composer Decoded composer.json.
     * @param array<string, mixed>|null $lock     Decoded composer.lock, null when unavailable.
     * @param list<string>              $paths    File paths in the zip, relative to the plugin root.
     * @return list<string>
     */
    public function problems(array $composer, ?array $lock, array $paths): array
    {
        if (!$this->hasRuntimeRequires($composer)) {
            return [];
        }
        $problems = [];
        if (!in_array('vendor/autoload.php', $paths, true)) {
            $problems[] = 'vendor/autoload.php is missing from the zip.';
        }
        foreach (self::AUTOLOAD_FILES as $file) {
            if (!in_array($file, $paths, true)) {
                $problems[] = $file . ' is missing from the zip (vendor/composer/autoload_*.php).';
            }
        }
        foreach ($this->packageNames($composer, $lock) as $name) {
            if (!$this->hasDirectory($paths, 'vendor/' . $name . '/')) {
                $problems[] = sprintf('Required package "%s" is missing: no files under vendor/%s/.', $name, $name);
            }
        }
        return $problems;
    }

    /**
     * Warnings for unanchored .distignore entries that also match files inside vendor packages.
     *
     * @param string $distignore Contents of .distignore.
     * @return list<string>
     */
    public function distignoreWarnings(string $distignore): array
    {
        $warnings = [];
        foreach (preg_split('/\R/', $distignore) ?: [] as $line) {
            $entry = trim($line);
            $name = rtrim($entry, '/');
            if ($entry === '' || $entry[0] === '#' || $entry[0] === '!' || $entry[0] === '/' || str_contains($name, '/')) {
                continue;
            }
            if (in_array($name, self::VENDOR_INTERNAL_NAMES, true)) {
                $warnings[] = sprintf(
                    '.distignore entry "%s" is unanchored and also strips "%s" inside vendor/ packages; use "/%s" to match the plugin root only.',
                    $entry,
                    $name,
                    $name
                );
            }
        }
        return $warnings;
    }

    /**
     * Runtime require names from composer.json (platform requirements removed).
     *
     * @param array<string, mixed> $composer Decoded composer.json.
     * @return list<string>
     */
    private function runtimeRequires(array $composer): array
    {
        $require = is_array($composer['require'] ?? null) ? array_keys($composer['require']) : [];
        return array_values(array_filter(
            array_map('strval', $require),
            static fn (string $name): bool => $name !== 'php' && !str_starts_with($name, 'php-') && !str_starts_with($name, 'ext-') && !str_starts_with($name, 'lib-')
        ));
    }

    /**
     * Package names that must have a vendor directory: composer.lock "packages" (non-dev), else composer.json require.
     *
     * @param array<string, mixed>      $composer Decoded composer.json.
     * @param array<string, mixed>|null $lock     Decoded composer.lock.
     * @return list<string>
     */
    private function packageNames(array $composer, ?array $lock): array
    {
        if (!is_array($lock['packages'] ?? null)) {
            return $this->runtimeRequires($composer);
        }
        $names = [];
        foreach ($lock['packages'] as $package) {
            if (is_array($package) && is_string($package['name'] ?? null) && ($package['type'] ?? '') !== 'metapackage') {
                $names[] = $package['name'];
            }
        }
        return $names;
    }

    /**
     * Whether any path lies below a directory prefix.
     *
     * @param list<string> $paths  Paths.
     * @param string       $prefix Directory prefix ending in "/".
     * @return bool
     */
    private function hasDirectory(array $paths, string $prefix): bool
    {
        foreach ($paths as $path) {
            if (str_starts_with($path, $prefix)) {
                return true;
            }
        }
        return false;
    }
}
